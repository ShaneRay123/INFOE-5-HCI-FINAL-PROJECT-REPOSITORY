const express = require('express');
const { body, param, query, validationResult } = require('express-validator');
const Module = require('../models/Module');
const UserProgress = require('../models/UserProgress');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const { authenticateToken, requireAdmin } = require('../middleware/auth');
const { writeAuditLog } = require('../utils/audit');

const router = express.Router();

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

router.get('/',
  authenticateToken,
  catchAsync(async (req, res) => {
    const { score } = req.query;
    
    let filter = {
      isActive: true,
      $or: [
        { 'checkpointQuiz.isCheckpointQuiz': { $exists: false } },
        { 'checkpointQuiz.isCheckpointQuiz': false }
      ]
    };
    
    
    if (score) {
      const scoreNum = parseInt(score);
      filter['scoreRange.min'] = { $lte: scoreNum };
      filter['scoreRange.max'] = { $gte: scoreNum };
    }
    
    
    if (req.user.role !== 'admin') {
      const userProgress = await UserProgress.findOne({ username: req.user.username });
      if (userProgress && userProgress.accessibleModules.length > 0) {
        filter._id = { $in: userProgress.accessibleModules };
      }
    }
    
    const modules = await Module.find(filter);
    sendResponse(res, 200, true, 'Modules retrieved successfully', { modules });
  })
);


router.get('/by-score/:score',
  authenticateToken,
  param('score').isInt({ min: 0, max: 100 }).withMessage('Invalid score'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const score = parseInt(req.params.score);

    const filter = {
      isActive: true,
      'scoreRange.min': { $lte: score },
      'scoreRange.max': { $gte: score },
      $or: [
        { 'checkpointQuiz.isCheckpointQuiz': { $exists: false } },
        { 'checkpointQuiz.isCheckpointQuiz': false }
      ]
    };

    const modules = await Module.find(filter);
    sendResponse(res, 200, true, 'Modules retrieved successfully', { modules });
  })
);

router.get('/:id',
  authenticateToken,
  param('id').isMongoId().withMessage('Invalid module ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const module = await Module.findById(req.params.id);
    if (!module) {
      throw new AppError('Module not found', 404);
    }
    
    
    if (req.user.role !== 'admin') {
      const userProgress = await UserProgress.findOne({ username: req.user.username });
      if (!userProgress || !userProgress.accessibleModules.includes(req.params.id)) {
        throw new AppError('Access denied to this module', 403);
      }
    }
    
    sendResponse(res, 200, true, 'Module retrieved successfully', { module });
  })
);

router.post('/',
  authenticateToken,
  requireAdmin,
  body('moduleId').trim().notEmpty().withMessage('Module ID is required'),
  body('title').trim().notEmpty().withMessage('Title is required'),
  body('tier').optional().isIn(['Tier 1', 'Tier 2']).withMessage('Tier must be Tier 1 or Tier 2'),
  body('scoreRange.min').isInt({ min: 0, max: 100 }).withMessage('Score range min must be between 0-100'),
  body('scoreRange.max').isInt({ min: 0, max: 100 }).withMessage('Score range max must be between 0-100'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const module = await Module.create(req.body);

    try {
      const usersToUpdate = await UserProgress.find({
        hasCompletedPreAssessment: true,
        preAssessmentScore: {
          $gte: req.body.scoreRange.min,
          $lte: req.body.scoreRange.max
        }
      });

      for (const user of usersToUpdate) {
        if (!user.accessibleModules.includes(module._id.toString())) {
          user.accessibleModules.push(module._id.toString());
          await user.save();
        }
      }
    } catch (error) {
      console.error('Error updating user accessible modules:', error);
    }

    await writeAuditLog(req, {
      action: 'CREATE_MODULE',
      entityType: 'Module',
      entityId: String(module._id),
      message: `[Admin] Created module ${module.moduleId}: ${module.title}`,
      before: null,
      after: {
        _id: String(module._id),
        moduleId: module.moduleId,
        title: module.title,
        tier: module.tier,
        scoreRange: module.scoreRange,
        isActive: module.isActive,
        content: module.content,
      },
    });
    sendResponse(res, 201, true, 'Module created successfully', { module });
  })
);

router.put('/:id',
  authenticateToken,
  requireAdmin,
  param('id').isMongoId().withMessage('Invalid module ID'),
  body('tier').optional().isIn(['Tier 1', 'Tier 2']).withMessage('Tier must be Tier 1 or Tier 2'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const beforeDoc = await Module.findById(req.params.id);
    if (!beforeDoc) {
      throw new AppError('Module not found', 404);
    }

    const module = await Module.findByIdAndUpdate(req.params.id, req.body, { new: true });

    await writeAuditLog(req, {
      action: 'UPDATE_MODULE',
      entityType: 'Module',
      entityId: String(module._id),
      message: `[Admin] Updated module ${module.moduleId}: ${module.title}`,
      before: {
        _id: String(beforeDoc._id),
        moduleId: beforeDoc.moduleId,
        title: beforeDoc.title,
        tier: beforeDoc.tier,
        scoreRange: beforeDoc.scoreRange,
        isActive: beforeDoc.isActive,
        content: beforeDoc.content,
      },
      after: {
        _id: String(module._id),
        moduleId: module.moduleId,
        title: module.title,
        tier: module.tier,
        scoreRange: module.scoreRange,
        isActive: module.isActive,
        content: module.content,
      },
    });
    sendResponse(res, 200, true, 'Module updated successfully', { module });
  })
);

router.delete('/:id',
  authenticateToken,
  requireAdmin,
  param('id').isMongoId().withMessage('Invalid module ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const module = await Module.findById(req.params.id);
    if (!module) {
      throw new AppError('Module not found', 404);
    }

    await Module.findByIdAndDelete(req.params.id);

    await writeAuditLog(req, {
      action: 'DELETE_MODULE',
      entityType: 'Module',
      entityId: String(module._id),
      message: `[Admin] Deleted module ${module.moduleId}: ${module.title}`,
      before: {
        _id: String(module._id),
        moduleId: module.moduleId,
        title: module.title,
        tier: module.tier,
        scoreRange: module.scoreRange,
        isActive: module.isActive,
        content: module.content,
      },
      after: null,
    });

    sendResponse(res, 200, true, 'Module deleted successfully');
  })
);

router.post('/:id/complete',
  authenticateToken,
  param('id').isMongoId().withMessage('Invalid module ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const module = await Module.findById(req.params.id);
    if (!module) {
      throw new AppError('Module not found', 404);
    }
    
    
    let userProgress = await UserProgress.findOne({ username: req.user.username });
    if (!userProgress) {
      userProgress = new UserProgress({ username: req.user.username });
    }
    
    
    if (!userProgress.completedModules.includes(req.params.id)) {
      userProgress.completedModules.push(req.params.id);
      userProgress.lastModuleCompletedAt = new Date();
      await userProgress.save();
    }
    
    sendResponse(res, 200, true, 'Module marked as completed', {
      completedModules: userProgress.completedModules.length
    });
  })
);


router.get('/:id/progress/:username',
  authenticateToken,
  param('id').isMongoId().withMessage('Invalid module ID'),
  param('username').trim().notEmpty().withMessage('Username is required'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    if (req.user.role !== 'admin' && req.user.username !== req.params.username) {
      throw new AppError('Access denied', 403);
    }
    
    const userProgress = await UserProgress.findOne({ username: req.params.username });
    const moduleId = req.params.id;
    
    const progress = {
      hasAccess: userProgress ? userProgress.accessibleModules.includes(moduleId) : false,
      isCompleted: userProgress ? userProgress.completedModules.includes(moduleId) : false,
      completedAt: null
    };
    
    sendResponse(res, 200, true, 'Module progress retrieved', { progress });
  })
);


router.post('/:moduleId/submodules',
  authenticateToken,
  requireAdmin,
  param('moduleId').isMongoId().withMessage('Invalid module ID'),
  body('subModuleId').trim().notEmpty().withMessage('Sub-module ID is required'),
  body('title').trim().notEmpty().withMessage('Title is required'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const module = await Module.findById(req.params.moduleId);
    if (!module) {
      throw new AppError('Module not found', 404);
    }
    
    
    const existingSubModule = module.subModules.find(sm => sm.subModuleId === req.body.subModuleId);
    if (existingSubModule) {
      throw new AppError('Sub-module ID already exists', 400);
    }
    
    module.subModules.push(req.body);
    await module.save();
    
    sendResponse(res, 201, true, 'Sub-module added successfully', { module });
  })
);


router.get('/checkpoint-quiz/:checkpointNumber',
  authenticateToken,
  param('checkpointNumber').isInt({ min: 1 }).withMessage('Invalid checkpoint number'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const checkpointNumber = parseInt(req.params.checkpointNumber);
    const module = await Module.findOne({
      isActive: true,
      'checkpointQuiz.isCheckpointQuiz': true,
      'checkpointQuiz.checkpointNumber': checkpointNumber
    });
    
    if (!module) {
      throw new AppError('Checkpoint quiz not found', 404);
    }
    
    
    const userProgress = await UserProgress.findOne({ username: req.user.username });
    if (!userProgress) {
      throw new AppError('User progress not found', 404);
    }
    
    const quiz = module.checkpointQuiz;
    const completedRequiredModules = quiz.requiredModuleIds.filter(moduleId =>
      userProgress.completedModules.includes(moduleId)
    );
    
    const canTake = completedRequiredModules.length >= quiz.requiredModulesCount;
    const alreadyCompleted = userProgress.completedCheckpointQuizzes.some(
      q => q.checkpointNumber === checkpointNumber && q.passed
    );
    
    if (!canTake && !alreadyCompleted) {
      throw new AppError('Requirements not met for this checkpoint quiz', 403);
    }
    
    
    const quizData = {
      _id: module._id,
      checkpointNumber,
      title: module.title,
      description: module.description,
      questions: quiz.questions.map(q => ({
        id: q.id,
        question: q.question,
        options: q.options
      })),
      passingScore: quiz.passingScore,
      timeLimit: quiz.timeLimit
    };
    
    sendResponse(res, 200, true, 'Checkpoint quiz retrieved', { quiz: quizData });
  })
);

module.exports = router;
