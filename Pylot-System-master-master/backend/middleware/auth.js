const jwt = require('jsonwebtoken');
const User = require('../models/User');
const config = require('../config/config');


const authenticateToken = async (req, res, next) => {
  try {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1]; 

    if (!token) {
      return res.status(401).json({
        message: 'No token provided.'
      });
    }

    const decoded = jwt.verify(token, config.JWT_SECRET);
    
    const user = await User.findById(decoded.id)
      .select('username role isApproved firstName middleName lastName')
      .lean();
    if (!user) {
      return res.status(401).json({
        message: 'Invalid token'
      });
    }


    req.user = user;
    next();
  } catch (error) {
    if (error.name === 'TokenExpiredError') {

      try {
        const authHeader = req.headers['authorization'];
        const tokenStr = authHeader && authHeader.split(' ')[1];
        const decoded = jwt.decode(tokenStr);
        if (decoded && decoded.id) {
          await User.findByIdAndUpdate(decoded.id, { isOnline: false }).setOptions({ skipFilter: true });
        }
      } catch {}
      return res.status(401).json({
        message: 'Token expired'
      });
    }
    if (error.name === 'JsonWebTokenError') {
      return res.status(401).json({
        message: 'Invalid token'
      });
    }
    
    return res.status(500).json({
      message: 'Token verification failed'
    });
  }
};


const requireAdmin = (req, res, next) => {
  const role = req.user && req.user.role ? String(req.user.role).toLowerCase() : '';
  if (role === 'admin') {
    return next();
  }
  return res.status(403).json({
    success: false,
    message: 'Admin access required'
  });
};


const requireApproved = (req, res, next) => {
  if (req.user && (req.user.isApproved || req.user.role === 'admin')) {
    next();
  } else {
    return res.status(403).json({
      success: false,
      message: 'Account requires admin approval'
    });
  }
};


const optionalAuth = async (req, res, next) => {
  try {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (token) {
      const decoded = jwt.verify(token, config.JWT_SECRET);
      const user = await User.findById(decoded.id)
        .select('username role isApproved fullName')
        .lean();
      if (user && (user.isApproved || user.role === 'admin')) {
        req.user = user;
      }
    }
    next();
  } catch (error) {
    
    next();
  }
};

module.exports = {
  authenticateToken,
  requireAdmin,
  requireApproved,
  optionalAuth
};
