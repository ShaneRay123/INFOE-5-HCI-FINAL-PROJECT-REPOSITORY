const express = require('express');
const { body, param, validationResult } = require('express-validator');
const UserProgress = require('../models/UserProgress');
const Module = require('../models/Module');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const { authenticateToken, requireAdmin } = require('../middleware/auth');

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


router.get('/me',
  authenticateToken,
  catchAsync(async (req, res) => {
    let progress = await UserProgress.findOne({ username: req.user.username });
    if (!progress) {
      
      progress = new UserProgress({ username: req.user.username });
      await progress.save();
    }
    
    sendResponse(res, 200, true, 'Progress retrieved successfully', { progress });
  })
);


router.get('/:username',
  authenticateToken,
  param('username').trim().notEmpty().withMessage('Username is required'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    
    if (req.user.role !== 'admin' && req.user.username !== req.params.username) {
      throw new AppError('Access denied', 403);
    }
    
    let progress = await UserProgress.findOne({ username: req.params.username });
    if (!progress) {
      progress = new UserProgress({ username: req.params.username });
      await progress.save();
    }
    sendResponse(res, 200, true, 'Progress retrieved successfully', { progress });
  })
);

router.post('/exam',
  authenticateToken,
  body('score').isInt({ min: 0, max: 100 }).withMessage('Score must be between 0 and 100'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { score } = req.body;

    let progress = await UserProgress.findOne({ username: req.user.username });
    if (!progress) {
      progress = new UserProgress({ username: req.user.username });
    }

    progress.hasCompletedPreAssessment = true;
    progress.preAssessmentScore = score;
    progress.preAssessmentCompletedAt = new Date();

    const modules = await Module.find({
      isActive: true,
      'scoreRange.min': { $lte: score },
      'scoreRange.max': { $gte: score }
    });

    progress.accessibleModules = modules.map(m => m._id);
    progress.currentCheckpointGroup = 1;

    await progress.save();
    sendResponse(res, 200, true, 'Exam completed', { progress });
  })
);

router.post('/pre-assessment',
  authenticateToken,
  body('score').isInt({ min: 0, max: 100 }).withMessage('Score must be between 0 and 100'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    console.warn('[DEPRECATED] POST /api/progress/pre-assessment called. Use /api/progress/exam instead.');
    return res.redirect(307, '/api/progress/exam');
  })
);

router.post('/post-assessment',
  authenticateToken,
  body('score').isInt({ min: 0, max: 100 }).withMessage('Score must be between 0 and 100'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    console.warn('[DEPRECATED] POST /api/progress/post-assessment called. This is historical only.');
    const { score } = req.body;
    let progress = await UserProgress.findOne({ username: req.user.username });
    if (!progress) {
      throw new AppError('User progress not found. Complete pre-assessment first.', 404);
    }
    
    progress.hasCompletedPostAssessment = true;
    progress.postAssessmentScore = score;
    progress.postAssessmentCompletedAt = new Date();
    
    await progress.save();
    sendResponse(res, 200, true, 'Post-assessment completed', { progress });
  })
);


router.post('/checkpoint-quiz/:checkpointNumber/submit',
  authenticateToken,
  param('checkpointNumber').isInt({ min: 1 }).withMessage('Invalid checkpoint number'),
  body('answers').isObject().withMessage('Answers must be an object'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { answers } = req.body;
    const checkpointNumber = parseInt(req.params.checkpointNumber);
    
    
    const module = await Module.findOne({
      isActive: true,
      'checkpointQuiz.isCheckpointQuiz': true,
      'checkpointQuiz.checkpointNumber': checkpointNumber
    });
    
    if (!module) {
      throw new AppError('Checkpoint quiz not found', 404);
    }
    
    const quiz = module.checkpointQuiz;
    
    
    const userProgress = await UserProgress.findOne({ username: req.user.username });
    if (!userProgress) {
      throw new AppError('User progress not found', 404);
    }
    
    
    let correctAnswers = 0;
    const totalQuestions = quiz.questions.length;
    
    quiz.questions.forEach(question => {
      const userAnswer = parseInt(answers[question.id]);
      let correctAnswerIndex;
      
      
      if (typeof question.correctAnswer === 'string' && isNaN(question.correctAnswer)) {
        correctAnswerIndex = question.options.indexOf(question.correctAnswer);
      } else {
        correctAnswerIndex = parseInt(question.correctAnswer);
      }
      
      if (userAnswer === correctAnswerIndex && correctAnswerIndex !== -1) {
        correctAnswers++;
      }
    });
    
    const percentage = Math.round((correctAnswers / totalQuestions) * 100);
    const passed = percentage >= quiz.passingScore;
    
    
    userProgress.completedCheckpointQuizzes = userProgress.completedCheckpointQuizzes.filter(
      q => q.checkpointNumber !== checkpointNumber
    );
    
    
    userProgress.completedCheckpointQuizzes.push({
      moduleId: module._id.toString(),
      checkpointNumber,
      score: percentage,
      passed,
      completedAt: new Date()
    });
    
    
    if (passed) {
      const allModules = await Module.find({
        isActive: true,
        'scoreRange.min': { $lte: userProgress.preAssessmentScore || 0 },
        'scoreRange.max': { $gte: userProgress.preAssessmentScore || 100 }
      });
      
      
      allModules.forEach(module => {
        if (!userProgress.accessibleModules.includes(module._id.toString())) {
          userProgress.accessibleModules.push(module._id.toString());
        }
      });
    }
    
    await userProgress.save();
    
    sendResponse(res, 200, true, 
      passed ? `Quiz passed! Checkpoint Group ${checkpointNumber + 1} modules unlocked.` : 'Quiz failed. Please try again.',
      {
        score: percentage,
        passed,
        correctAnswers,
        totalQuestions,
        nextCheckpointUnlocked: passed
      }
    );
  })
);


router.get('/',
  authenticateToken,
  requireAdmin,
  catchAsync(async (req, res) => {
    const { page = 1, limit = 10 } = req.query;
    
    const options = {
      page: parseInt(page),
      limit: parseInt(limit)
    };
    
    const progressRecords = await UserProgress.find()
      .sort({ updatedAt: -1 })
      .limit(options.limit * 1)
      .skip((options.page - 1) * options.limit);
    
    const total = await UserProgress.countDocuments();
    
    sendResponse(res, 200, true, 'All progress retrieved successfully', {
      progressRecords,
      pagination: {
        currentPage: options.page,
        totalPages: Math.ceil(total / options.limit),
        totalRecords: total,
        hasNextPage: options.page < Math.ceil(total / options.limit),
        hasPrevPage: options.page > 1
      }
    });
  })
);


router.delete('/:username',
  authenticateToken,
  requireAdmin,
  param('username').trim().notEmpty().withMessage('Username is required'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const deleted = await UserProgress.findOneAndDelete({ username: req.params.username });
    if (!deleted) {
      throw new AppError('User progress not found', 404);
    }
    
    sendResponse(res, 200, true, 'User progress reset successfully');
  })
);

module.exports = router;
