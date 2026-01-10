require('dotenv').config();

const config = {
  
  PORT: process.env.PORT || 5000,
  NODE_ENV: process.env.NODE_ENV || 'development',
  
  
  MONGO_URI: process.env.MONGO_URI,
  
  
  JWT_SECRET: process.env.JWT_SECRET || 'your-fallback-secret-key-change-in-production',
  JWT_EXPIRES_IN: process.env.JWT_EXPIRES_IN || '7d',
  JWT_REFRESH_SECRET: process.env.JWT_REFRESH_SECRET || 'your-refresh-secret-change-in-production',
  JWT_REFRESH_EXPIRES_IN: process.env.JWT_REFRESH_EXPIRES_IN || '30d',
  
  
  BCRYPT_SALT_ROUNDS: parseInt(process.env.BCRYPT_SALT_ROUNDS) || 12,
  
  
  RATE_LIMIT_WINDOW_MS: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000, 
  RATE_LIMIT_MAX_REQUESTS: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100,
  
  
  FRONTEND_URL: process.env.FRONTEND_URL || 'http://localhost:3000',
  
  
  HEARTBEAT_STALE_SECONDS: parseInt(process.env.HEARTBEAT_STALE_SECONDS) || 10,
  
  // Google OAuth Configuration
  google: {
    clientId: process.env.GOOGLE_CLIENT_ID || '291303377694-d0gaqmc7cntiovt57931h6oihcro9sqi.apps.googleusercontent.com',
  },
  
  
  isDevelopment: process.env.NODE_ENV === 'development',
  isProduction: process.env.NODE_ENV === 'production'
};


const requiredEnvVars = ['MONGO_URI'];
const missingEnvVars = requiredEnvVars.filter(envVar => !process.env[envVar]);

if (missingEnvVars.length > 0) {
  console.error(`? Missing required environment variables: ${missingEnvVars.join(', ')}`);
  process.exit(1);
}


if (config.isProduction) {
  if (config.JWT_SECRET.includes('fallback') || config.JWT_REFRESH_SECRET.includes('refresh-secret')) {
    console.warn('??  WARNING: Using default JWT secrets in production. Please set JWT_SECRET and JWT_REFRESH_SECRET environment variables.');
  }
}

module.exports = config;
