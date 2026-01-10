



const mongoose = require('mongoose');
const config = require('../backend/config/config');
const UserProgress = require('../backend/models/UserProgress');
const Module = require('../backend/models/Module');
const { classifyDifficultyFromPct } = require('../backend/utils/moduleAssignment');

function normalizeKey(v) {
  return String(v || '').trim().toLowerCase();
}

async function run() {
  console.log('Running per-module assignment validation...');

  await mongoose.connect(config.MONGO_URI, {
    useNewUrlParser: true,
    useUnifiedTopology: true,
  });

  const total = await UserProgress.countDocuments();
  if (!total) {
    console.log('No UserProgress records found.');
    await mongoose.disconnect();
    return;
  }

  const sampleSize = Math.min(10, total);
  const skip = total > sampleSize ? Math.floor(Math.random() * Math.max(1, total - sampleSize)) : 0;

  console.log(`Sampling ${sampleSize} users out of ${total} (skip=${skip}).`);

  const progresses = await UserProgress.find().skip(skip).limit(sampleSize).lean();

  let usersWithIssues = 0;
  let duplicateAssignedIssues = 0;
  let multiTypeIssues = 0;
  let difficultyIssues = 0;
  let rangeIssues = 0;

  for (const progress of progresses) {
    const username = progress.username;
    const assignedIds = Array.from(progress.assignedModules || []);
    if (!assignedIds.length) {
      console.log(`- ${username}: no assignedModules; skipping detailed checks.`);
      continue;
    }

    const modules = await Module.find({ _id: { $in: assignedIds } }).lean();
    const modulesById = new Map(modules.map(m => [String(m._id), m]));

    let hasIssue = false;

    const seenIds = new Set();
    for (const id of assignedIds) {
      if (seenIds.has(id)) {
        hasIssue = true;
        duplicateAssignedIssues += 1;
        console.warn(`  [Duplicate] user=${username} has duplicate assigned module id=${id}`);
        break;
      }
      seenIds.add(id);
    }

    const typeToId = new Map();
    for (const id of assignedIds) {
      const mod = modulesById.get(String(id));
      if (!mod) continue;
      const key = normalizeKey(mod.title);
      if (typeToId.has(key)) {
        hasIssue = true;
        multiTypeIssues += 1;
        console.warn(`  [MultiType] user=${username} has multiple modules for type=${mod.title}`);
        break;
      }
      typeToId.set(key, id);
    }

    const moduleScores = progress.moduleScores || {};
    const legacy = progress.moduleTypeScores || {};

    for (const id of assignedIds) {
      const mod = modulesById.get(String(id));
      if (!mod || !mod.scoreRange) continue;

      const typeKey = normalizeKey(mod.title);

      let label = Object.keys(moduleScores).find(k => normalizeKey(k) === typeKey);
      let stats = label ? moduleScores[label] : null;
      if (!stats) {

        label = Object.keys(legacy).find(k => normalizeKey(k) === typeKey);
        stats = label ? legacy[label] : null;
      }
      if (!stats) continue;

      const pct = Number(stats.pct ?? stats.percentage ?? 0) || 0;
      const min = mod.scoreRange.min ?? 0;
      const max = mod.scoreRange.max ?? 100;

      if (pct < min || pct > max) {
        hasIssue = true;
        rangeIssues += 1;
        console.warn(`  [Range] user=${username} type=${mod.title} pct=${pct} not in [${min}, ${max}]`);
      }

      const expectedDiff = classifyDifficultyFromPct(pct);
      const actualDiff = String(mod.difficulty || '').trim();
      if (actualDiff && normalizeKey(actualDiff) !== normalizeKey(expectedDiff)) {
        hasIssue = true;
        difficultyIssues += 1;
        console.warn(`  [Difficulty] user=${username} type=${mod.title} pct=${pct} expected=${expectedDiff} actual=${actualDiff}`);
      }
    }

    if (hasIssue) {
      usersWithIssues += 1;
    } else {
      console.log(`✔ ${username}: assignments look consistent.`);
    }
  }

  console.log('--- Validation summary ---');
  console.log(`Users sampled           : ${progresses.length}`);
  console.log(`Users with any issues   : ${usersWithIssues}`);
  console.log(`Duplicate module IDs    : ${duplicateAssignedIssues}`);
  console.log(`Multi-module per type   : ${multiTypeIssues}`);
  console.log(`Difficulty mismatches   : ${difficultyIssues}`);
  console.log(`Score range violations  : ${rangeIssues}`);

  await mongoose.disconnect();
  console.log('Validation complete.');
}

run().catch((err) => {
  console.error('Fatal validation error:', err);
  process.exit(1);
});

