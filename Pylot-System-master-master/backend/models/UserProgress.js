const mongoose = require('mongoose');

const userProgressSchema = new mongoose.Schema({
  username: { type: String, required: true },
  hasCompletedPreAssessment: { type: Boolean, default: false },
  preAssessmentScore: { type: Number, default: 0 },
  preAssessmentCompletedAt: { type: Date },
  hasCompletedPostAssessment: { type: Boolean, default: false },
  postAssessmentScore: { type: Number, default: 0 },
  postAssessmentCompletedAt: { type: Date },
  
  postAssessmentPassed: { type: Boolean, default: false },

  moduleTypeScores: { type: Object, default: {} },
  userModuleScores: { type: Object, default: {} },

  moduleScores: { type: Object, default: {} }, 
  overallModuleScore: { type: Number, default: 0 }, 

  assignedModules: [{ type: String }], 
  assignedModules_old: [{ type: String }], 
  advancedUser: { type: Boolean, default: false },
  
  postAssessmentEarlyDeclined: { type: Boolean, default: false },
  postAssessmentEarlyDeclinedAt: { type: Date },
  accessibleModules: [{ type: String }], 
  completedModules: [{ type: String }], 
  lastModuleCompletedAt: { type: Date }, 
  currentModule: { type: String, default: null },
  
  completedCheckpointQuizzes: [{ 
    moduleId: { type: String, required: true }, 
    checkpointNumber: { type: Number, required: true }, 
    score: { type: Number, required: true },
    passed: { type: Boolean, required: true },
    completedAt: { type: Date, default: Date.now }
  }],
  
  currentCheckpointGroup: { type: Number, default: 1 }
}, { timestamps: true });


userProgressSchema.index({ username: 1 });
userProgressSchema.index({ 'completedCheckpointQuizzes.checkpointNumber': 1 });

module.exports = mongoose.model("UserProgress", userProgressSchema);
