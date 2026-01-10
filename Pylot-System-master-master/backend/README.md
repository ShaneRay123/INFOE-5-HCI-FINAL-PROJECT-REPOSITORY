# Pylot System Backend

A secure, modern Node.js backend for the Pylot learning management system with JWT authentication, input validation, and comprehensive API endpoints.

## 🚀 Quick Start

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Set up environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your MongoDB URI and JWT secrets
   ```

3. **Start the server:**
   ```bash
   npm start
   ```

4. **Health check:**
   ```bash
   curl http://localhost:5000/health
   ```

## 🔒 Security Features

- **Password Hashing**: Bcrypt with salt rounds (12)
- **JWT Authentication**: Access & refresh tokens
- **Input Validation**: Express-validator for all routes
- **Rate Limiting**: Development-friendly limits
- **Security Headers**: Helmet.js for HTTP security
- **CORS**: Configured for frontend compatibility

## 📁 Project Structure

```
backend/
├── config/
│   └── config.js              # Environment configuration
├── middleware/
│   ├── auth.js                # JWT authentication
│   └── errorHandler.js        # Global error handling
├── models/
│   ├── User.js                # User model with password hashing
│   ├── Module.js              # Learning modules
│   ├── Exam.js                # Assessments
│   ├── Result.js              # Exam results
│   └── UserProgress.js        # Progress tracking
├── routes/
│   ├── auth.js                # Authentication endpoints
│   └── admin.js               # Admin management
├── utils/
│   └── tokenUtils.js          # JWT utilities
└── server-new.js              # Main server file
```

## 📊 API Endpoints

### Authentication
- `POST /login` - User login (legacy)
- `POST /signup` - User signup (legacy)
- `POST /api/auth/login` - Secure login with JWT
- `POST /api/auth/signup` - Secure signup with JWT
- `POST /api/auth/refresh` - Refresh JWT tokens
- `GET /api/auth/me` - Get current user

### Modules
- `GET /api/modules` - List all active modules
- `GET /api/modules/user/:username` - User-specific modules
- `GET /api/modules/:id` - Get specific module
- `GET /api/modules/by-score/:score` - Modules by score range

### Exams & Results
- `GET /api/exams` - List all exams
- `POST /api/exams/:id/submit` - Submit exam
- `GET /api/results` - Get results
- `GET /api/results/latest` - Latest result for user

### Admin
- `GET /admin/users` - List users
- `POST /admin/users/:id/approve` - Approve user
- `PUT /admin/users/:id` - Update user
- `DELETE /admin/users/:id` - Delete user

### Checkpoint Quizzes
- `GET /api/checkpoint-quizzes` - List checkpoint quizzes
- `POST /api/checkpoint-quizzes/:number/submit` - Submit quiz

## 🔧 Environment Variables

Required variables in `.env`:

```bash
# Database
MONGO_URI=mongodb://localhost:27017/pylot-system

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key
JWT_REFRESH_SECRET=your-super-secret-refresh-key

# Server
PORT=5000
NODE_ENV=development

# CORS
FRONTEND_URL=http://localhost:3000
```

## 🛠️ Development

### Start Development Server
```bash
npm run dev
```

### Available Scripts
- `npm start` - Start production server
- `npm run dev` - Start development server

## 🎯 Current Data

Your system includes:
- **👥 Users**: 4 users (including admin)
- **📚 Modules**: 28 Python learning modules
- **📝 Exams**: 2 assessments (Pre & Post)
- **📊 Results**: User test results and progress
- **🎯 Checkpoint Quizzes**: Integrated learning checkpoints

## 🔄 Migration Completed

✅ All data has been migrated to the new secure structure:
- User passwords are hashed with bcrypt
- JWT authentication system implemented
- All legacy API endpoints maintained for compatibility
- Database optimized with proper indexes

## 🚨 Important Notes

1. **Migration Complete**: Fully migrated to secure server architecture
2. **Legacy Compatibility**: All old API endpoints work without authentication
3. **New Security**: JWT-based authentication available for new features
4. **Development Ready**: Rate limiting disabled for localhost

## 📞 Support

For issues or questions:
- Check the API documentation in this README
- Review the migration guide for troubleshooting
- Ensure all environment variables are properly configured