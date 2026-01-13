const mongoose = require('mongoose');

const tierConfigSchema = new mongoose.Schema({
  key: { type: String, required: true, enum: ['Tier 1', 'Tier 2'], unique: true },
  label: { type: String, required: true },
  min: { type: Number, required: true, min: 0, max: 100 },
  max: { type: Number, required: true, min: 0, max: 100 },
}, { timestamps: true });

tierConfigSchema.index({ key: 1 }, { unique: true });
tierConfigSchema.index({ label: 1 }, { unique: true });

module.exports = mongoose.model('TierConfig', tierConfigSchema);
