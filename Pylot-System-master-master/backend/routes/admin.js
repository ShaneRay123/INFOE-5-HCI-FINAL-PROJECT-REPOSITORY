const express = require('express');
const bcrypt = require('bcrypt');
const User = require('../models/User');
const Module = require('../models/Module');
const UserProgress = require('../models/UserProgress');
const { TIERS, tierFromPercentage } = require('../utils/tiers');
const TierConfig = require('../models/TierConfig');
const { refreshTierConfigCache, getTierConfigCached } = require('../utils/tiers');
const { writeAuditLog } = require('../utils/audit');
const { authenticateToken, requireAdmin } = require('../middleware/auth');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const config = require('../config/config');

const router = express.Router();

const { body, param, validationResult } = require('express-validator');
const { rateLimit, ipKeyGenerator } = require('express-rate-limit');

const adminLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, 
  max: 100, 
  message: {
    success: false,
    message: 'Too many admin requests, please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,

  keyGenerator: (req, res) => (req.user && req.user._id ? String(req.user._id) : ipKeyGenerator(req, res)),

  skip: (req, res) => req.method === 'OPTIONS' || (config.isDevelopment && (req.ip === '127.0.0.1' || req.ip === '::1'))
});


router.use(authenticateToken, requireAdmin, adminLimiter);


router.use(authenticateToken, requireAdmin);


function validateTierConfig(tiers) {
  if (!Array.isArray(tiers) || tiers.length !== 2) {
    return { ok: false, message: 'Tier config must include exactly 2 tiers' };
  }

  const allowedKeys = new Set(['Tier 1', 'Tier 2']);
  const seenKeys = new Set();
  const labels = new Set();

  const normalized = tiers.map((t) => ({
    key: String(t.key || '').trim(),
    label: String(t.label || '').trim(),
    min: Number(t.min),
    max: Number(t.max),
  }));

  for (const t of normalized) {
    if (!allowedKeys.has(t.key)) return { ok: false, message: `Invalid tier key: ${t.key}` };
    if (seenKeys.has(t.key)) return { ok: false, message: `Duplicate tier key: ${t.key}` };
    seenKeys.add(t.key);

    if (!t.label) return { ok: false, message: `Tier label is required for ${t.key}` };
    if (t.label !== t.key) return { ok: false, message: 'Tier labels are fixed and cannot be edited' };
    const labelLower = t.label.toLowerCase();
    if (labels.has(labelLower)) return { ok: false, message: 'Tier names must be unique' };
    labels.add(labelLower);

    if (!Number.isFinite(t.min) || !Number.isFinite(t.max)) return { ok: false, message: `Invalid min/max for ${t.key}` };
    if (t.min < 0 || t.max > 100) return { ok: false, message: `Ranges must be within 0-100 for ${t.key}` };
    if (t.min > t.max) return { ok: false, message: `Min must be <= Max for ${t.key}` };
  }

  const sorted = [...normalized].sort((a, b) => a.min - b.min || a.max - b.max);
  for (let i = 0; i < sorted.length; i++) {
    for (let j = i + 1; j < sorted.length; j++) {
      const a = sorted[i];
      const b = sorted[j];
      const overlaps = Math.max(a.min, b.min) <= Math.min(a.max, b.max);
      if (overlaps) return { ok: false, message: 'No overlapping score ranges are allowed' };
    }
  }

  const tier1 = normalized.find(t => t.key === 'Tier 1');
  const tier2 = normalized.find(t => t.key === 'Tier 2');
  if (!tier1 || !tier2) return { ok: false, message: 'Tier 1 and Tier 2 are required' };

  if (tier1.min !== 0) return { ok: false, message: 'Tier 1 must always start at 0' };
  if (tier2.max !== 100) return { ok: false, message: 'Tier 2 must always end at 100' };
  if (!(tier1.max < tier2.min)) return { ok: false, message: 'Tier 1 max must be less than Tier 2 min' };

  if (Math.min(tier1.min, tier2.min) !== 0) return { ok: false, message: 'Ranges must fully cover 0–100' };
  if (Math.max(tier1.max, tier2.max) !== 100) return { ok: false, message: 'Ranges must fully cover 0–100' };
  const coversAll = (score) => (
    (score >= tier1.min && score <= tier1.max) ||
    (score >= tier2.min && score <= tier2.max)
  );
  for (let s = 0; s <= 100; s++) {
    if (!coversAll(s)) return { ok: false, message: 'Ranges must fully cover 0–100' };
  }

  return { ok: true, normalized };
}


router.get('/tier-config', catchAsync(async (req, res) => {
  await TierConfig.deleteMany({ key: { $nin: ['Tier 1', 'Tier 2'] } });

  await Module.updateMany(
    { tier: { $nin: ['Tier 1', 'Tier 2'] } },
    { $set: { tier: 'Tier 2' } }
  );

  await refreshTierConfigCache();

  const rows = await TierConfig.find({ key: { $in: ['Tier 1', 'Tier 2'] } }).sort({ key: 1 }).lean();
  if (!rows || rows.length === 0) {
    const cached = getTierConfigCached();
    return res.json({ success: true, tiers: (cached.tiers || []).map(t => ({ key: t.key, label: t.label, min: t.min, max: t.max })) });
  }
  res.json({ success: true, tiers: rows.map(r => ({ key: r.key, label: r.label, min: r.min, max: r.max })) });
}));


router.put('/tier-config', catchAsync(async (req, res) => {
  const tiers = req.body && (req.body.tiers || req.body);
  const beforeRows = await TierConfig.find({}).sort({ key: 1 }).lean();
  const before = beforeRows && beforeRows.length
    ? beforeRows.map(r => ({ key: r.key, label: r.label, min: r.min, max: r.max }))
    : (getTierConfigCached().tiers || []).map(t => ({ key: t.key, label: t.label, min: t.min, max: t.max }));

  const v = validateTierConfig(tiers);
  if (!v.ok) {
    return res.status(400).json({ success: false, message: v.message });
  }

  const normalized = v.normalized;

  for (const t of normalized) {
    await TierConfig.updateOne(
      { key: t.key },
      { $set: { key: t.key, label: t.label, min: t.min, max: t.max } },
      { upsert: true }
    );
  }

  await TierConfig.deleteMany({ key: { $nin: ['Tier 1', 'Tier 2'] } });

  await Module.updateMany(
    { tier: { $nin: ['Tier 1', 'Tier 2'] } },
    { $set: { tier: 'Tier 2' } }
  );

  await refreshTierConfigCache();

  const after = normalized.map(t => ({ key: t.key, label: t.label, min: t.min, max: t.max }));

  if (JSON.stringify(before) !== JSON.stringify(after)) {
    for (let i = 0; i < after.length; i++) {
      const b = before.find(x => x.key === after[i].key);
      const a = after[i];
      if (!b) continue;
      const changed = b.label !== a.label || b.min !== a.min || b.max !== a.max;
      if (changed) {
        await writeAuditLog(req, {
          action: 'UPDATE_TIER_CONFIG',
          entityType: 'TierConfig',
          entityId: a.key,
          message: `[Admin] Updated ${a.key}: ${b.label} ${b.min}–${b.max} → ${a.label} ${a.min}–${a.max}`,
          before: b,
          after: a,
        });
      }
    }
  }

  res.json({ success: true, tiers: after });
}));


const updateUserValidation = [
  param('id')
    .isMongoId()
    .withMessage('Invalid user ID'),
    
  body('username')
    .optional()
    .trim()
    .isLength({ min: 3, max: 30 })
    .withMessage('Username must be between 3 and 30 characters')
    .matches(/^[a-zA-Z0-9_]+$/)
    .withMessage('Username can only contain letters, numbers, and underscores'),
  
  body('firstName')
    .optional()
    .trim()
    .isLength({ min: 1, max: 100 })
    .withMessage('First name is required')
    .matches(/^[a-zA-Z\s]+$/)
    .withMessage('First name can only contain letters and spaces'),

  body('middleName')
    .optional({ nullable: true })
    .trim()
    .matches(/^[a-zA-Z\s]+$/)
    .withMessage('Middle name can only contain letters and spaces'),

  body('lastName')
    .optional()
    .trim()
    .isLength({ min: 1, max: 100 })
    .withMessage('Last name is required')
    .matches(/^[a-zA-Z\s]+$/)
    .withMessage('Last name can only contain letters and spaces'),
  
  body('age')
    .optional()
    .isInt({ min: 1, max: 150 })
    .withMessage('Age must be a number between 1 and 150'),
  
  body('address')
    .optional()
    .trim()
    .isLength({ min: 5, max: 200 })
    .withMessage('Address must be between 5 and 200 characters'),
  
  body('gender')
    .optional()
    .isIn(['Male', 'Female', 'Other'])
    .withMessage('Gender must be Male, Female, or Other'),
    
  body('role')
    .optional()
    .isIn(['user', 'admin'])
    .withMessage('Role must be user or admin'),
    
  body('isApproved')
    .optional()
    .isBoolean()
    .withMessage('isApproved must be a boolean')
];


const handleValidationErrors = (req, res, next) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({
      success: false,
      message: 'Validation failed',
      errors: errors.array()
    });
  }
  next();
};


router.get('/users', 
  catchAsync(async (req, res) => {
    const { role, page = 1, limit = 10 } = req.query;
    
    const filter = {};
    if (role) filter.role = role;
    
    const options = {
      page: parseInt(page),
      limit: parseInt(limit),
      sort: { isOnline: -1, lastLogin: -1, createdAt: -1 }
    };
    
    const usersRaw = await User.find(filter)
      .select('studentId firstName middleName lastName username email role isOnline lastLogin lastActive createdAt') 
      .sort({ isOnline: -1, lastActive: -1, createdAt: -1 })
      .limit(options.limit * 1)
      .skip((options.page - 1) * options.limit)
      .lean(); 

    const now = Date.now();
    const STALE_MS = (config.HEARTBEAT_STALE_SECONDS || 30) * 1000;

    const users = (usersRaw || []).map(u => {
      const role = u.role ? String(u.role).toLowerCase() : '';
      const lastActive = u.lastActive || u.lastLogin || null;
      const isOnlineDerived = !!(lastActive && (now - new Date(lastActive).getTime()) < STALE_MS);

      return {
        ...u,
        fullName: [u.firstName, u.middleName, u.lastName]
          .filter(Boolean)
          .map(s => String(s).trim())
          .filter(Boolean)
          .join(' '),
        lastActive,

        isOnline: role === 'admin' ? true : isOnlineDerived,
      };
    });
    
    const total = await User.countDocuments(filter);
    
    sendResponse(res, 200, true, 'Users retrieved successfully', {
      users,
      pagination: {
        currentPage: options.page,
        totalPages: Math.ceil(total / options.limit),
        totalUsers: total,
        hasNextPage: options.page < Math.ceil(total / options.limit),
        hasPrevPage: options.page > 1
      }
    });
  })
);


router.get('/users/:id',
  param('id').isMongoId().withMessage('Invalid user ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    const userRaw = await User.findById(req.params.id)
      .select('-password')
      .lean();
    if (!userRaw) {
      throw new AppError('User not found', 404);
    }

    const user = {
      ...userRaw,
      fullName: [userRaw.firstName, userRaw.middleName, userRaw.lastName].filter(Boolean).map(s => String(s).trim()).filter(Boolean).join(' ')
    };
    
    sendResponse(res, 200, true, 'User retrieved successfully', { user });
  })
);


router.post('/users/:id/approve',
  param('id').isMongoId().withMessage('Invalid user ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const user = await User.findByIdAndUpdate(
      req.params.id,
      { isApproved: true },
      { new: true }
    );
    
    if (!user) {
      throw new AppError('User not found', 404);
    }
    
    sendResponse(res, 200, true, 'User approved successfully', { user });
  })
);


router.put('/users/:id',
  updateUserValidation,
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { username, firstName, middleName, lastName, age, address, gender, role, isApproved } = req.body;

    if (username) {
      const usernameRegex = new RegExp(`^${String(username).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`, 'i');
      const existingUser = await User.findOne({
        username: usernameRegex,
        _id: { $ne: req.params.id }
      });
      if (existingUser) {
        throw new AppError('Username already exists', 409);
      }
    }
    
    const updateData = {};
    if (username !== undefined) updateData.username = username;
    if (firstName !== undefined) updateData.firstName = firstName;
    if (middleName !== undefined) updateData.middleName = middleName;
    if (lastName !== undefined) updateData.lastName = lastName;
    if (age !== undefined) updateData.age = age;
    if (address !== undefined) updateData.address = address;
    if (gender !== undefined) updateData.gender = gender;
    if (role !== undefined) updateData.role = role;
    if (isApproved !== undefined) updateData.isApproved = isApproved;
    
    const user = await User.findByIdAndUpdate(
      req.params.id,
      updateData,
      { new: true, runValidators: true }
    );
    
    if (!user) {
      throw new AppError('User not found', 404);
    }
    
    sendResponse(res, 200, true, 'User updated successfully', { user });
  })
);


router.post('/users/:id/soft-delete',
  param('id').isMongoId().withMessage('Invalid user ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    if (req.params.id === req.user._id.toString()) {
      throw new AppError('You cannot deactivate your own account', 400);
    }
    
    const user = await User.findById(req.params.id);
    if (!user) {
      throw new AppError('User not found', 404);
    }

    if (String(user.role).toLowerCase() === 'admin') {
      throw new AppError('You cannot deactivate an admin account', 400);
    }
    
    if (user.isDeleted) {
      throw new AppError('User is already deactivated', 400);
    }
    
    await user.softDelete();
    
    sendResponse(res, 200, true, 
      `User '${user.username}' has been deactivated. Their data is preserved but they cannot login.`);
  })
);


router.post('/users/:id/restore',
  param('id').isMongoId().withMessage('Invalid user ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    const user = await User.findOne({ _id: req.params.id, isDeleted: true });
    if (!user) {
      throw new AppError('Deactivated user not found', 404);
    }
    
    user.isDeleted = false;
    user.deletedAt = undefined;
    await user.save();
    
    sendResponse(res, 200, true, 
      `User '${user.username}' has been restored and can now login again.`);
  })
);


router.get('/users/deleted',
  catchAsync(async (req, res) => {
    const { page = 1, limit = 10 } = req.query;
    
    const options = {
      page: parseInt(page),
      limit: parseInt(limit),
      sort: { deletedAt: -1 }
    };
    
    const usersRaw = await User.find({ isDeleted: true })
      .select('-password')
      .sort(options.sort)
      .limit(options.limit * 1)
      .skip((options.page - 1) * options.limit)
      .lean();

    const users = (usersRaw || []).map(u => ({
      ...u,
      fullName: [u.firstName, u.middleName, u.lastName].filter(Boolean).map(s => String(s).trim()).filter(Boolean).join(' ')
    }));
    
    const total = await User.countDocuments({ isDeleted: true });
    
    sendResponse(res, 200, true, 'Deactivated users retrieved successfully', {
      users,
      pagination: {
        currentPage: options.page,
        totalPages: Math.ceil(total / options.limit),
        totalUsers: total,
        hasNextPage: options.page < Math.ceil(total / options.limit),
        hasPrevPage: options.page > 1
      }
    });
  })
);


router.delete('/users/:id',
  param('id').isMongoId().withMessage('Invalid user ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    if (req.params.id === req.user._id.toString()) {
      throw new AppError('You cannot delete your own account', 400);
    }
    
    
    const user = await User.findOne({ _id: req.params.id }).setOptions({ skipFilter: true });
    if (!user) {
      throw new AppError('User not found', 404);
    }

    if (String(user.role).toLowerCase() === 'admin') {
      throw new AppError('You cannot delete an admin account', 400);
    }
    
    
    await User.findByIdAndDelete(req.params.id);
    
    sendResponse(res, 200, true, 
      `User '${user.username}' and all associated data (progress, results, certificates) have been permanently deleted.`);
  })
);


router.get('/stats',
  catchAsync(async (req, res) => {
    
    const [
      totalUsers,
      activeUsers,
      softDeletedUsers,
      approvedUsers,
      pendingUsers,
      adminUsers
    ] = await Promise.all([
      User.countDocuments().setOptions({ skipFilter: true }),
      User.countDocuments({ isDeleted: { $ne: true } }),
      User.countDocuments({ isDeleted: true }),
      User.countDocuments({ isApproved: true, isDeleted: { $ne: true } }),
      User.countDocuments({ isApproved: { $ne: true }, role: { $ne: 'admin' }, isDeleted: { $ne: true } }),
      User.countDocuments({ role: 'admin', isDeleted: { $ne: true } })
    ]);
    
    const stats = {
      totalUsers, 
      activeUsers, 
      softDeletedUsers, 
      approvedUsers, 
      pendingUsers, 
      adminUsers, 
      regularUsers: activeUsers - adminUsers 
    };
    
    sendResponse(res, 200, true, 'Statistics retrieved successfully', { stats });
  })
);

router.get('/module-progress',
  catchAsync(async (req, res) => {
    const { page = 1, limit = 20 } = req.query;

    const options = {
      page: parseInt(page, 10),
      limit: parseInt(limit, 10),
    };

    const [progressesRaw, total] = await Promise.all([
      UserProgress.find({}).sort({ updatedAt: -1 })
        .limit(options.limit * 1)
        .skip((options.page - 1) * options.limit)
        .lean(),
      UserProgress.countDocuments(),
    ]);

    const seen = new Set();
    const progresses = [];
    for (const p of progressesRaw || []) {
      const key = p.username || '__no_username__';
      if (seen.has(key)) continue;
      seen.add(key);
      progresses.push(p);
    }

    const items = progresses.map(p => ({
      username: p.username,
      hasCompletedPreAssessment: !!p.hasCompletedPreAssessment,
      overall: typeof p.overallModuleScore === 'number' ? p.overallModuleScore : null,
      advancedUser: !!p.advancedUser,
      assignedCount: Array.isArray(p.assignedModules) ? p.assignedModules.length : 0,
      moduleScores: p.moduleScores || {},
    }));

    sendResponse(res, 200, true, 'Module progress retrieved successfully', {
      items,
      pagination: {
        currentPage: options.page,
        totalPages: Math.ceil(total / options.limit),
        totalRecords: total,
        hasNextPage: options.page < Math.ceil(total / options.limit),
        hasPrevPage: options.page > 1,
      },
    });
  })
);

router.get('/validate-assignments',
  catchAsync(async (req, res) => {
    const progresses = await UserProgress.find({}).lean();
    const summary = { totalUsers: progresses.length, ok: 0, duplicates: 0, mismatched: 0 };
    const issues = [];

    const scoreToTier = (s) => tierFromPercentage(Number(s));

    for (const p of progresses) {
      const username = p.username;
      const assigned = Array.isArray(p.assignedModules) ? p.assignedModules : [];
      if (assigned.length === 0) {
        summary.ok += 1;
        console.log(`? ${username}: ok (no assignments)`);
        continue;
      }

      const modules = await Module.find({ _id: { $in: assigned }, isActive: true }).lean();
      const byType = new Map();
      for (const m of modules) {
        const arr = byType.get(m.title) || [];
        arr.push(m);
        byType.set(m.title, arr);
      }

      let hasDupes = false;
      let hasMismatch = false;
      const mismatches = [];

      for (const [type, list] of byType.entries()) {
        if (list.length > 1) {
          hasDupes = true;
        }
        const scoreEntry = p.moduleTypeScores && p.moduleTypeScores[type];
        if (scoreEntry && typeof scoreEntry.percentage === 'number') {
          const s = scoreEntry.percentage;
          const expected = scoreToTier(s);
          const picked = list[0];
          const actual = String(picked.tier || '').trim();
          if (actual !== expected) {
            hasMismatch = true;
            mismatches.push({ type, score: s, expected, actual, moduleId: String(picked._id) });
          }
        }
      }

      if (hasDupes) {
        summary.duplicates += 1;
        const dupDetails = [...byType.entries()]
          .filter(([_, arr]) => arr.length > 1)
          .map(([t, arr]) => ({ moduleType: t, count: arr.length, moduleIds: arr.map(x => String(x._id)) }));
        issues.push({ username, type: 'duplicates', details: dupDetails });
        console.log(`?? ${username}: duplicates in [${dupDetails.map(d => d.moduleType).join(', ')}]`);
      }

      if (hasMismatch) {
        summary.mismatched += 1;
        issues.push({ username, type: 'mismatched', details: mismatches });
        for (const mm of mismatches) {
          console.log(`? ${username}: ${mm.type} expected ${mm.expected} got ${mm.actual} at ${mm.score}%`);
        }
      }

      if (!hasDupes && !hasMismatch) {
        summary.ok += 1;
        console.log(`? ${username}: ok`);
      }
    }

    res.json({ success: true, summary, issues });
  })
);

router.get('/catalog-audit',
  catchAsync(async (req, res) => {
    const modules = await Module.find({
      isActive: true,
      $or: [
        { 'checkpointQuiz.isCheckpointQuiz': { $exists: false } },
        { 'checkpointQuiz.isCheckpointQuiz': false }
      ]
    }).lean();

    const BRACKETS = TIERS();

    const byTitle = new Map();
    for (const m of modules) {
      const t = String(m.title || '').trim();
      if (!t) continue;
      const arr = byTitle.get(t) || [];
      arr.push({
        _id: String(m._id),
        moduleId: m.moduleId,
        title: t,
        tier: String(m.tier || '').trim(),
        min: Number(m.scoreRange?.min ?? -1),
        max: Number(m.scoreRange?.max ?? -1),
      });
      byTitle.set(t, arr);
    }

    const report = [];

    const intersects = (aMin, aMax, bMin, bMax) => Math.max(aMin, bMin) <= Math.min(aMax, bMax);

    for (const [title, list] of byTitle.entries()) {
      const issues = [];
      const warnings = [];
      const existing = list.map(x => ({ tier: x.tier, min: x.min, max: x.max, moduleId: x.moduleId, _id: x._id }));

      for (const x of list) {
        if (!(x.min >= 0 && x.max <= 100 && x.min < x.max)) {
          warnings.push({ kind: 'invalid-range', entry: x });
        }
      }

      const overlaps = [];
      const sorted = [...list].sort((a,b) => (a.min - b.min) || (a.max - b.max));
      for (let i=0;i<sorted.length;i++) {
        for (let j=i+1;j<sorted.length;j++) {
          if (intersects(sorted[i].min, sorted[i].max, sorted[j].min, sorted[j].max)) {
            overlaps.push({ a: sorted[i], b: sorted[j] });
          }
        }
      }

      const missingBrackets = [];
      for (const b of BRACKETS) {
        const has = list.some(x => x.tier === b.name && intersects(x.min, x.max, b.min, b.max));
        if (!has) missingBrackets.push(b.name);
      }

      const outOfPolicy = list
        .filter(x => !BRACKETS.some(b => b.name === x.tier))
        .map(x => ({ tier: x.tier, min: x.min, max: x.max, moduleId: x.moduleId, _id: x._id }));

      const duplicates = [];
      for (const b of BRACKETS) {
        const sameTier = list.filter(x => x.tier === b.name).sort((a,b) => a.min - b.min);
        for (let i=0;i<sameTier.length;i++) {
          for (let j=i+1;j<sameTier.length;j++) {
            if (intersects(sameTier[i].min, sameTier[i].max, sameTier[j].min, sameTier[j].max)) {
              duplicates.push({ tier: b.name, a: sameTier[i], b: sameTier[j] });
            }
          }
        }
      }

      const ok = missingBrackets.length === 0 && overlaps.length === 0 && warnings.length === 0 && duplicates.length === 0;

      report.push({
        title,
        ok,
        existing,
        missingBrackets,
        overlaps,
        duplicates,
        outOfPolicy,
        warnings,
        suggestions: missingBrackets.map(name => {
          const b = BRACKETS.find(x => x.name === name);
          return { action: 'add', tier: name, scoreRange: { min: b.min, max: b.max } };
        })
      });
    }

    res.json({ success: true, titles: report.length, report });
  })
);

module.exports = router;

