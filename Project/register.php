<?php
require_once 'config/auth.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Study Planner</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>📚 Study Planner</h1>
                <p>Create your account to get started</p>
            </div>
            
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required autocomplete="name">
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
                    <small style="color: var(--text-light);">At least 3 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                    <small style="color: var(--text-light);">At least 6 characters</small>
                </div>
                
                <div id="errorMessage" class="error-message" style="display: none; color: var(--danger-color); margin-bottom: 1rem;"></div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <span class="btn-text">Register</span>
                    <span class="btn-loader" style="display: none;">Loading...</span>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>
