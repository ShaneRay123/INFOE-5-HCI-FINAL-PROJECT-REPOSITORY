const mongoose = require('mongoose');

const examSessionSchema = new mongoose.Schema({
  username: { type: String, required: true },
  examId: { type: mongoose.Schema.Types.ObjectId, ref: 'Exam', required: true },
  examType: { type: String, enum: ['exam', 'pre-assessment', 'post-assessment'], required: true },
  
  
  currentQuestion: { type: Number, default: 0 },
  answers: { type: Map, of: mongoose.Schema.Types.Mixed, default: {} }, 
  timeLeft: { type: Number, required: true }, 
  timeSpent: { type: Number, default: 0 }, 
  
  
  startedAt: { type: Date, default: Date.now },
  lastSavedAt: { type: Date, default: Date.now },
  isActive: { type: Boolean, default: true },
  
  
  totalQuestions: { type: Number, required: true },
  originalTimeLimit: { type: Number, required: true }, 
}, { 
  timestamps: true,
  
  transform: function(doc, ret) {
    if (ret.answers) {
      ret.answers = Object.fromEntries(ret.answers);
    }
    return ret;
  }
});


examSessionSchema.index({ username: 1, examType: 1 });
examSessionSchema.index({ username: 1, examId: 1 });
examSessionSchema.index({ isActive: 1 });
examSessionSchema.index({ lastSavedAt: 1 }); 


examSessionSchema.methods.updateProgress = function(currentQuestion, answers, timeLeft) {
  this.currentQuestion = currentQuestion;
  this.answers = new Map(Object.entries(answers));
  this.timeLeft = timeLeft;
  this.timeSpent = this.originalTimeLimit - timeLeft;
  this.lastSavedAt = new Date();
  return this.save();
};

examSessionSchema.methods.complete = function() {
  this.isActive = false;
  return this.save();
};


examSessionSchema.statics.findActiveSession = function(username, examType) {
  return this.findOne({ 
    username, 
    examType, 
    isActive: true 
  }).populate('examId');
};

examSessionSchema.statics.createSession = function(username, exam, examType) {
  return this.create({
    username,
    examId: exam._id,
    examType,
    currentQuestion: 0,
    answers: new Map(),
    timeLeft: exam.timeLimit * 60, 
    originalTimeLimit: exam.timeLimit * 60,
    totalQuestions: exam.questions.length,
    isActive: true
  });
};


examSessionSchema.statics.cleanupOldSessions = function() {
  const oneWeekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
  return this.deleteMany({
    $or: [
      { isActive: false, updatedAt: { $lt: oneWeekAgo } },
      { isActive: true, lastSavedAt: { $lt: oneWeekAgo } }
    ]
  });
};

module.exports = mongoose.model('ExamSession', examSessionSchema);
