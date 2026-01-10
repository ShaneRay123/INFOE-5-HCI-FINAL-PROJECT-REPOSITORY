const express = require('express');
const { body, param, validationResult } = require('express-validator');
const Exam = require('../models/Exam');
const Result = require('../models/Result');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const { authenticateToken, requireAdmin } = require('../middleware/auth');
const { writeAuditLog } = require('../utils/audit');

const router = express.Router();


const examValidation = [
  body('title').trim().notEmpty().withMessage('Title is required'),
  body('timeLimit').isInt({ min: 1, max: 180 }).withMessage('Time limit must be between 1 and 180 minutes'),
  body('passingScore').optional().isInt({ min: 0, max: 100 }).withMessage('Passing score must be between 0 and 100'),
  body('questions').optional().isArray().withMessage('Questions must be an array'),
  body('questions.*.question').optional().trim().notEmpty().withMessage('Question text is required'),
  body('questions.*.options').optional().isArray({ min: 4, max: 4 }).withMessage('Each question must have exactly 4 options'),
  body('questions.*.correctAnswer').optional().trim().notEmpty().withMessage('Correct answer is required')
];

function normalizeQuestions(questions) {
  if (!Array.isArray(questions)) return [];
  return questions.map((q) => ({
    id: String(q.id),
    question: String(q.question || ''),
    options: Array.isArray(q.options) ? q.options.map(o => String(o || '')) : [],
    correctAnswer: String(q.correctAnswer || ''),
    explanation: q.explanation ? String(q.explanation) : "",
    moduleTitle: String(q.moduleTitle || ""),
    moduleType: String(q.moduleType || q.moduleTitle || ""),
    tier: String(q.tier || ""),
  }));
}


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
  requireAdmin,
  catchAsync(async (req, res) => {
    const exams = await Exam.find().sort({ createdAt: -1 });
    sendResponse(res, 200, true, 'Exams retrieved successfully', exams);
  })
);


router.get('/active',
  authenticateToken,
  catchAsync(async (req, res) => {
    const exam = await Exam.findOne({ isActive: true }).sort({ createdAt: -1 });

    if (!exam) {
      throw new AppError('No active exam found', 404);
    }

    if (req.user.role !== 'admin') {
      exam.questions = exam.questions.map(q => ({
        id: q.id,
        question: q.question,
        options: q.options,
        moduleTitle: q.moduleTitle,
        moduleType: q.moduleType,
        tier: q.tier,
      }));
    }

    sendResponse(res, 200, true, 'Exam retrieved successfully', exam);
  })
);


router.get('/active/:type',
  authenticateToken,
  param('type').isIn(['pre-assessment', 'post-assessment']).withMessage('Type must be pre-assessment or post-assessment'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    console.warn(`[DEPRECATED] GET /api/exams/active/${req.params.type} called. Use /api/exams/active instead.`);

    const exam = await Exam.findOne({ isActive: true }).sort({ createdAt: -1 });
    if (!exam) {
      throw new AppError('No active exam found', 404);
    }

    if (req.user.role !== 'admin') {
      exam.questions = exam.questions.map(q => ({
        id: q.id,
        question: q.question,
        options: q.options,
        moduleTitle: q.moduleTitle,
        moduleType: q.moduleType,
        tier: q.tier,
      }));
    }

    sendResponse(res, 200, true, 'Exam retrieved successfully', exam);
  })
);


router.get('/:id',
  authenticateToken,
  param('id').isMongoId().withMessage('Invalid exam ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const exam = await Exam.findById(req.params.id);
    if (!exam) {
      throw new AppError('Exam not found', 404);
    }
    
    
    if (req.user.role !== 'admin') {
      exam.questions = exam.questions.map(q => ({
        id: q.id,
        question: q.question,
        options: q.options,
        moduleTitle: q.moduleTitle
      }));
    }
    
    sendResponse(res, 200, true, 'Exam retrieved successfully', { exam });
  })
);


router.post('/',
  authenticateToken,
  requireAdmin,
  examValidation,
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const questions = normalizeQuestions(req.body.questions);

    const exam = await Exam.create({
      ...req.body,
      questions,
    });

    await writeAuditLog(req, {
      action: 'CREATE_EXAM',
      entityType: 'Exam',
      entityId: String(exam._id),
      message: `[Admin] Created exam: ${exam.title}`,
      before: null,
      after: {
        _id: String(exam._id),
        title: exam.title,
        timeLimit: exam.timeLimit,
        passingScore: exam.passingScore,
        isActive: exam.isActive,
        questionsCount: Array.isArray(exam.questions) ? exam.questions.length : 0,
      },
    });
    sendResponse(res, 201, true, 'Exam created successfully', { exam });
  })
);


router.put('/:id',
  authenticateToken,
  requireAdmin,
  param('id').isMongoId().withMessage('Invalid exam ID'),
  examValidation,
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const existing = await Exam.findById(req.params.id);
    if (!existing) {
      throw new AppError('Exam not found', 404);
    }

    const before = {
      _id: String(existing._id),
      title: existing.title,
      timeLimit: existing.timeLimit,
      passingScore: existing.passingScore,
      isActive: existing.isActive,
      questionsCount: Array.isArray(existing.questions) ? existing.questions.length : 0,
    };

    const { title, timeLimit, passingScore, questions, isActive } = req.body;

    if (typeof title !== 'undefined') existing.title = title;
    if (typeof timeLimit !== 'undefined') existing.timeLimit = timeLimit;
    if (typeof passingScore !== 'undefined') existing.passingScore = passingScore;

    if (Array.isArray(questions)) {

      const normalizedQuestions = normalizeQuestions(questions);
      existing.set('questions', normalizedQuestions);
      existing.markModified('questions');
    }

    if (typeof isActive !== 'undefined') existing.isActive = !!isActive;
    
    const exam = await existing.save({ validateBeforeSave: true });

    const after = {
      _id: String(exam._id),
      title: exam.title,
      timeLimit: exam.timeLimit,
      passingScore: exam.passingScore,
      isActive: exam.isActive,
      questionsCount: Array.isArray(exam.questions) ? exam.questions.length : 0,
    };

    await writeAuditLog(req, {
      action: 'UPDATE_EXAM',
      entityType: 'Exam',
      entityId: String(exam._id),
      message: `[Admin] Updated exam: ${exam.title}`,
      before,
      after,
    });
    sendResponse(res, 200, true, 'Exam updated successfully', { exam });
  })
);


router.delete('/:id',
  authenticateToken,
  requireAdmin,
  param('id').isMongoId().withMessage('Invalid exam ID'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const exam = await Exam.findById(req.params.id);
    if (!exam) {
      throw new AppError('Exam not found', 404);
    }

    await Exam.findByIdAndDelete(req.params.id);

    await writeAuditLog(req, {
      action: 'DELETE_EXAM',
      entityType: 'Exam',
      entityId: String(exam._id),
      message: `[Admin] Deleted exam: ${exam.title}`,
      before: {
        _id: String(exam._id),
        title: exam.title,
        timeLimit: exam.timeLimit,
        passingScore: exam.passingScore,
        isActive: exam.isActive,
        questionsCount: Array.isArray(exam.questions) ? exam.questions.length : 0,
      },
      after: null,
    });
    sendResponse(res, 200, true, 'Exam deleted successfully');
  })
);


router.post('/:id/submit',
  authenticateToken,
  param('id').isMongoId().withMessage('Invalid exam ID'),
  body('answers').isObject().withMessage('Answers must be an object'),
  body('timeSpent').optional().isInt({ min: 0 }).withMessage('Time spent must be a positive number'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { answers, timeSpent = 0 } = req.body;
    
    const exam = await Exam.findById(req.params.id);
    if (!exam) {
      throw new AppError('Exam not found', 404);
    }
    
    
    let correctAnswers = 0;
    const totalQuestions = exam.questions.length;

    const toAnswerIndex = (val, options) => {
      const letterMap = { A: 0, B: 1, C: 2, D: 3, E: 4, F: 5 };
      if (typeof val === 'number' && !Number.isNaN(val)) return val;
      if (typeof val === 'string') {
        const s = val.trim();
        if (!s) return -1;

        const opts = (options || []).map(o => String(o));

        const textIdx = opts.findIndex(opt => opt.trim().toLowerCase() === s.toLowerCase());
        if (textIdx !== -1) return textIdx;

        const upper = s.toUpperCase();
        if (letterMap[upper] !== undefined) return letterMap[upper];

        const asNum = Number(s);
        if (!Number.isNaN(asNum)) return asNum;
        return -1;
      }
      return -1;
    };
    
    exam.questions.forEach(question => {
      const options = (question.options || []).map(o => String(o));
      const correctIndex = toAnswerIndex(question.correctAnswer, options);
      const userAnswerIndex = toAnswerIndex(answers ? answers[question.id] : undefined, options);
      if (correctIndex >= 0 && userAnswerIndex === correctIndex) {
        correctAnswers++;
      }
    });
    
    const percentage = Math.round((correctAnswers / totalQuestions) * 100);
    const passingScore = exam.passingScore || 70;
    const passed = percentage >= passingScore;
    
    
    const result = await Result.create({
      username: req.user.username,
      examId: exam._id,
      examTitle: exam.title,
      examSubject: 'exam',
      examDifficulty: 'Intermediate', 
      totalQuestions,
      correctAnswers,
      percentage,
      passed,
      timeSpent,
      passingScore,
      answers
    });
    
    sendResponse(res, 201, true, 'Exam submitted successfully', {
      result: {
        score: percentage,
        passed,
        correctAnswers,
        totalQuestions,
        timeSpent
      }
    });
  })
);

module.exports = router;
