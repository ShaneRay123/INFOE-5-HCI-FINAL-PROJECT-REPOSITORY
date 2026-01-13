const mongoose = require('mongoose');

const examSchema = new mongoose.Schema({
  title: { type: String, required: true },
  timeLimit: { type: Number, required: true, min: 1, max: 180 },
  passingScore: { type: Number, default: 70, min: 0, max: 100 },
  questions: [{
    id: { type: String, required: true },
    question: { type: String, required: true },
    options: [{ type: String, required: true }],
    correctAnswer: { type: String, required: true },
    explanation: { type: String, default: "" },
    moduleTitle: { type: String, default: "" },
    moduleType: { type: String, default: "" },
    tier: { type: String, default: "" }
  }],
  isActive: { type: Boolean, default: true }
}, { timestamps: true });

examSchema.index({ isActive: 1 });

module.exports = mongoose.model("Exam", examSchema);
