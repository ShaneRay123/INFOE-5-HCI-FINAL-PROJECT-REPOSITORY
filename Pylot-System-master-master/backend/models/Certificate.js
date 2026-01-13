const mongoose = require('mongoose');

const certificateSchema = new mongoose.Schema({
  
  certificateId: { 
    type: String, 
    required: true, 
    unique: true,
    default: () => `CERT-${Date.now()}-${Math.random().toString(36).substr(2, 9).toUpperCase()}`
  },
  
  
  username: { type: String, required: true, ref: 'User' },
  fullName: { type: String, required: true },
  
  
  courseName: { type: String, required: true, default: 'Pylot System Certification Course' },
  completionDate: { type: Date, required: true, default: Date.now },
  issueDate: { type: Date, required: true, default: Date.now },
  
  
  preAssessmentScore: { type: Number, required: true, min: 0, max: 100 },
  postAssessmentScore: { type: Number, required: true, min: 0, max: 100 },
  improvementScore: { type: Number, required: true }, 
  
  
  modulesCompleted: { type: Number, required: true },
  totalModules: { type: Number, required: true },
  completionPercentage: { type: Number, required: true, min: 0, max: 100 },
  
  
  isValid: { type: Boolean, default: true },
  validationHash: { type: String, required: false }, 
  
  
  pdfBuffer: { type: Buffer }, 
  pdfPath: { type: String }, 
  
  
  templateVersion: { type: String, default: 'v1.0' },
  generatedBy: { type: String, default: 'Pylot System' }
}, { 
  timestamps: true 
});


certificateSchema.index({ username: 1 });

certificateSchema.index({ validationHash: 1 });


certificateSchema.pre('save', function(next) {
  if (!this.validationHash) {
    const crypto = require('crypto');
    const data = `${this.certificateId}-${this.username}-${this.completionDate.toISOString()}-${this.postAssessmentScore}`;
    this.validationHash = crypto.createHash('sha256').update(data).digest('hex');
  }
  next();
});


certificateSchema.virtual('certificateUrl').get(function() {
  return `/api/certificates/${this.certificateId}/download`;
});


certificateSchema.virtual('verificationUrl').get(function() {
  return `/api/certificates/verify/${this.validationHash}`;
});


certificateSchema.methods.toJSON = function() {
  const certificateObject = this.toObject({ virtuals: true });
  delete certificateObject.pdfBuffer; 
  return certificateObject;
};

module.exports = mongoose.model('Certificate', certificateSchema);
