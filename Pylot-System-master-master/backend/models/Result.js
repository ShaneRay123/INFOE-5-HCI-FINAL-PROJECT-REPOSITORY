const mongoose = require('mongoose');

const resultSchema = new mongoose.Schema(
  {
    username: { type: String, required: true },
    examId: { type: mongoose.Schema.Types.ObjectId, ref: "Exam", required: true },
    examTitle: { type: String, required: true },
    examSubject: { type: String, required: true },
    examDifficulty: { type: String, enum: ["Beginner", "Intermediate", "Advanced"], required: true },
    totalQuestions: { type: Number, required: true },
    correctAnswers: { type: Number, required: true },
    percentage: { type: Number, required: true },
    passed: { type: Boolean, required: true },
    timeSpent: { type: Number, default: 0 }, 
    passingScore: { type: Number, default: 70 }, 
    answers: { type: Object, default: {} },
  },
  { timestamps: true }
);


resultSchema.index({ username: 1 });
resultSchema.index({ examId: 1 });
resultSchema.index({ username: 1, examId: 1 });

module.exports = mongoose.model("Result", resultSchema);
