const mongoose = require('mongoose');
const bcrypt = require('bcrypt');

const userSchema = new mongoose.Schema({
  username: { type: String, required: function() { return !this.googleId; }, unique: true },
  email: { type: String, lowercase: true, trim: true, sparse: true },
  password: { 
    type: String, 
    required: function() { 
      return !this.googleId && !this.isOAuthUser; 
    } 
  },
  googleId: { type: String, sparse: true },
  authProvider: { 
    type: String, 
    enum: ['local', 'google'], 
    default: 'local' 
  },
  isOAuthUser: { type: Boolean, default: false },
  role: { type: String, enum: ['user', 'admin'], default: 'user' },
  isApproved: { type: Boolean, default: true },
  isDeleted: { type: Boolean, default: false },
  deletedAt: { type: Date },

  firstName: {
    type: String,
    required: function () { return this.role !== 'admin'; }
  },
  middleName: { type: String },
  lastName: {
    type: String,
    required: function () { return this.role !== 'admin'; }
  },

  studentId: {
    type: String,
    unique: true,
    required: function () { return this.role !== 'admin'; }
  },

  lastLogin: { type: Date },
  lastActive: { type: Date },
  isOnline: { type: Boolean, default: false },

  age: {
    type: Number,
    min: 1,
    max: 150,
    required: function () { return this.role !== 'admin'; }
  },
  address: {
    type: String,
    required: function () { return this.role !== 'admin'; }
  },
  gender: {
    type: String,
    enum: ['Male', 'Female', 'Other'],
    required: function () { return this.role !== 'admin'; }
  }
}, { timestamps: true });

userSchema.set('toObject', { virtuals: true });
userSchema.set('toJSON', { virtuals: true });

userSchema.virtual('fullName').get(function() {
  const parts = [this.firstName, this.middleName, this.lastName]
    .filter(Boolean)
    .map(s => String(s).trim())
    .filter(s => s.length > 0);
  return parts.join(' ');
});

userSchema.index({ role: 1, isApproved: 1 });
userSchema.index({ isDeleted: 1 });
userSchema.index({ role: 1, isDeleted: 1 });
userSchema.index({ createdAt: -1 });
userSchema.index({ email: 1 }, { unique: true, sparse: true });

userSchema.pre('save', async function(next) {

  if (this.isModified('username') && typeof this.username === 'string') {
    this.username = this.username.trim().toLowerCase();
  }
  if (this.isModified('email') && typeof this.email === 'string' && this.email) {
    this.email = this.email.trim().toLowerCase();
  }

  if (this.isModified('password')) {
    try {
      const hashedPassword = await bcrypt.hash(this.password, 12);
      this.password = hashedPassword;
    } catch (error) {
      return next(error);
    }
  }
  return next();
});

userSchema.methods.comparePassword = async function(candidatePassword) {
  return bcrypt.compare(candidatePassword, this.password);
};

userSchema.methods.softDelete = function() {
  this.isDeleted = true;
  this.deletedAt = new Date();
  return this.save();
};

userSchema.pre(/^find/, function(next) {

  if (!this.getQuery().hasOwnProperty('isDeleted')) {
    this.where({ isDeleted: { $ne: true } });
  }
  next();
});

userSchema.methods.toJSON = function() {
  const userObject = this.toObject();
  delete userObject.password;
  return userObject;
};

userSchema.pre('findOneAndDelete', async function(next) {
  try {
    const user = await this.model.findOne(this.getQuery());
    if (user) {
      const UserProgress = require('./UserProgress');
      const Result = require('./Result');
      const Certificate = require('./Certificate');

      await Promise.all([
        UserProgress.deleteMany({ username: user.username }),
        Result.deleteMany({ username: user.username }),
        Certificate.deleteMany({ username: user.username })
      ]);

      console.log(`Cascading delete completed for user: ${user.username}`);
    }
    next();
  } catch (error) {
    console.error('Error during cascading delete:', error);
    next(error);
  }
});

userSchema.pre('deleteOne', { document: true }, async function(next) {
  try {
    const UserProgress = require('./UserProgress');
    const Result = require('./Result');
    const Certificate = require('./Certificate');

    await Promise.all([
      UserProgress.deleteMany({ username: this.username }),
      Result.deleteMany({ username: this.username }),
      Certificate.deleteMany({ username: this.username })
    ]);

    console.log(`Cascading delete completed for user: ${this.username}`);
    next();
  } catch (error) {
    console.error('Error during cascading delete:', error);
    next(error);
  }
});

module.exports = mongoose.model('User', userSchema);

