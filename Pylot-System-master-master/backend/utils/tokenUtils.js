const jwt = require('jsonwebtoken');
const config = require('../config/config');


const generateAccessToken = (userId) => {
  return jwt.sign(
    { id: userId },
    config.JWT_SECRET,
    { expiresIn: config.JWT_EXPIRES_IN }
  );
};


const generateRefreshToken = (userId) => {
  return jwt.sign(
    { id: userId, type: 'refresh' },
    config.JWT_REFRESH_SECRET,
    { expiresIn: config.JWT_REFRESH_EXPIRES_IN }
  );
};


const generateTokens = (userId) => {
  return {
    accessToken: generateAccessToken(userId),
    refreshToken: generateRefreshToken(userId)
  };
};


const verifyRefreshToken = (token) => {
  return jwt.verify(token, config.JWT_REFRESH_SECRET);
};

module.exports = {
  generateAccessToken,
  generateRefreshToken,
  generateTokens,
  verifyRefreshToken
};
