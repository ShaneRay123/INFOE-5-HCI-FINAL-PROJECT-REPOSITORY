const express = require('express');
const mongoose = require('mongoose');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const multer = require('multer');
const { GridFSBucket } = require('mongodb');


const config = require('./config/config');


const { globalErrorHandler } = require('./middleware/errorHandler');


const authRoutes = require('./routes/auth');
const googleAuthRoutes = require('./routes/googleAuth');
const adminRoutes = require('./routes/admin');
const examRoutes = require('./routes/exams');
const moduleRoutes = require('./routes/modules');
const progressRoutes = require('./routes/progress');
const examSessionRoutes = require('./routes/examSessions');

const app = express();

const allowedOrigins = [
  config.FRONTEND_URL,
  process.env.FRONTEND_URL_2,
  process.env.FRONTEND_URLS,
  'http://localhost:3000',
  'https://localhost:3000'
].filter(Boolean);

function isAllowedOrigin(origin) {
  if (!origin) return true; // non-browser or same-origin
  if (allowedOrigins.some(o => String(o).split(',').map(s => s.trim()).includes(origin))) return true;

  try {
    const u = new URL(origin);
    if (u.hostname.endsWith('.vercel.app')) return true;
  } catch {}
  return false;
}

app.use(require('cors')({
  origin(origin, callback) {
    return callback(null, isAllowedOrigin(origin));
  },
  credentials: true,
  methods: ['GET','POST','PUT','DELETE','OPTIONS'],
  allowedHeaders: ['Content-Type','Authorization']
}));
app.options(/.*/, require('cors')({
  origin(origin, callback) {
    return callback(null, isAllowedOrigin(origin));
  },
  credentials: true,
  methods: ['GET','POST','PUT','DELETE','OPTIONS'],
  allowedHeaders: ['Content-Type','Authorization']
}));

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

if (process.env.VERCEL !== '1') {
  app.use(helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        baseUri: ["'self'"],
        fontSrc: ["'self'", "https:", "data:"],
        formAction: ["'self'"],
        frameAncestors: ["'self'", config.FRONTEND_URL, 'http://localhost:3000'],
        imgSrc: ["'self'", "data:"],
        objectSrc: ["'none'"],
        scriptSrc: ["'self'"],
        scriptSrcAttr: ["'none'"],
        styleSrc: ["'self'", "https:", "'unsafe-inline'"],
        upgradeInsecureRequests: [],
      },
    },
  }));
}


const generalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, 
  max: 1000, 
  message: {
    success: false,
    message: 'Too many requests, please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,

  skip: (req) => req.method === 'OPTIONS' || (config.isDevelopment && (req.ip === '127.0.0.1' || req.ip === '::1')),
});

app.use(generalLimiter);




app.use((req, res, next) => {
  
  if (req.method === 'GET') {
    if (req.path.includes('/api/exams')) {

      res.set('Cache-Control', 'no-cache, no-store, must-revalidate');
    } else if (req.path.includes('/api/modules')) {

      res.set('Cache-Control', 'public, max-age=300');
    } else if (req.path.includes('/file/') || req.path.includes('/api/file/')) {
      res.set('Cache-Control', 'public, max-age=3600');
    } else if (req.path.includes('/api/user-progress') || req.path.includes('/api/results')) {
      res.set('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
  }
  next();
});

if (mongoose.connection.readyState === 0) {
  mongoose.connect(config.MONGO_URI)
    .then(() => console.log("? MongoDB connected"))
    .catch(err => {
      console.error("? MongoDB connection error:", err.message);

      if (process.env.VERCEL !== '1') {
        process.exit(1);
      }
    });
}

const conn = mongoose.connection;
let gfs;
conn.once("open", () => {
  gfs = new GridFSBucket(conn.db, { bucketName: "uploads" });
  console.log("? GridFS ready");
});


app.get('/health', (req, res) => {
  res.status(200).json({
    success: true,
    message: 'Server is running',
    timestamp: new Date().toISOString(),
    environment: config.NODE_ENV
  });
});



app.use('/api/auth', authRoutes);
app.use('/api/auth', googleAuthRoutes); // Google OAuth routes
app.use('/api/admin', adminRoutes);
app.use('/api/exams', examRoutes);
app.use('/api/modules', moduleRoutes);
app.use('/api/progress', progressRoutes);
app.use('/api/exam-sessions', examSessionRoutes);




const User = require('./models/User');
const { generateTokens } = require('./utils/tokenUtils');
const jwt = require('jsonwebtoken');
const configJwt = require('./config/config');
const { catchAsync, sendResponse } = require('./middleware/errorHandler');
const { authenticateToken } = require('./middleware/auth');

const signupLimiter = rateLimit({
  windowMs: 1 * 60 * 1000, // 1 minute
  max: 5, // 5 requests per minute
  message: {
    message: 'Too many signup attempts. Please try again later.'
  },
  standardHeaders: true,
  legacyHeaders: false,
  skip: (req) => req.method === 'OPTIONS' // Skip preflight requests
});

app.post('/create-account', signupLimiter, catchAsync(async (req, res) => {
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
    return res.status(400).json({ 
      message: 'All required fields must be provided and cannot be blank.' 
    });
  }

  const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;
  if (!gmailRegex.test(normalized.email)) {
    return res.status(400).json({ message: 'Please enter a valid Gmail address.' });
  }
  if (normalized.password.length < 8) {
    return res.status(400).json({ message: 'Password must be at least 8 characters long.' });
  }

  const nameRegex = /^[A-Za-z\s]+$/;
  if (!nameRegex.test(normalized.firstName) || !nameRegex.test(normalized.lastName) || (normalized.middleName && !nameRegex.test(normalized.middleName))) {
    return res.status(400).json({ message: 'Names can only contain letters and spaces.' });
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

  async function generateUniqueStudentId() {
    let sid, exists = true;
    while (exists) {
      const year = new Date().getFullYear();
      const random = Math.floor(1000 + Math.random() * 9000);
      sid = `STD-${year}-${random}`;
      exists = !!(await User.findOne({ studentId: sid }));
    }
    return sid;
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

  res.json({
    success: true,
    message: 'User registered successfully',
    role: newUser.role,
    username: newUser.username,
    studentId: newUser.studentId,
  });
}));

app.get('/verify-user', catchAsync(async (req, res) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];
  if (!token) {
    return res.status(401).json({ message: 'No token provided.' });
  }
  try {
    const decoded = jwt.verify(token, configJwt.JWT_SECRET);
    const user = await User.findById(decoded.id).select('username role isApproved firstName middleName lastName studentId').lean();
    if (!user) return res.status(401).json({ message: 'Invalid token' });
    return res.json({ success: true, user });
  } catch (err) {
    if (err.name === 'TokenExpiredError') {
      return res.status(401).json({ message: 'Token expired' });
    }
    return res.status(401).json({ message: 'Invalid token' });
  }
}));

app.post('/verify-user', catchAsync(async (req, res) => {
  const { username, password } = req.body;

  
  const user = await User.findOne({ username }).select('+password');
  if (!user) {
    return res.status(401).json({ success: false, message: 'Invalid credentials' });
  }

  
  const isPasswordValid = await user.comparePassword(password);
  if (!isPasswordValid) {
    return res.status(401).json({ success: false, message: 'Invalid credentials' });
  }

  
  if (user.role !== 'admin' && !user.isApproved) {
    return res.status(403).json({ success: false, message: 'Account pending admin approval' });
  }

  try {
    const now = new Date();
    await User.findByIdAndUpdate(user._id, { isOnline: true, lastLogin: now, lastActive: now });
  } catch (e) {
    console.warn('Warning: failed to update user online status on login', e?.message);
  }

  const tokens = generateTokens(user._id);

  res.json({
    success: true,
    message: `Welcome ${user.role} ${user.username}`,
    role: user.role,
    isApproved: user.isApproved,
    
    accessToken: tokens.accessToken,
    refreshToken: tokens.refreshToken
  });
}));

app.post('/api/auth/heartbeat', authenticateToken, catchAsync(async (req, res) => {
  const userId = req.user?._id || req.user?.id;
  const role = req.user?.role ? String(req.user.role).toLowerCase() : '';
  const now = new Date();

  if (userId && role !== 'admin') {
    await User.findByIdAndUpdate(userId, { lastActive: now, isOnline: true }).setOptions({ skipFilter: true });
  }

  return res.json({ success: true, serverTime: now.toISOString() });
}));

app.post('/api/auth/offline', authenticateToken, catchAsync(async (req, res) => {
  const userId = req.user?._id || req.user?.id;
  const role = req.user?.role ? String(req.user.role).toLowerCase() : '';
  if (userId && role !== 'admin') {
    const now = new Date();
    await User.findByIdAndUpdate(userId, { lastActive: now, isOnline: false }).setOptions({ skipFilter: true });
  }
  return res.json({ success: true });
}));



const Module = require('./models/Module');
const Exam = require('./models/Exam');
const Result = require('./models/Result');
const UserProgress = require('./models/UserProgress');
const Certificate = require('./models/Certificate');
const CertificateGenerator = require('./utils/certificateGenerator');
const { recomputeAssignmentsForUserProgress } = require('./utils/moduleAssignment');


app.get('/admin/users', catchAsync(async (req, res) => {
  const { approved } = req.query;
  const filter = {};
  if (approved === 'true') filter.isApproved = true;
  if (approved === 'false') filter.isApproved = { $ne: true };

  const usersRaw = await User.find(filter).select('-password').lean();
  const now = Date.now();
  const STALE_MS = (config.HEARTBEAT_STALE_SECONDS || 30) * 1000;

  const usernames = usersRaw.map(u => u.username);
  const progresses = await UserProgress.find({ username: { $in: usernames } }).lean();
  const progressByUser = new Map((progresses || []).map(p => [p.username, p]));

  const users = (usersRaw || []).map(u => {
    const p = progressByUser.get(u.username);
    const summary = p ? {
      advancedUser: !!p.advancedUser,
      assignedModules: Array.isArray(p.accessibleModules) ? p.accessibleModules.length : 0,
      moduleTypeScores: p.moduleTypeScores || {}
    } : {
      advancedUser: false,
      assignedModules: 0,
      moduleTypeScores: {}
    };

    const fullName = [u.firstName, u.middleName, u.lastName]
      .filter(Boolean)
      .map(s => String(s).trim())
      .filter(Boolean)
      .join(' ');

    return {
      ...u,
      fullName: fullName || u.fullName || u.username,
      isOnline: !!(u.lastActive && (now - new Date(u.lastActive).getTime()) < STALE_MS),
      progressSummary: summary
    };
  });
  res.json(users);
}));

app.post('/admin/users/:id/approve', catchAsync(async (req, res) => {
  const updated = await User.findOneAndUpdate({ _id: req.params.id }, { isApproved: true }, { new: true }).select('-password');
  if (!updated) return res.status(404).json({ error: 'User not found' });
  res.json({ success: true, user: updated });
}));

app.put('/admin/users/:id', catchAsync(async (req, res) => {
  const { username, fullName, age, address, gender, role, isApproved } = req.body;
  
  
  if (!username || !fullName || !age || !address || !gender) {
    return res.status(400).json({ 
      error: 'All fields are required: username, full name, age, address, and gender' 
    });
  }

  
  const ageNumber = parseInt(age);
  if (isNaN(ageNumber) || ageNumber < 1 || ageNumber > 150) {
    return res.status(400).json({ 
      error: 'Age must be a valid number between 1 and 150' 
    });
  }

  
  if (!['Male', 'Female', 'Other'].includes(gender)) {
    return res.status(400).json({ 
      error: 'Gender must be Male, Female, or Other' 
    });
  }

  
  const existingUser = await User.findOne({ 
    username, 
    _id: { $ne: req.params.id } 
  });
  if (existingUser) {
    return res.status(409).json({ 
      error: 'Username already exists' 
    });
  }

  const updated = await User.findByIdAndUpdate(
    req.params.id, 
    {
      username,
      fullName,
      age: ageNumber,
      address,
      gender,
      role: role || 'user',
      isApproved: typeof isApproved === 'boolean' ? isApproved : true
    }, 
    { new: true }
  ).select('-password');
  
  if (!updated) {
    return res.status(404).json({ error: 'User not found' });
  }
  
  res.json({ success: true, user: updated });
}));

app.delete('/admin/users/:id', catchAsync(async (req, res) => {
  const deleted = await User.findOneAndDelete({ _id: req.params.id });
  if (!deleted) return res.status(404).json({ error: 'User not found' });
  res.json({ success: true });
}));



app.get('/api/exams/active/:type', catchAsync(async (req, res) => {
  const { type } = req.params;
  if (!['pre-assessment', 'post-assessment'].includes(type)) {
    return res.status(400).json({ error: 'Invalid exam type' });
  }

  const exam = await Exam.findOne({ type, isActive: true }).sort({ createdAt: -1 });
  if (!exam) {
    return res.status(404).json({ error: `No active ${type} exam found` });
  }

  let baseQuestions = (exam.questions || []).map(q => ({
    id: q.id,
    question: q.question,
    options: q.options,
    moduleTitle: q.moduleTitle
  }));

  if (type === 'pre-assessment' || type === 'post-assessment') {
    for (let i = baseQuestions.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [baseQuestions[i], baseQuestions[j]] = [baseQuestions[j], baseQuestions[i]];
    }
  }

  const sanitized = {
    _id: exam._id,
    title: exam.title,
    type: exam.type,
    timeLimit: exam.timeLimit,
    waitTime: exam.waitTime,
    passingScore: exam.passingScore,
    isActive: exam.isActive,
    createdAt: exam.createdAt,
    updatedAt: exam.updatedAt,
    questions: baseQuestions
  };

  res.json(sanitized);
}));

app.post('/api/exams', catchAsync(async (req, res) => {
  const { title, type, questions, timeLimit, waitTime, isActive } = req.body;
  
  
  if (!title || !type) {
    return res.status(400).json({ error: 'Title and Type are required' });
  }
  
  if (!questions || questions.length === 0) {
    return res.status(400).json({ error: 'At least one question is required' });
  }
  
  
  for (let i = 0; i < questions.length; i++) {
    const question = questions[i];
    if (!question.question || !question.options || question.options.length !== 4 || !question.correctAnswer) {
      return res.status(400).json({ error: `Question ${i + 1} is incomplete. All questions must have text, 4 options, and a correct answer.` });
    }
  }
  
  const exam = await Exam.create(req.body);
  res.status(201).json(exam);
}));

app.get('/api/exams', catchAsync(async (req, res) => {
  const exams = await Exam.find().sort({ createdAt: -1 });
  res.json(exams);
}));

app.get('/api/exams/:id', catchAsync(async (req, res) => {
  const exam = await Exam.findById(req.params.id);
  if (!exam) {
    return res.status(404).json({ error: 'Exam not found' });
  }
  res.json(exam);
}));

app.put('/api/exams/:id', catchAsync(async (req, res) => {
  const { title, type, questions, timeLimit, waitTime, isActive } = req.body;
  
  
  if (!title || !type) {
    return res.status(400).json({ error: 'Title and Type are required' });
  }
  
  if (!questions || questions.length === 0) {
    return res.status(400).json({ error: 'At least one question is required' });
  }
  
  
  for (let i = 0; i < questions.length; i++) {
    const question = questions[i];
    if (!question.question || !question.options || question.options.length !== 4 || !question.correctAnswer) {
      return res.status(400).json({ error: `Question ${i + 1} is incomplete. All questions must have text, 4 options, and a correct answer.` });
    }
  }
  
  const exam = await Exam.findByIdAndUpdate(req.params.id, req.body, { new: true });
  if (!exam) {
    return res.status(404).json({ error: 'Exam not found' });
  }
  res.json(exam);
}));

app.delete('/api/exams/:id', catchAsync(async (req, res) => {
  const exam = await Exam.findByIdAndDelete(req.params.id);
  if (!exam) {
    return res.status(404).json({ error: 'Exam not found' });
  }
  res.json({ success: true, message: 'Exam deleted successfully' });
}));


app.post('/api/results', catchAsync(async (req, res) => {
  const payload = req.body || {};
  const result = await Result.create(payload);
  res.status(201).json(result);
}));

app.get('/api/results', catchAsync(async (req, res) => {
  const { username, examId } = req.query;
  const filter = {};
  if (username) filter.username = username;
  if (examId) filter.examId = examId;
  const results = await Result.find(filter).sort({ createdAt: -1 });
  res.json(results);
}));

app.get('/api/results/latest', catchAsync(async (req, res) => {
  const { username, examId } = req.query;
  if (!username || !examId) return res.status(400).json({ error: "username and examId are required" });
  const latest = await Result.findOne({ username, examId }).sort({ createdAt: -1 });
  if (!latest) return res.status(404).json({ error: "Result not found" });
  res.json(latest);
}));

app.delete('/api/results/:id', catchAsync(async (req, res) => {
  const deleted = await Result.findByIdAndDelete(req.params.id);
  if (!deleted) {
    return res.status(404).json({ error: 'Result not found' });
  }
  res.json({ success: true });
}));


app.get('/api/user-progress/:username', catchAsync(async (req, res) => {
  const username = req.params.username;
  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    progress = new UserProgress({ username });
  }

  let moduleScores = progress.moduleScores || {};
  let overallPct = progress.overallModuleScore || 0;

  try {
    const result = await recomputeAssignmentsForUserProgress(progress);
    if (result && result.hadResult) {
      moduleScores = result.moduleScores || moduleScores;
      overallPct = typeof result.overallPct === 'number' ? result.overallPct : overallPct;
    }
    await progress.save();
  } catch (e) {
    console.warn('Progress recompute skipped:', e?.message);
  }

  res.json({
    ...progress.toObject(),
    moduleScores: moduleScores,
    overall: overallPct,
  });
}));

app.post('/api/user-progress/:username/pre-assessment', catchAsync(async (req, res) => {
  console.warn(`[DEPRECATED] POST /api/user-progress/${req.params.username}/pre-assessment called. Use /api/user-progress/:username/exam instead.`);
  req.url = `/api/user-progress/${req.params.username}/exam`;
  return res.redirect(307, req.url);
}));

app.post('/api/user-progress/:username/exam', catchAsync(async (req, res) => {
  const { score } = req.body;
  const username = req.params.username;

  if (typeof score !== 'number' || score < 0 || score > 100) {
    return res.status(400).json({ error: 'Invalid score. Must be a number between 0 and 100.' });
  }

  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    progress = new UserProgress({ username });
  }
  if (progress.hasCompletedPreAssessment) {
    return res.status(409).json({ 
      error: 'Pre-assessment already completed',
      alreadyCompleted: true,
      completedAt: progress.preAssessmentCompletedAt
    });
  }

  progress.hasCompletedPreAssessment = true;
  progress.preAssessmentScore = score;
  progress.preAssessmentCompletedAt = new Date();
  progress.currentCheckpointGroup = 1;

  let moduleScores = progress.moduleScores || {};
  let overallPct = progress.overallModuleScore || 0;

  try {
    const result = await recomputeAssignmentsForUserProgress(progress);
    if (result && result.hadResult) {
      moduleScores = result.moduleScores || moduleScores;
      overallPct = typeof result.overallPct === 'number' ? result.overallPct : overallPct;
    }
  } catch (e) {
    console.warn('Pre-assessment recompute skipped:', e?.message);
  }

  await progress.save();

  res.json({
    ...progress.toObject(),
    moduleScores: moduleScores,
    overall: overallPct,
  });
}));

app.post('/api/user-progress/:username/module-complete', catchAsync(async (req, res) => {
  const { moduleId } = req.body;
  const username = req.params.username;
  
  const progress = await UserProgress.findOne({ username });
  if (!progress) {
    return res.status(404).json({ error: 'User progress not found' });
  }
  
  if (!progress.completedModules.includes(moduleId)) {
    progress.completedModules.push(moduleId);
    await progress.save();
  }
  
  res.json(progress);
}));

app.post('/api/user-progress/:username/post-assessment', catchAsync(async (req, res) => {
  console.warn(`[DEPRECATED] POST /api/user-progress/${req.params.username}/post-assessment called. Post-assessment is historical only.`);
  const { score, passed } = req.body;
  const username = req.params.username;
  
  
  if (typeof score !== 'number' || score < 0 || score > 100) {
    return res.status(400).json({ error: 'Invalid score. Must be a number between 0 and 100.' });
  }
  
  
  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    return res.status(404).json({ error: 'User progress not found. Complete pre-assessment first.' });
  }
  
  
  progress.hasCompletedPostAssessment = true;
  progress.postAssessmentScore = score;
  progress.postAssessmentCompletedAt = new Date();
  
  // If the client tells us whether this attempt was a pass, persist that flag.
  // This represents the pass/fail decision at the time of the exam
  // and should not change if the exam's passingScore is modified later.
  if (typeof passed === 'boolean') {
    progress.postAssessmentPassed = passed;
  }
  
  await progress.save();
  res.json(progress);
}));


app.post('/api/user-progress/:username/post-assessment-early-decline', catchAsync(async (req, res) => {
  console.warn(`[DEPRECATED] POST /api/user-progress/${req.params.username}/post-assessment-early-decline called. Post-assessment is historical only.`);
  const username = req.params.username;
  const { declined } = req.body || {};

  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    return res.status(404).json({ error: 'User progress not found.' });
  }

  
  if (declined === true) {
    progress.postAssessmentEarlyDeclined = true;
    progress.postAssessmentEarlyDeclinedAt = new Date();
    await progress.save();
  }

  res.json({
    success: true,
    postAssessmentEarlyDeclined: progress.postAssessmentEarlyDeclined,
    postAssessmentEarlyDeclinedAt: progress.postAssessmentEarlyDeclinedAt,
  });
}));


app.post('/api/user-progress/:username/refresh-modules', catchAsync(async (req, res) => {
  const username = req.params.username;

  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    return res.status(404).json({ error: 'User progress not found.' });
  }

  let result;
  try {
    result = await recomputeAssignmentsForUserProgress(progress);
    await progress.save();
  } catch (e) {
    console.warn('Refresh modules recompute skipped:', e?.message);
  }

  const totalModules = result && Array.isArray(result.accessibleIds)
    ? result.accessibleIds.length
    : (progress.accessibleModules || []).length;

  res.json({
    success: true,
    message: 'Accessible modules refreshed',
    accessibleModules: progress.accessibleModules,
    assignedModules: progress.assignedModules,
    totalModules,
  });
}));

app.post('/api/user-progress/:username/recompute-pre-assignment', catchAsync(async (req, res) => {
  const { username } = req.params;

  let progress = await UserProgress.findOne({ username });
  if (!progress) progress = new UserProgress({ username });

  const result = await recomputeAssignmentsForUserProgress(progress);
  if (!result || !result.hadResult) {
    return res.status(404).json({ error: 'No pre-assessment result found for this user.' });
  }

  await progress.save();

  res.json({
    success: true,
    progress: {
      ...progress.toObject(),
      moduleScores: result.moduleScores || progress.moduleScores || {},
      overall: typeof result.overallPct === 'number' ? result.overallPct : (progress.overallModuleScore || 0),
    },
  });
}));



app.get('/api/modules/user/:username', catchAsync(async (req, res) => {
  const { username } = req.params;

  let progress = await UserProgress.findOne({ username });
  if (!progress) progress = new UserProgress({ username });

  try {
    await recomputeAssignmentsForUserProgress(progress);
    await progress.save();
  } catch (e) {
    console.warn('Modules recompute skipped:', e?.message);
  }

  let accessibleModuleIds = [];
  if (progress.assignedModules && progress.assignedModules.length > 0) {
    accessibleModuleIds = progress.assignedModules;
  } else if (progress.accessibleModules && progress.accessibleModules.length > 0) {
    accessibleModuleIds = progress.accessibleModules;
  } else {
    const allModules = await Module.find({
      isActive: true,
      $or: [
        { 'checkpointQuiz.isCheckpointQuiz': { $exists: false } },
        { 'checkpointQuiz.isCheckpointQuiz': false }
      ],
    });
    accessibleModuleIds = allModules.map(m => m._id.toString());
  }

  const modules = await Module.find({ _id: { $in: accessibleModuleIds }, isActive: true });
  res.json(modules);
}));


app.get('/api/modules/:id', catchAsync(async (req, res) => {
  const module = await Module.findById(req.params.id);
  if (!module) {
    return res.status(404).json({ error: 'Module not found' });
  }
  res.json(module);
}));


app.get('/api/modules/:moduleId/submodules', catchAsync(async (req, res) => {
  const module = await Module.findById(req.params.moduleId);
  if (!module) {
    return res.status(404).json({ error: 'Module not found' });
  }
  res.json(module.subModules || []);
}));


app.get('/api/modules/:moduleId/submodules/:subModuleId', catchAsync(async (req, res) => {
  const module = await Module.findById(req.params.moduleId);
  if (!module) {
    return res.status(404).json({ error: 'Module not found' });
  }
  
  const subModule = module.subModules.find(sm => sm.subModuleId === req.params.subModuleId);
  if (!subModule) {
    return res.status(404).json({ error: 'Sub-module not found' });
  }
  
  res.json(subModule);
}));


app.get('/api/checkpoint-quizzes', catchAsync(async (req, res) => {
  const modules = await Module.find({ 
    isActive: true,
    'checkpointQuiz.isCheckpointQuiz': true 
  }).sort({ 'checkpointQuiz.checkpointNumber': 1 });
  
  
  const quizzes = modules.map(module => ({
    _id: module._id,
    checkpointNumber: module.checkpointQuiz.checkpointNumber,
    title: module.title,
    description: module.description,
    requiredModulesCount: module.checkpointQuiz.requiredModulesCount,
    requiredModuleIds: module.checkpointQuiz.requiredModuleIds,
    questions: module.checkpointQuiz.questions,
    passingScore: module.checkpointQuiz.passingScore,
    timeLimit: module.checkpointQuiz.timeLimit,
    isActive: module.isActive,
    moduleId: module.moduleId
  }));
  
  res.json(quizzes);
}));

app.get('/api/checkpoint-quizzes/:checkpointNumber', catchAsync(async (req, res) => {
  const checkpointNumber = parseInt(req.params.checkpointNumber);
  const module = await Module.findOne({ 
    isActive: true,
    'checkpointQuiz.isCheckpointQuiz': true,
    'checkpointQuiz.checkpointNumber': checkpointNumber
  });
  
  if (!module) {
    return res.status(404).json({ error: 'Checkpoint quiz not found' });
  }
  
  
  const quiz = {
    _id: module._id,
    checkpointNumber: module.checkpointQuiz.checkpointNumber,
    title: module.title,
    description: module.description,
    requiredModulesCount: module.checkpointQuiz.requiredModulesCount,
    requiredModuleIds: module.checkpointQuiz.requiredModuleIds,
    questions: module.checkpointQuiz.questions,
    passingScore: module.checkpointQuiz.passingScore,
    timeLimit: module.checkpointQuiz.timeLimit,
    isActive: module.isActive,
    moduleId: module.moduleId
  };
  
  res.json(quiz);
}));

app.post('/api/checkpoint-quizzes', catchAsync(async (req, res) => {
  const { checkpointNumber, title, description, requiredModulesCount, requiredModuleIds, questions, passingScore, timeLimit, moduleId } = req.body;
  
  
  if (!checkpointNumber || !title || !requiredModulesCount || !requiredModuleIds || !questions) {
    return res.status(400).json({ error: 'Checkpoint number, title, required modules count, required module IDs, and questions are required' });
  }
  
  
  const existingQuiz = await Module.findOne({
    'checkpointQuiz.isCheckpointQuiz': true,
    'checkpointQuiz.checkpointNumber': checkpointNumber
  });
  
  if (existingQuiz) {
    return res.status(409).json({ 
      error: `Checkpoint number ${checkpointNumber} is already taken`,
      existingQuiz: {
        id: existingQuiz._id,
        title: existingQuiz.title,
        moduleId: existingQuiz.moduleId
      }
    });
  }
  
  
  if (!Array.isArray(questions) || questions.length === 0) {
    return res.status(400).json({ error: 'At least one question is required' });
  }
  
  for (const question of questions) {
    if (!question.id || !question.question || !question.options || !question.correctAnswer) {
      return res.status(400).json({ error: 'Each question must have id, question, options, and correctAnswer' });
    }
    if (!Array.isArray(question.options) || question.options.length !== 4) {
      return res.status(400).json({ error: 'Each question must have exactly 4 options' });
    }
  }
  
  
  const module = await Module.create({
    moduleId: moduleId || `CQ${checkpointNumber}`,
    title: title,
    description: description || '',
    scoreRange: { min: 0, max: 100 }, 
    isActive: true,
    tier: 'Tier 1',
    subModules: [],
    checkpointQuiz: {
      isCheckpointQuiz: true,
      checkpointNumber: checkpointNumber,
      requiredModulesCount: requiredModulesCount,
      requiredModuleIds: requiredModuleIds,
      questions: questions,
      passingScore: passingScore || 70,
      timeLimit: timeLimit || 15
    }
  });
  
  res.status(201).json(module);
}));

app.put('/api/checkpoint-quizzes/:checkpointNumber', catchAsync(async (req, res) => {
  const checkpointNumber = parseInt(req.params.checkpointNumber);
  const { title, description, requiredModulesCount, requiredModuleIds, questions, passingScore, timeLimit } = req.body;
  
  const module = await Module.findOne({
    'checkpointQuiz.isCheckpointQuiz': true,
    'checkpointQuiz.checkpointNumber': checkpointNumber
  });
  
  if (!module) {
    return res.status(404).json({ error: 'Checkpoint quiz not found' });
  }
  
  
  if (title) module.title = title;
  if (description) module.description = description;
  
  
  if (requiredModulesCount) module.checkpointQuiz.requiredModulesCount = requiredModulesCount;
  if (requiredModuleIds) module.checkpointQuiz.requiredModuleIds = requiredModuleIds;
  if (questions) module.checkpointQuiz.questions = questions;
  if (passingScore) module.checkpointQuiz.passingScore = passingScore;
  if (timeLimit) module.checkpointQuiz.timeLimit = timeLimit;
  
  await module.save();
  
  res.json(module);
}));

app.delete('/api/checkpoint-quizzes/:checkpointNumber', catchAsync(async (req, res) => {
  const checkpointNumber = parseInt(req.params.checkpointNumber);
  const deleted = await Module.findOneAndDelete({
    'checkpointQuiz.isCheckpointQuiz': true,
    'checkpointQuiz.checkpointNumber': checkpointNumber
  });
  
  if (!deleted) {
    return res.status(404).json({ error: 'Checkpoint quiz not found' });
  }
  
  res.json({ success: true });
}));


app.post('/api/checkpoint-quizzes/:checkpointNumber/submit', catchAsync(async (req, res) => {
  const { username, answers, quizId } = req.body;
  const checkpointNumber = parseInt(req.params.checkpointNumber);
  
  if (!username || !answers) {
    return res.status(400).json({ error: 'Username and answers are required' });
  }
  
  
  let module;
  if (quizId) {
    module = await Module.findById(quizId);
    if (!module) {
      return res.status(404).json({ error: 'Quiz not found' });
    }
  } else {
    module = await Module.findOne({ 
      isActive: true,
      'checkpointQuiz.isCheckpointQuiz': true,
      'checkpointQuiz.checkpointNumber': checkpointNumber
    });
  }
  
  if (!module) {
    return res.status(404).json({ error: 'Checkpoint quiz not found' });
  }
  
  const quiz = module.checkpointQuiz;
  
  
  const mismatchedAnswers = Object.keys(answers).filter(answerKey => 
    !quiz.questions.find(q => q.id === answerKey)
  );
  
  if (mismatchedAnswers.length > 0) {
    return res.status(400).json({ 
      error: 'Answer keys do not match quiz questions. This indicates a data mismatch.',
      expectedQuestions: quiz.questions.map(q => q.id),
      receivedAnswers: Object.keys(answers)
    });
  }
  
  
  let correctAnswers = 0;
  const totalQuestions = quiz.questions.length;
  
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
  
  quiz.questions.forEach(question => {
    const options = (question.options || []).map(o => String(o));
    const correctAnswerIndex = toAnswerIndex(question.correctAnswer, options);
    const userAnswerIndex = toAnswerIndex(answers ? answers[question.id] : undefined, options);
    
    if (correctAnswerIndex >= 0 && userAnswerIndex === correctAnswerIndex) {
      correctAnswers++;
    }
  });
  
  const percentage = Math.round((correctAnswers / totalQuestions) * 100);
  const passed = percentage >= quiz.passingScore;
  
  
  let userProgress = await UserProgress.findOne({ username });
  if (!userProgress) {
    userProgress = new UserProgress({ username });
  }
  
  
  userProgress.completedCheckpointQuizzes = userProgress.completedCheckpointQuizzes.filter(
    q => q.checkpointNumber !== checkpointNumber
  );
  
  
  const newCompletion = {
    moduleId: module._id.toString(),
    checkpointNumber,
    score: percentage,
    passed,
    completedAt: new Date()
  };
  userProgress.completedCheckpointQuizzes.push(newCompletion);
  
  
  if (passed) {
    
    const nextCheckpointGroup = checkpointNumber + 1;
    const nextModules = await Module.find({ 
      isActive: true,
      'scoreRange.min': { $lte: userProgress.preAssessmentScore || 0 },
      'scoreRange.max': { $gte: userProgress.preAssessmentScore || 100 }
    });
    
    
    nextModules.forEach(module => {
      if (!userProgress.accessibleModules.includes(module._id.toString())) {
        userProgress.accessibleModules.push(module._id.toString());
      }
    });
    
    
    userProgress.currentCheckpointGroup = nextCheckpointGroup;
  }
  
  await userProgress.save();
  
  res.json({
    score: percentage,
    passed,
    correctAnswers,
    totalQuestions,
    message: passed ? `Quiz passed! Checkpoint Group ${checkpointNumber + 1} modules unlocked.` : 'Quiz failed. Please try again.'
  });
}));


app.get('/api/checkpoint-quizzes/:checkpointNumber/can-take/:username', catchAsync(async (req, res) => {
  const checkpointNumber = parseInt(req.params.checkpointNumber);
  const { username } = req.params;
  
  
  const module = await Module.findOne({ 
    isActive: true,
    'checkpointQuiz.isCheckpointQuiz': true,
    'checkpointQuiz.checkpointNumber': checkpointNumber
  });
  
  if (!module) {
    return res.status(404).json({ error: 'Checkpoint quiz not found' });
  }
  
  const quiz = module.checkpointQuiz;
  
  
  const userProgress = await UserProgress.findOne({ username });
  if (!userProgress) {
    return res.status(404).json({ error: 'User progress not found' });
  }
  
  
  const completedRequiredModules = quiz.requiredModuleIds.filter(moduleId => 
    userProgress.completedModules.includes(moduleId)
  );
  
  const canTake = completedRequiredModules.length >= quiz.requiredModulesCount;
  const alreadyCompleted = userProgress.completedCheckpointQuizzes.some(
    q => q.checkpointNumber === checkpointNumber && q.passed
  );
  
  res.json({
    canTake: canTake && !alreadyCompleted,
    completedRequiredModules: completedRequiredModules.length,
    requiredModulesCount: quiz.requiredModulesCount,
    alreadyCompleted,
    missingModules: quiz.requiredModuleIds.filter(moduleId => 
      !userProgress.completedModules.includes(moduleId)
    )
  });
}));


app.get('/debug/modules', catchAsync(async (req, res) => {
  if (!conn || !conn.db) {
    return res.status(503).json({ error: 'File storage is not ready' });
  }

  const modules = await Module.find({ isActive: true }).select('moduleId title content contentFileId scoreRange');
  const files = await conn.db.collection("uploads.files").find({}).toArray();
  
  const debug = {
    modules: modules.map(m => ({
      id: m._id,
      moduleId: m.moduleId,
      title: m.title,
      content: m.content,
      contentFileId: m.contentFileId,
      scoreRange: m.scoreRange,
      hasContent: !!m.content && m.content.trim() !== ''
    })),
    files: files.map(f => ({
      id: f._id,
      filename: f.filename,
      contentType: f.contentType,
      length: f.length
    }))
  };
  
  res.json(debug);
}));




const MAX_FILE_SIZE = 10 * 1024 * 1024; 

const storage = multer.memoryStorage();
const upload = multer({
  storage,
  limits: { fileSize: MAX_FILE_SIZE },
  fileFilter: (req, file, cb) => {
    
    const allowed = [
      'application/pdf',
      'image/png',
      'image/jpeg'
    ];
    if (!allowed.includes(file.mimetype)) {
      return cb(new Error('Unsupported file type'));
    }
    cb(null, true);
  }
});


const runSingleFileUpload = (req, res, next) => {
  upload.single('file')(req, res, (err) => {
    if (err) {
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(413).json({ error: 'File too large' });
      }
      return res.status(400).json({ error: err.message || 'Upload error' });
    }
    next();
  });
};


app.post("/upload",
  authenticateToken,
  (req, res, next) => {
    const role = req.user && req.user.role ? String(req.user.role).toLowerCase() : '';
    if (role !== 'admin') {
      return res.status(403).json({ error: 'Forbidden' });
    }
    next();
  },
  runSingleFileUpload,
  (req, res) => {
    if (!gfs || !conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready, please try again later.' });
    }

    if (!req.file) return res.status(400).json({ error: "No file uploaded" });

    const uploadStream = gfs.openUploadStream(req.file.originalname, {
      contentType: req.file.mimetype,
    });

    uploadStream.end(req.file.buffer);

    uploadStream.on("finish", () => {
      res.json({
        success: true,
        filename: req.file.originalname,
        fileId: uploadStream.id,
      });
    });

    uploadStream.on("error", (err) => {
      console.error('GridFS upload error:', err);
      res.status(500).json({ error: "Upload failed" });
    });
  }
);


app.get("/files", async (req, res) => {
  try {
    if (!conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }
    const files = await conn.db.collection("uploads.files").find().toArray();
    res.json(files);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});


app.get("/api/files", async (req, res) => {
  try {
    if (!conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }
    const files = await conn.db.collection("uploads.files").find().toArray();
    res.json(files);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});


app.get("/file/:filename", async (req, res) => {
  try {
    if (!gfs || !conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }

    const files = await conn.db
      .collection("uploads.files")
      .find({ filename: req.params.filename })
      .toArray();

    if (!files || files.length === 0) {
      return res.status(404).json({ error: "File not found" });
    }

    const file = files[0];
    const download = req.query.download === "true";

    const contentType = file.contentType || "application/pdf";
    res.set("Content-Type", contentType);
    res.set(
      "Content-Disposition",
      download
        ? `attachment; filename="${file.filename}"`
        : `inline; filename="${file.filename}"`
    );
    
    
    res.set("Cross-Origin-Resource-Policy", "cross-origin");

    const origin = req.headers.origin;
    if (origin && isAllowedOrigin(origin)) {
      res.set("Access-Control-Allow-Origin", origin);
      res.set("Access-Control-Allow-Credentials", "true");
    }
    res.set("Access-Control-Expose-Headers", "Content-Length, Content-Range, Accept-Ranges");
    res.set("Access-Control-Allow-Headers", "Range");
    res.set("Accept-Ranges", "bytes");

    const range = req.headers.range;
    const total = file.length;
    
    if (range) {
      const bytesPrefix = "bytes=";
      if (!range.startsWith(bytesPrefix)) {
        return res.status(416).send("Malformed Range header");
      }
      
      const [rawStart, rawEnd] = range.replace(bytesPrefix, "").split("-");
      let start = Number(rawStart);
      let end = rawEnd ? Number(rawEnd) : total - 1;
      
      if (Number.isNaN(start) || Number.isNaN(end) || start > end || end >= total) {
        return res.status(416).send("Unsatisfiable Range");
      }
      
      const chunkSize = end - start + 1;
      res.status(206);
      res.set("Content-Range", `bytes ${start}-${end}/${total}`);
      res.set("Content-Length", String(chunkSize));
      
      const partialStream = gfs.openDownloadStreamByName(req.params.filename, { start, end: end + 1 });
      partialStream.on("error", () => res.sendStatus(404));
      return partialStream.pipe(res);
    }

    res.set("Content-Length", String(total));
    const fullStream = gfs.openDownloadStreamByName(req.params.filename);
    fullStream.on("error", () => res.sendStatus(404));
    fullStream.pipe(res);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});


app.get("/api/file/:filename", async (req, res) => {
  try {
    if (!gfs || !conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }

    const files = await conn.db
      .collection("uploads.files")
      .find({ filename: req.params.filename })
      .toArray();

    if (!files || files.length === 0) {
      return res.status(404).json({ error: "File not found" });
    }

    const file = files[0];
    const download = req.query.download === "true";

    const contentType = file.contentType || "application/pdf";
    res.set("Content-Type", contentType);
    res.set(
      "Content-Disposition",
      download
        ? `attachment; filename="${file.filename}"`
        : `inline; filename="${file.filename}"`
    );
    
    
    res.set("Cross-Origin-Resource-Policy", "cross-origin");

    const origin = req.headers.origin;
    if (origin && isAllowedOrigin(origin)) {
      res.set("Access-Control-Allow-Origin", origin);
      res.set("Access-Control-Allow-Credentials", "true");
    }
    res.set("Access-Control-Expose-Headers", "Content-Length, Content-Range, Accept-Ranges");
    res.set("Access-Control-Allow-Headers", "Range");
    res.set("Accept-Ranges", "bytes");

    const range = req.headers.range;
    const total = file.length;
    
    if (range) {
      const bytesPrefix = "bytes=";
      if (!range.startsWith(bytesPrefix)) {
        return res.status(416).send("Malformed Range header");
      }
      
      const [rawStart, rawEnd] = range.replace(bytesPrefix, "").split("-");
      let start = Number(rawStart);
      let end = rawEnd ? Number(rawEnd) : total - 1;
      
      if (Number.isNaN(start) || Number.isNaN(end) || start > end || end >= total) {
        return res.status(416).send("Unsatisfiable Range");
      }
      
      const chunkSize = end - start + 1;
      res.status(206);
      res.set("Content-Range", `bytes ${start}-${end}/${total}`);
      res.set("Content-Length", String(chunkSize));
      
      const partialStream = gfs.openDownloadStreamByName(req.params.filename, { start, end: end + 1 });
      partialStream.on("error", () => res.sendStatus(404));
      return partialStream.pipe(res);
    }

    res.set("Content-Length", String(total));
    const fullStream = gfs.openDownloadStreamByName(req.params.filename);
    fullStream.on("error", () => res.sendStatus(404));
    fullStream.pipe(res);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});


app.get("/api/file/id/:id", async (req, res) => {
  try {
    if (!gfs || !conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }

    let fileId;
    try {
      fileId = new mongoose.Types.ObjectId(req.params.id);
    } catch (e) {
      return res.status(400).json({ error: 'Invalid file ID' });
    }

    const files = await conn.db
      .collection("uploads.files")
      .find({ _id: fileId })
      .toArray();

    if (!files || files.length === 0) {
      return res.status(404).json({ error: "File not found" });
    }

    const file = files[0];
    const download = req.query.download === "true";

    const contentType = file.contentType || "application/pdf";
    res.set("Content-Type", contentType);
    res.set(
      "Content-Disposition",
      download
        ? `attachment; filename="${file.filename}"`
        : `inline; filename="${file.filename}"`
    );

    res.set("Cross-Origin-Resource-Policy", "cross-origin");

    const origin = req.headers.origin;
    if (origin && isAllowedOrigin(origin)) {
      res.set("Access-Control-Allow-Origin", origin);
      res.set("Access-Control-Allow-Credentials", "true");
    }
    res.set("Access-Control-Expose-Headers", "Content-Length, Content-Range, Accept-Ranges");
    res.set("Access-Control-Allow-Headers", "Range");
    res.set("Accept-Ranges", "bytes");

    const range = req.headers.range;
    const total = file.length;

    if (range) {
      const bytesPrefix = 'bytes=';
      if (!range.startsWith(bytesPrefix)) {
        return res.status(416).send('Malformed Range header');
      }

      const [rawStart, rawEnd] = range.replace(bytesPrefix, '').split('-');
      let start = Number(rawStart);
      let end = rawEnd ? Number(rawEnd) : total - 1;

      if (Number.isNaN(start) || Number.isNaN(end) || start > end || end >= total) {
        return res.status(416).send('Unsatisfiable Range');
      }

      const chunkSize = end - start + 1;
      res.status(206);
      res.set('Content-Range', `bytes ${start}-${end}/${total}`);
      res.set('Content-Length', String(chunkSize));

      const partialStream = gfs.openDownloadStream(fileId, { start, end: end + 1 });
      partialStream.on('error', () => res.sendStatus(404));
      return partialStream.pipe(res);
    }

    res.set('Content-Length', String(total));
    const fullStream = gfs.openDownloadStream(fileId);
    fullStream.on('error', () => res.sendStatus(404));
    fullStream.pipe(res);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});


app.delete("/file/:filename", authenticateToken, async (req, res) => {
  try {
    const role = req.user && req.user.role ? String(req.user.role).toLowerCase() : '';
    if (role !== 'admin') {
      return res.status(403).json({ error: 'Forbidden' });
    }

    if (!gfs || !conn || !conn.db) {
      return res.status(503).json({ error: 'File storage is not ready' });
    }

    const files = await conn.db
      .collection("uploads.files")
      .find({ filename: req.params.filename })
      .toArray();

    if (!files || files.length === 0) {
      return res.status(404).json({ error: "File not found" });
    }

    await gfs.delete(files[0]._id);

    const updatedFiles = await conn.db.collection("uploads.files").find().toArray();
    res.json({ success: true, message: "File deleted", files: updatedFiles });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});




app.get('/api/certificates/eligibility/:username', catchAsync(async (req, res) => {
  const { username } = req.params;
  
  
  const userProgress = await UserProgress.findOne({ username });
  if (!userProgress) {
    return res.status(404).json({ 
      success: false, 
      message: 'User progress not found' 
    });
  }
  
  const eligibility = CertificateGenerator.calculateEligibility(userProgress);
  
  res.json({
    success: true,
    ...eligibility,
    userProgress: {
      preAssessmentCompleted: userProgress.hasCompletedPreAssessment,
      postAssessmentCompleted: userProgress.hasCompletedPostAssessment,
      modulesCompleted: userProgress.completedModules ? userProgress.completedModules.length : 0,
      preAssessmentScore: userProgress.preAssessmentScore,
      postAssessmentScore: userProgress.postAssessmentScore
    }
  });
}));

app.get('/api/post-assessment/status/:username', catchAsync(async (req, res) => {
  console.warn(`[DEPRECATED] GET /api/post-assessment/status/${req.params.username} called. Post-assessment is historical only.`);
  const { username } = req.params;

  let progress = await UserProgress.findOne({ username });
  if (!progress) {
    progress = new UserProgress({ username });
  }

  const alreadyPassed = progress.postAssessmentPassed === true;
  const score = progress.postAssessmentScore || 0;
  const completedAt = progress.postAssessmentCompletedAt || null;

  return res.json({
    success: true,
    username,
    alreadyPassed,
    score,
    completedAt,
  });
}));


app.post('/api/certificates/generate/:username', catchAsync(async (req, res) => {
  const { username } = req.params;
  const { force } = req.query; 
  
  
  const user = await User.findOne({ username });
  if (!user) {
    return res.status(404).json({ 
      success: false, 
      message: 'User not found' 
    });
  }
  
  const userProgress = await UserProgress.findOne({ username });
  if (!userProgress) {
    return res.status(404).json({ 
      success: false, 
      message: 'User progress not found' 
    });
  }
  
  
  const eligibility = CertificateGenerator.calculateEligibility(userProgress);
  if (!eligibility.isEligible) {
    return res.status(400).json({
      success: false,
      message: 'User not eligible for certificate',
      requirements: eligibility.requirements
    });
  }
  
  
  let certificate = await Certificate.findOne({ username });

  const shouldRegenerate = certificate && String(force).toLowerCase() === 'true';
  
  if (!certificate || shouldRegenerate) {
    
    const totalModules = await Module.countDocuments({ isActive: true });
    
    
    const certificateData = CertificateGenerator.prepareCertificateData(user, userProgress, totalModules);
    
    
    const generator = new CertificateGenerator();
    const pdfUint8Array = await generator.generateCertificate(certificateData);
    const pdfBuffer = Buffer.from(pdfUint8Array); 

    // Compute a robust full name for the certificate
    const fullNameFromUser = (user.fullName && String(user.fullName).trim()) ||
      [user.firstName, user.middleName, user.lastName]
        .filter(Boolean)
        .map(s => String(s).trim())
        .filter(Boolean)
        .join(' ');
    const certificateFullName = fullNameFromUser || user.username;
    
    if (!certificate) {
      
      certificate = new Certificate({
        certificateId: certificateData.certificateId,
        username: user.username,
        fullName: certificateFullName,
        preAssessmentScore: userProgress.preAssessmentScore,
        postAssessmentScore: userProgress.postAssessmentScore,
        improvementScore: certificateData.improvementScore,
        modulesCompleted: certificateData.modulesCompleted,
        totalModules: totalModules,
        completionPercentage: certificateData.completionPercentage,
        completionDate: userProgress.postAssessmentCompletedAt || new Date(),
        pdfBuffer
        
      });
    } else {
      
      certificate.fullName = certificateFullName;
      certificate.preAssessmentScore = userProgress.preAssessmentScore;
      certificate.postAssessmentScore = userProgress.postAssessmentScore;
      certificate.improvementScore = certificateData.improvementScore;
      certificate.modulesCompleted = certificateData.modulesCompleted;
      certificate.totalModules = totalModules;
      certificate.completionPercentage = certificateData.completionPercentage;
      certificate.completionDate = userProgress.postAssessmentCompletedAt || new Date();
      certificate.pdfBuffer = pdfBuffer;
      certificate.issueDate = new Date();
    }
    
    await certificate.save();
  }
  
  
  res.set({
    'Content-Type': 'application/pdf',
    'Content-Disposition': `attachment; filename="Certificate-${user.fullName.replace(/\s+/g, '_')}-${certificate.certificateId}.pdf"`,
    'Content-Length': certificate.pdfBuffer.length
  });
  
  res.send(certificate.pdfBuffer);
}));


app.get('/api/certificates/:username', catchAsync(async (req, res) => {
  const { username } = req.params;
  
  const certificate = await Certificate.findOne({ username });
  if (!certificate) {
    return res.status(404).json({ 
      success: false, 
      message: 'Certificate not found' 
    });
  }
  
  res.json({
    success: true,
    certificate
  });
}));


app.get('/api/certificates/:certificateId/download', catchAsync(async (req, res) => {
  const { certificateId } = req.params;
  
  const certificate = await Certificate.findOne({ certificateId });
  if (!certificate) {
    return res.status(404).json({ 
      success: false, 
      message: 'Certificate not found' 
    });
  }
  
  if (!certificate.pdfBuffer) {
    return res.status(500).json({ 
      success: false, 
      message: 'Certificate PDF not available' 
    });
  }
  
  res.set({
    'Content-Type': 'application/pdf',
    'Content-Disposition': `attachment; filename="Certificate-${certificate.fullName.replace(/\s+/g, '_')}-${certificate.certificateId}.pdf"`,
    'Content-Length': certificate.pdfBuffer.length
  });
  
  res.send(certificate.pdfBuffer);
}));


app.get('/api/certificates/verify/:validationHash', catchAsync(async (req, res) => {
  const { validationHash } = req.params;
  
  const certificate = await Certificate.findOne({ validationHash, isValid: true });
  if (!certificate) {
    return res.status(404).json({ 
      success: false, 
      message: 'Certificate not found or invalid' 
    });
  }
  
  res.json({
    success: true,
    message: 'Certificate is valid',
    certificate: {
      certificateId: certificate.certificateId,
      fullName: certificate.fullName,
      courseName: certificate.courseName,
      completionDate: certificate.completionDate,
      issueDate: certificate.issueDate,
      postAssessmentScore: certificate.postAssessmentScore,
      improvementScore: certificate.improvementScore,
      isValid: certificate.isValid
    }
  });
}));


app.get('/api/certificates', catchAsync(async (req, res) => {
  const { page = 1, limit = 10, username } = req.query;
  
  const filter = {};
  if (username) {
    filter.username = { $regex: username, $options: 'i' };
  }
  
  const certificates = await Certificate.find(filter)
    .select('-pdfBuffer') 
    .sort({ createdAt: -1 })
    .limit(limit * 1)
    .skip((page - 1) * limit);
    
  const totalCertificates = await Certificate.countDocuments(filter);
  
  res.json({
    success: true,
    certificates,
    pagination: {
      currentPage: parseInt(page),
      totalPages: Math.ceil(totalCertificates / limit),
      totalCertificates,
      hasNext: page * limit < totalCertificates,
      hasPrev: page > 1
    }
  });
}));




app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: `Route ${req.originalUrl} not found`
  });
});


app.use(globalErrorHandler);


process.on('SIGTERM', () => {
  console.log('?? SIGTERM received');
  console.log('?? Shutting down gracefully');
  server.close(() => {
    console.log('?? Process terminated');
    mongoose.connection.close();
  });
});

process.on('unhandledRejection', (err) => {
  console.log('?? UNHANDLED REJECTION! Shutting down...');
  console.log(err.name, err.message);
  server.close(() => {
    process.exit(1);
  });
});

module.exports = app;

let server;
if (process.env.VERCEL !== '1' && require.main === module) {
  server = app.listen(config.PORT, () => {
    console.log(`?? Server running on port ${config.PORT}`);
    console.log(`?? Environment: ${config.NODE_ENV}`);
    console.log(`?? Frontend URL: ${config.FRONTEND_URL}`);
    
    
    const ExamSession = require('./models/ExamSession');
    setInterval(async () => {
      try {
        const result = await ExamSession.cleanupOldSessions();
        if (result.deletedCount > 0) {
          console.log(`?? Cleaned up ${result.deletedCount} old exam sessions`);
        }
      } catch (error) {
        console.error('Error cleaning up old sessions:', error);
      }
    }, 60 * 60 * 1000); 
  });
}

