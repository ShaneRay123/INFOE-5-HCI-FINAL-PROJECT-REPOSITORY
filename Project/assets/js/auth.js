/**
 * Authentication JavaScript
 * Handles login, registration, and logout
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const logoutBtn = document.getElementById('logoutBtn');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
});

/**
 * Handle login form submission
 */
async function handleLogin(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    const errorMessage = document.getElementById('errorMessage');
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    // Show loading state
    submitBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoader) btnLoader.style.display = 'inline-block';
    if (errorMessage) errorMessage.style.display = 'none';
    
    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect to dashboard
            window.location.href = 'dashboard.php';
        } else {
            // Show error message
            if (errorMessage) {
                errorMessage.textContent = data.message || 'Login failed. Please try again.';
                errorMessage.style.display = 'block';
            } else {
                alert(data.message || 'Login failed');
            }
            
            // Reset button state
            submitBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnLoader) btnLoader.style.display = 'none';
        }
    } catch (error) {
        console.error('Login error:', error);
        if (errorMessage) {
            errorMessage.textContent = 'An error occurred. Please try again.';
            errorMessage.style.display = 'block';
        } else {
            alert('An error occurred');
        }
        
        // Reset button state
        submitBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoader) btnLoader.style.display = 'none';
    }
}

/**
 * Handle registration form submission
 */
async function handleRegister(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    const errorMessage = document.getElementById('errorMessage');
    
    const fullName = document.getElementById('full_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    // Validate password length
    if (password.length < 6) {
        if (errorMessage) {
            errorMessage.textContent = 'Password must be at least 6 characters long.';
            errorMessage.style.display = 'block';
        } else {
            alert('Password must be at least 6 characters long.');
        }
        return;
    }
    
    // Validate username length
    if (username.length < 3) {
        if (errorMessage) {
            errorMessage.textContent = 'Username must be at least 3 characters long.';
            errorMessage.style.display = 'block';
        } else {
            alert('Username must be at least 3 characters long.');
        }
        return;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoader) btnLoader.style.display = 'inline-block';
    if (errorMessage) errorMessage.style.display = 'none';
    
    try {
        const response = await fetch('api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ full_name: fullName, username, email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect to dashboard
            window.location.href = 'dashboard.php';
        } else {
            // Show error message
            if (errorMessage) {
                errorMessage.textContent = data.message || 'Registration failed. Please try again.';
                errorMessage.style.display = 'block';
            } else {
                alert(data.message || 'Registration failed');
            }
            
            // Reset button state
            submitBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnLoader) btnLoader.style.display = 'none';
        }
    } catch (error) {
        console.error('Registration error:', error);
        if (errorMessage) {
            errorMessage.textContent = 'An error occurred. Please try again.';
            errorMessage.style.display = 'block';
        } else {
            alert('An error occurred');
        }
        
        // Reset button state
        submitBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoader) btnLoader.style.display = 'none';
    }
}

/**
 * Handle logout
 */
async function handleLogout(e) {
    if (e) e.preventDefault();
    try {
        await fetch('api/logout.php');
        window.location.href = 'login.php';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'login.php';
    }
}

/**
 * Fetch wrapper for authenticated requests
 * (Since we use session auth, this is just a wrapper for fetch,
 * but useful if we ever switch to token-based auth or need global error handling)
 */
async function fetchWithAuth(url, options = {}) {
    const defaultOptions = {
        credentials: 'same-origin'
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, mergedOptions);
        
        if (response.status === 401 || response.status === 403) {
            // Session expired or unauthorized
            window.location.href = 'login.php';
            throw new Error('Unauthorized');
        }
        
        return response;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}
