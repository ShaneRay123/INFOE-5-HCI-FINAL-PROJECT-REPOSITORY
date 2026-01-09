<?php
/**
 * Authentication Helper Functions
 */

session_start();

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Require user to be logged in, redirect if not
 */
function requireAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Authentication required']));
    }
}

/**
 * Redirect to login if not authenticated (for HTML pages)
 */
function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirect to dashboard if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}
?>


