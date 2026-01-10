const express = require('express');
const { body, param, validationResult } = require('express-validator');
const ExamSession = require('../models/ExamSession');
const Exam = require('../models/Exam');
const Result = require('../models/Result');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const { authenticateToken } = require('../middleware/auth');

const router = express.Router();


router.use(authenticateToken);


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


router.get('/active',
  catchAsync(async (req, res) => {
    const username = req.user.username;

    const session = await ExamSession.findOne({ username, isActive: true }).sort({ lastSavedAt: -1 });
    if (!session) {
      return sendResponse(res, 200, true, 'No active session found', { session: null });
    }

    const sessionAge = Date.now() - session.lastSavedAt.getTime();
    const maxSessionAge = 24 * 60 * 60 * 1000;
    if (sessionAge > maxSessionAge) {
      await session.complete();
      return sendResponse(res, 200, true, 'Session expired', { session: null });
    }

    sendResponse(res, 200, true, 'Active session found', { session });
  })
);


router.get('/active/:examType',
  param('examType').isIn(['exam', 'pre-assessment', 'post-assessment']).withMessage('Invalid exam type'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { examType } = req.params;
    const username = req.user.username;

    const session = await ExamSession.findActiveSession(username, examType);
    
    if (!session) {
      return sendResponse(res, 200, true, 'No active session found', { session: null });
    }

    
    const sessionAge = Date.now() - session.lastSavedAt.getTime();
    const maxSessionAge = 24 * 60 * 60 * 1000; 
    
    if (sessionAge > maxSessionAge) {
      await session.complete();
      return sendResponse(res, 200, true, 'Session expired', { session: null });
    }

    sendResponse(res, 200, true, 'Active session found', { session });
  })
);


router.post('/start',
  body('examId').isMongoId().withMessage('Valid exam ID is required'),
  body('examType').optional().isString(),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { examId, examType } = req.body;
    const username = req.user.username;

    const effectiveExamType = examType || 'exam';

    const existingSession = await ExamSession.findActiveSession(username, effectiveExamType);
    if (existingSession) {
      return sendResponse(res, 200, true, 'Active session already exists', { session: existingSession });
    }

    
    const exam = await Exam.findById(examId);
    if (!exam) {
      throw new AppError('Exam not found', 404);
    }

    if (!exam.isActive) {
      throw new AppError('Exam is not active', 400);
    }

    
    const session = await ExamSession.createSession(username, exam, effectiveExamType);
    
    sendResponse(res, 201, true, 'Exam session started', { session });
  })
);


const progressValidators = [
  param('sessionId').isMongoId().withMessage('Valid session ID is required'),
  body('currentQuestion').isInt({ min: 0 }).withMessage('Current question must be a non-negative integer'),
  body('answers').isObject().withMessage('Answers must be an object'),
  body('timeLeft').isInt({ min: 0 }).withMessage('Time left must be a non-negative integer'),
  handleValidationErrors,
];

const progressHandler = catchAsync(async (req, res) => {
  const { sessionId } = req.params;
  const { currentQuestion, answers, timeLeft } = req.body;
  const username = req.user.username;

  const session = await ExamSession.findById(sessionId);
  if (!session) {
    throw new AppError('Session not found', 404);
  }
  if (session.username !== username) {
    throw new AppError('Session does not belong to this user', 403);
  }
  if (!session.isActive) {
    throw new AppError('Session is not active', 400);
  }

  if (currentQuestion >= session.totalQuestions) {
    throw new AppError('Invalid question number', 400);
  }

  await session.updateProgress(currentQuestion, answers, timeLeft);
  sendResponse(res, 200, true, 'Progress saved', { session });
});

router.put('/:sessionId/progress', progressValidators, progressHandler);
router.post('/:sessionId/progress', progressValidators, progressHandler);


router.post('/:sessionId/complete',
  param('sessionId').isMongoId().withMessage('Valid session ID is required'),
  body('finalAnswers').isObject().withMessage('Final answers must be an object'),
  body('timeSpent').isInt({ min: 0 }).withMessage('Time spent must be a non-negative integer'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { sessionId } = req.params;
    const { finalAnswers, timeSpent } = req.body;
    const username = req.user.username;

    const session = await ExamSession.findOne({ 
      _id: sessionId, 
      username, 
      isActive: true 
    }).populate('examId');

    if (!session) {
      throw new AppError('Active session not found', 404);
    }
    
    session.answers = new Map(Object.entries(finalAnswers));
    session.timeSpent = timeSpent;
    session.timeLeft = 0;
    await session.complete();

    const exam = session.examId;
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
      const userAnswerIndex = toAnswerIndex(finalAnswers ? finalAnswers[question.id] : undefined, options);

      if (correctIndex >= 0 && userAnswerIndex === correctIndex) {
        correctAnswers++;
      }
    });

    const percentage = Math.round((correctAnswers / totalQuestions) * 100);
    const passingScore = exam.passingScore || 70;
    const passed = percentage >= passingScore;

    await Result.create({
      username: username,
      examId: exam._id,
      examTitle: exam.title,
      examSubject: 'exam',
      examDifficulty: 'Intermediate',
      totalQuestions: totalQuestions,
      correctAnswers: correctAnswers,
      percentage: percentage,
      passed: passed,
      timeSpent: timeSpent,
      passingScore: passingScore,
      answers: finalAnswers
    });
    
    sendResponse(res, 200, true, 'Exam session completed', { 
      session,
      exam: exam,
      result: {
        score: percentage,
        passed,
        correctAnswers,
        totalQuestions
      }
    });
  })
);


router.delete('/:sessionId',
  param('sessionId').isMongoId().withMessage('Valid session ID is required'),
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { sessionId } = req.params;
    const username = req.user.username;

    const session = await ExamSession.findOne({ 
      _id: sessionId, 
      username, 
      isActive: true 
    });

    if (!session) {
      throw new AppError('Active session not found', 404);
    }

    await session.complete();
    
    sendResponse(res, 200, true, 'Exam session cancelled');
  })
);


router.get('/my-sessions',
  catchAsync(async (req, res) => {
    const username = req.user.username;
    const { page = 1, limit = 10, examType } = req.query;

    const filter = { username };
    if (examType) {
      filter.examType = examType;
    }

    const options = {
      page: parseInt(page),
      limit: parseInt(limit),
      sort: { startedAt: -1 }
    };

    const sessions = await ExamSession.find(filter)
      .populate('examId', 'title timeLimit')
      .sort(options.sort)
      .limit(options.limit * 1)
      .skip((options.page - 1) * options.limit);

    const total = await ExamSession.countDocuments(filter);

    sendResponse(res, 200, true, 'Sessions retrieved successfully', {
      sessions,
      pagination: {
        currentPage: options.page,
        totalPages: Math.ceil(total / options.limit),
        totalSessions: total,
        hasNextPage: options.page < Math.ceil(total / options.limit),
        hasPrevPage: options.page > 1
      }
    });
  })
);


router.post('/cleanup',
  catchAsync(async (req, res) => {
    
    if (req.user.role !== 'admin') {
      throw new AppError('Access denied', 403);
    }

    const result = await ExamSession.cleanupOldSessions();
    
    sendResponse(res, 200, true, `Cleaned up ${result.deletedCount} old sessions`);
  })
);

module.exports = router;
