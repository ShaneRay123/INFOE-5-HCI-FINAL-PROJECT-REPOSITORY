const express = require('express');
const { OAuth2Client } = require('google-auth-library');
const User = require('../models/User');
const { generateTokens } = require('../utils/tokenUtils');
const { catchAsync, AppError, sendResponse } = require('../middleware/errorHandler');
const config = require('../config/config');

const router = express.Router();
const client = new OAuth2Client(config.google.clientId);

// Google OAuth verification
const verifyGoogleToken = async (token) => {
  try {
    const ticket = await client.verifyIdToken({
      idToken: token,
      audience: config.google.clientId,
    });
    return ticket.getPayload();
  } catch (error) {
    console.error('Error verifying Google token:', error);
    throw new AppError('Invalid Google token', 401);
  }
};

// Google OAuth login/signup
router.post('/google', catchAsync(async (req, res) => {
  const { token } = req.body;
  
  if (!token) {
    throw new AppError('Google token is required', 400);
  }

  // Verify the Google token
  const googleUser = await verifyGoogleToken(token);
  
  // Check if user already exists
  let user = await User.findOne({ 
    $or: [
      { email: googleUser.email },
      { googleId: googleUser.sub }
    ]
  });

  // If user doesn't exist, create a new one
  if (!user) {
    // Generate a unique username from email
    const baseUsername = googleUser.email.split('@')[0];
    let username = baseUsername;
    let usernameExists = await User.findOne({ username });
    
    // If username exists, append a number to make it unique
    let counter = 1;
    while (usernameExists) {
      username = `${baseUsername}${counter}`;
      // eslint-disable-next-line no-await-in-loop
      usernameExists = await User.findOne({ username });
      counter += 1;
    }

    // Create new user with Google data
    user = new User({
      username,
      email: googleUser.email,
      googleId: googleUser.sub,
      firstName: googleUser.given_name || '',
      lastName: googleUser.family_name || '',
      isOAuthUser: true,
      authProvider: 'google',
      // These fields are marked as required in the schema but may not be available from Google
      // We'll set default values for required fields
      studentId: `google_${googleUser.sub.substring(0, 8)}`,
      address: 'Not provided',
      age: 18, // Default age
      gender: 'Other', // Default gender
      isApproved: true // Auto-approve Google users
    });

    await user.save();
  } else if (!user.googleId) {
    // If user exists but doesn't have googleId, update with Google data
    user.googleId = googleUser.sub;
    user.authProvider = 'google';
    user.isOAuthUser = true;
    
    // Update name if not already set
    if (!user.firstName && googleUser.given_name) {
      user.firstName = googleUser.given_name;
    }
    if (!user.lastName && googleUser.family_name) {
      user.lastName = googleUser.family_name;
    }
    
    await user.save();
  }

  // Generate JWT tokens
  const { accessToken, refreshToken } = await generateTokens({
    userId: user._id,
    username: user.username,
    role: user.role
  });

  // Update last login time
  user.lastLogin = new Date();
  await user.save();

  // Return tokens and user info
  sendResponse(res, 200, {
    success: true,
    accessToken,
    refreshToken,
    user: {
      id: user._id,
      username: user.username,
      email: user.email,
      firstName: user.firstName,
      lastName: user.lastName,
      role: user.role,
      isOAuthUser: user.isOAuthUser,
      authProvider: user.authProvider
    }
  });
}));

module.exports = router;
