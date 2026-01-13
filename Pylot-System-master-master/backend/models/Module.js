const mongoose = require('mongoose');


const subModuleSchema = new mongoose.Schema({
  subModuleId: { type: String, required: true }, 
  title: { type: String, required: true },
  description: { type: String, default: "" },
  content: { type: String, default: "" }, 
  isActive: { type: Boolean, default: true }
}, { timestamps: true });


const moduleSchema = new mongoose.Schema({
  moduleId: { type: String, required: true }, 
  title: { type: String, required: true },
  description: { type: String, default: "" },
  content: { type: String, default: "" }, 
  contentFileId: { type: mongoose.Schema.Types.ObjectId, ref: 'uploads.files' },
  scoreRange: { 
    min: { type: Number, required: true }, 
    max: { type: Number, required: true }  
  },
  isActive: { type: Boolean, default: true },
  tier: { type: String, enum: ['Tier 1', 'Tier 2'], default: 'Tier 1' },
  
  subModules: [subModuleSchema],
  
  checkpointQuiz: {
    isCheckpointQuiz: { type: Boolean, default: false }, 
    checkpointNumber: { type: Number }, 
    requiredModulesCount: { type: Number, default: 4 }, 
    requiredModuleIds: [{ type: String }], 
    questions: [{
      id: { type: String, required: true },
      question: { type: String, required: true },
      options: [{ type: String, required: true }],
      correctAnswer: { type: String, required: true },
      explanation: { type: String, default: "" }
    }],
    passingScore: { type: Number, default: 70, min: 0, max: 100 },
    timeLimit: { type: Number, default: 15, min: 1, max: 60 } 
  }
}, { timestamps: true });


moduleSchema.index({ moduleId: 1 });
moduleSchema.index({ 'scoreRange.min': 1, 'scoreRange.max': 1 });
moduleSchema.index({ 'checkpointQuiz.checkpointNumber': 1 });
moduleSchema.index({ title: 1, tier: 1 });

module.exports = mongoose.model("Module", moduleSchema);
