const express = require('express');
const { body, validationResult } = require('express-validator');
const rateLimit = require('express-rate-limit');
const User = require('../models/User');
const UserProgress = require('../models/UserProgress');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const { generateTokens, verifyRefreshToken } = require('../utils/tokenUtils');
const { authenticateToken } = require('../middleware/auth');
const { recomputeAssignmentsForUserProgress } = require('../utils/moduleAssignment');
const config = require('../config/config');

const router = express.Router();


const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, 
  max: config.isProduction ? 20 : 1000,
  message: {
    success: false,
    message: 'Too many authentication attempts, please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

const signupLimiter = rateLimit({
  windowMs: 1 * 60 * 1000,
  max: config.isProduction ? 10 : 1000,
  message: {
    message: 'Too many signup attempts. Please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,
});

const resetLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, 
  max: config.isProduction ? 5 : 1000,
  message: {
    success: false,
    message: 'Too many password reset attempts. Please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,
});


const nameRegex = /^[A-Za-z\s]+$/;
const initialOrNameRegex = /^[A-Za-z]+(?:\s+[A-Za-z]+)*$/; 

const signupValidation = [
  body('username')
    .trim()
    .isLength({ min: 3, max: 30 })
    .withMessage('Username must be between 3 and 30 characters')
    .matches(/^[a-zA-Z0-9_]+$/)
    .withMessage('Username can only contain letters, numbers, and underscores'),

  body('email')
    .trim()
    .notEmpty().withMessage('Email is required')
    .matches(/^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|school\.edu)$/i)
    .withMessage('Please enter a valid email (gmail.com, yahoo.com, or school.edu).'),
  
  body('password')
    .isLength({ min: 8 })
    .withMessage('Password must be at least 8 characters long'),
  
  body('firstName')
    .trim()
    .notEmpty().withMessage('First name is required')
    .matches(nameRegex).withMessage('First name can only contain letters and spaces'),

  body('middleName')
    .optional({ nullable: true })
    .trim()
    .matches(initialOrNameRegex).withMessage('Middle name can only contain letters and spaces'),

  body('lastName')
    .trim()
    .notEmpty().withMessage('Last name is required')
    .matches(nameRegex).withMessage('Last name can only contain letters and spaces'),
  
  body('age')
    .isInt({ min: 1, max: 65 })
    .withMessage('Age must be a number between 1 and 65'),
  
  body('address')
    .trim()
    .isLength({ min: 5, max: 200 })
    .withMessage('Address must be between 5 and 200 characters'),
  
  body('gender')
    .isIn(['Male', 'Female', 'Other'])
    .withMessage('Gender must be Male, Female, or Other'),
  
  body('role')
    .optional()
    .isIn(['user', 'admin'])
    .withMessage('Role must be user or admin')
];

const loginValidation = [
  body('username')
    .trim()
    .notEmpty()
    .withMessage('Username is required'),
  
  body('password')
    .notEmpty()
    .withMessage('Password is required')
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

async function generateUniqueStudentId() {
  let studentId;
  let exists = true;
  while (exists) {
    const year = new Date().getFullYear();
    const random = Math.floor(1000 + Math.random() * 9000);
    studentId = `STD-${year}-${random}`;
    exists = !!(await User.findOne({ studentId }));
  }
  return studentId;
}

router.post('/signup', 
  signupLimiter,
  signupValidation,
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { username, email, password, role, firstName, middleName, lastName, age, address, gender } = req.body;

    const normalized = {
      username: (username || '').trim().toLowerCase(),
      email: (email || '').trim().toLowerCase(),
      password: (password || '').trim(),
      firstName: (firstName || '').trim(),
      middleName: (middleName || '').trim(),
      lastName: (lastName || '').trim(),
      address: (address || '').trim(),
      gender: (gender || '').trim(),
      role: role
    };

    if (!normalized.username || !normalized.email || !normalized.password || !normalized.firstName || !normalized.lastName || !String(age).trim() || !normalized.address || !normalized.gender) {
      return res.status(400).json({ message: 'All required fields must be provided and cannot be blank.' });
    }

    const usernameRegex = new RegExp(`^${normalized.username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`, 'i');
    const existingUser = await User.findOne({ username: usernameRegex });
    if (existingUser) {
      return res.status(409).json({ message: 'Username already exists.' });
    }

    const existingEmail = await User.findOne({ email: normalized.email });
    if (existingEmail) {
      return res.status(409).json({ message: 'Email already in use.' });
    }

    const studentId = await generateUniqueStudentId();
    const newUser = new User({
      username: normalized.username,
      email: normalized.email,
      password: normalized.password,
      role: role || 'user',
      firstName: normalized.firstName,
      middleName: normalized.middleName || undefined,
      lastName: normalized.lastName,
      studentId,
      age,
      address: normalized.address,
      gender: normalized.gender
    });

    try {
      await newUser.save();
    } catch (err) {
      if (err && err.code === 11000) {
        if (err.keyPattern && err.keyPattern.username) {
          return res.status(409).json({ message: 'Username already exists.' });
        }
        if (err.keyPattern && err.keyPattern.email) {
          return res.status(409).json({ message: 'Email already in use.' });
        }
        if (err.keyPattern && err.keyPattern.studentId) {
          return res.status(409).json({ message: 'Student ID conflict' });
        }
      }
      throw err;
    }

    sendResponse(res, 201, true, 'User registered successfully', {
      user: {
        id: newUser._id,
        username: newUser.username,
        email: newUser.email,
        role: newUser.role,
        studentId: newUser.studentId,
        fullName: newUser.fullName,
      }
    });
  })
);


router.post('/login',
  authLimiter,
  loginValidation,
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { username, password } = req.body;

    const user = await User.findOne({ username: new RegExp(`^${String(username).trim()}$`, 'i') })
      .select('+password')
      .setOptions({ skipFilter: true });
    if (!user) {
      throw new AppError('Invalid credentials', 401);
    }

    if (user.isDeleted) {
      throw new AppError('Account has been deactivated. Please contact an administrator.', 403);
    }

    const isPasswordValid = await user.comparePassword(password);
    if (!isPasswordValid) {
      throw new AppError('Invalid credentials', 401);
    }

    const now = new Date();
    user.isOnline = true;
    user.lastLogin = now;
    user.lastActive = now;
    await user.save();

    const tokens = generateTokens(user._id);

    sendResponse(res, 200, true, `Welcome ${user.role} ${user.username}`, {
      user: {
        id: user._id,
        username: user.username,
        role: user.role,
        fullName: user.fullName,
        studentId: user.studentId,
        isOnline: user.isOnline,
        lastLogin: user.lastLogin,
      },
      ...tokens
    });
  })
);


router.post('/refresh',
  catchAsync(async (req, res) => {
    const { refreshToken } = req.body;

    if (!refreshToken) {
      throw new AppError('Refresh token is required', 401);
    }

    try {
      const decoded = verifyRefreshToken(refreshToken);
      
      
      const user = await User.findById(decoded.id).setOptions({ skipFilter: true });
      if (!user) {
        throw new AppError('User not found', 401);
      }

      
      if (user.isDeleted) {
        throw new AppError('Account has been deactivated', 403);
      }

      
      
      
      const tokens = generateTokens(user._id);

      sendResponse(res, 200, true, 'Tokens refreshed successfully', tokens);
    } catch (error) {
      if (error.name === 'JsonWebTokenError' || error.name === 'TokenExpiredError') {
        throw new AppError('Invalid or expired refresh token', 401);
      }
      throw error;
    }
  })
);


router.get('/me',
  authenticateToken,
  catchAsync(async (req, res) => {
    try {
      const username = req.user.username;
      let progress = await UserProgress.findOne({ username });
      if (!progress) progress = new UserProgress({ username });

      const result = await recomputeAssignmentsForUserProgress(progress);
      await progress.save();

      const moduleScores = result && result.moduleScores ? result.moduleScores : (progress.moduleScores || {});
      const overallPct = result && typeof result.overallPct === 'number'
        ? result.overallPct
        : (progress.overallModuleScore || 0);

      return sendResponse(res, 200, true, 'User data retrieved successfully', {
        user: req.user,
        userProgress: {
          ...progress.toObject(),
          moduleScores,
          overall: overallPct,
        }
      });
    } catch (e) {
      return sendResponse(res, 200, true, 'User data retrieved successfully', {
        user: req.user
      });
    }
  })
);


router.post('/logout',
  authenticateToken,
  catchAsync(async (req, res) => {

    try {
      const user = await User.findOne({ _id: req.user.id }).setOptions({ skipFilter: true });
      if (user) {
        user.isOnline = false;
        await user.save();
      }
    } catch {}
    sendResponse(res, 200, true, 'Logged out successfully');
  })
);

router.post('/reset-password',
  resetLimiter,
  [
    body('username').trim().notEmpty().withMessage('Username is required'),
    body('email')
      .trim()
      .notEmpty().withMessage('Email is required')
      .matches(/^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|school\.edu)$/i)
      .withMessage('Please enter a valid email (gmail.com, yahoo.com, or school.edu).'),
    body('newPassword')
      .isLength({ min: 8 })
      .withMessage('Password must be at least 8 characters long')
  ],
  handleValidationErrors,
  catchAsync(async (req, res) => {
    const { username, email, newPassword } = req.body;

    const user = await User.findOne({
      username: new RegExp(`^${String(username).trim()}$`, 'i'),
      email: String(email).trim().toLowerCase()
    }).setOptions({ skipFilter: true });

    if (!user) {

      throw new AppError('User not found', 404);
    }

    user.password = String(newPassword);
    await user.save();

    return sendResponse(res, 200, true, 'Password reset successful');
  })
);

module.exports = router;

