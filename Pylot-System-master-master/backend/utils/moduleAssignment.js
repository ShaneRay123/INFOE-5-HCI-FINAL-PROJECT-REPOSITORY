const Module = require('../models/Module');
const Exam = require('../models/Exam');
const Result = require('../models/Result');
const UserProgress = require('../models/UserProgress');
const { tierKeyFromPercentage } = require('./tiers');

const NON_CHECKPOINT_FILTER = {
  isActive: true,
  $or: [
    { 'checkpointQuiz.isCheckpointQuiz': { $exists: false } },
    { 'checkpointQuiz.isCheckpointQuiz': false }
  ]
};

function normalizeModuleTypeLabel(raw) {
  if (!raw && raw !== 0) return 'Unknown';
  const label = String(raw).trim();
  if (!label) return 'Unknown';
  return label;
}

function normalizeKey(label) {
  return String(label || '')
    .trim()
    .toLowerCase();
}

function toAnswerIndex(val, options) {
  const letterMap = { A: 0, B: 1, C: 2, D: 3, E: 4, F: 5 };
  if (typeof val === 'number' && !Number.isNaN(val)) return val;
  if (typeof val === 'string') {
    const s = val.trim();
    if (!s) return -1;
    const asNum = Number(s);
    if (!Number.isNaN(asNum)) return asNum;
    const upper = s.toUpperCase();
    if (letterMap[upper] !== undefined) return letterMap[upper];
    const opts = (options || []).map(o => String(o));
    const idx = opts.findIndex(opt => opt.trim().toLowerCase() === s.toLowerCase());
    return idx;
  }
  return -1;
}


function computeModuleScoresFromQuestions(questions, answers) {
  const perType = new Map(); // key -> { label, correct, total }

  (questions || []).forEach((q) => {
    const rawLabel = q.moduleTitle != null ? q.moduleTitle : q.moduleType;
    const label = normalizeModuleTypeLabel(rawLabel);
    const key = normalizeKey(label);
    if (label === 'Unknown') {
      console.warn(`[Assign] Unknown moduleType for question id=${q.id || '(no id)'}`);
    }

    const options = (q.options || []).map(o => String(o));
    const correctIndex = toAnswerIndex(q.correctAnswer, options);
    const userIndex = toAnswerIndex(answers ? answers[q.id] : undefined, options);
    const isCorrect = correctIndex >= 0 && userIndex === correctIndex;

    const agg = perType.get(key) || { label, correct: 0, total: 0 };

    if (agg.label === 'Unknown' && label !== 'Unknown') {
      agg.label = label;
    }
    agg.total += 1;
    if (isCorrect) agg.correct += 1;
    perType.set(key, agg);
  });

  const moduleScores = {}; 
  const legacyScores = {}; 
  let overallCorrect = 0;
  let overallTotal = 0;

  for (const { label, correct, total } of perType.values()) {
    const pct = total > 0 ? Math.round((correct / total) * 100) : 0;
    const tier = tierKeyFromPercentage(pct);
    moduleScores[label] = { correct, total, pct, tier };
    legacyScores[label] = { correct, total, percentage: pct };
    overallCorrect += correct;
    overallTotal += total;
  }

  const overallPct = overallTotal > 0 ? Math.round((overallCorrect / overallTotal) * 100) : 0;

  return { moduleScores, legacyScores, overallPct };
}

async function selectAssignedModulesFromScores(moduleScores, advancedUser) {
  const allModules = await Module.find(NON_CHECKPOINT_FILTER);
  const byType = {}; // label -> module




  for (const [label, stats] of Object.entries(moduleScores || {})) {
    const pct = Number(stats.pct ?? stats.percentage ?? 0);
    if (!Number.isFinite(pct)) continue;

    const typeKey = normalizeKey(label);
    const expectedTier = tierKeyFromPercentage(pct);
    const candidates = allModules.filter(m => {
      const titleKey = normalizeKey(m.title);
      const min = m.scoreRange?.min ?? 0;
      const max = m.scoreRange?.max ?? 100;
      return titleKey === typeKey && String(m.tier || '').trim() === expectedTier && pct >= min && pct <= max;
    });

    if (!candidates.length) {
      console.log(`[Assign] ${label}: ${stats.correct || 0}/${stats.total || 0} = ${pct}% → ${expectedTier} → no matching variant`);
      continue;
    }

    candidates.sort((a, b) => {
      const aMin = a.scoreRange?.min ?? 0;
      const bMin = b.scoreRange?.min ?? 0;
      if (bMin !== aMin) return bMin - aMin;
      return String(b.tier).localeCompare(String(a.tier));
    });
    const chosen = candidates[0];

    if (chosen) {
      byType[label] = chosen;
      const tierLabel = chosen.tier || expectedTier;
      console.log(`[Assign] ${label}: ${Number(stats.correct || 0)}/${Number(stats.total || 0)} = ${pct}% → ${tierLabel}`);
    }
  }

  const assignedModules = Object.values(byType);
  const assignedIds = assignedModules.map(m => m._id.toString());

  const accessibleIds = advancedUser
    ? allModules.map(m => m._id.toString())
    : assignedIds;

  return { assignedIds, accessibleIds };
}


async function recomputeAssignmentsForUserProgress(progress) {
  const username = progress.username;
  const latestResult = await Result.findOne({ username, examSubject: 'exam' }).sort({ createdAt: -1 });

  if (!latestResult) {
    return {
      hadResult: false,
      moduleScores: progress.moduleScores || {},
      overallPct: progress.overallModuleScore || 0,
      advancedUser: !!progress.advancedUser,
      assignedIds: progress.assignedModules || [],
      accessibleIds: progress.accessibleModules || [],
    };
  }

  const exam = await Exam.findById(latestResult.examId);
  if (!exam) {
    return {
      hadResult: false,
      moduleScores: progress.moduleScores || {},
      overallPct: progress.overallModuleScore || 0,
      advancedUser: !!progress.advancedUser,
      assignedIds: progress.assignedModules || [],
      accessibleIds: progress.accessibleModules || [],
    };
  }

  const answers = latestResult.answers || {};
  const { moduleScores, legacyScores, overallPct } = computeModuleScoresFromQuestions(exam.questions || [], answers);
  const advancedUser = overallPct >= 90;

  const { assignedIds, accessibleIds } = await selectAssignedModulesFromScores(moduleScores, advancedUser);

  progress.moduleTypeScores = legacyScores;
  progress.userModuleScores = Object.fromEntries(Object.entries(legacyScores).map(([k, v]) => [k, v.percentage]));
  progress.moduleScores = moduleScores;
  progress.overallModuleScore = overallPct;
  progress.advancedUser = advancedUser;
  progress.assignedModules = assignedIds; 
  progress.accessibleModules = accessibleIds; 

  if (Number.isFinite(overallPct)) {
    progress.preAssessmentScore = overallPct;
    if (!progress.hasCompletedPreAssessment) {
      progress.hasCompletedPreAssessment = true;
    }
    if (!progress.preAssessmentCompletedAt) {
      const completedAt = latestResult.createdAt || new Date();
      progress.preAssessmentCompletedAt = completedAt;
    }
  }

  return { hadResult: true, moduleScores, overallPct, advancedUser, assignedIds, accessibleIds };
}

module.exports = {
  computeModuleScoresFromQuestions,
  recomputeAssignmentsForUserProgress,
};
