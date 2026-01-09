<?php
require_once 'config/auth.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Study Planner</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>📚 Study Planner</h1>
                <p>Login to manage your study schedule</p>
            </div>
            
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                
                <div id="errorMessage" class="error-message" style="display: none; color: var(--danger-color); margin-bottom: 1rem;"></div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <span class="btn-text">Login</span>
                    <span class="btn-loader" style="display: none;">Loading...</span>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>
