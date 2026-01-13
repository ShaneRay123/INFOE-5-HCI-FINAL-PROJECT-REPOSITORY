<?php
// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to URL
function redirect($url) {
    header("Location: $url");
    exit;
}

// Check user role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'student';
}

// Sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Flash messages
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Get user details
function getUserDetails($user_id) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    
    return null;
}

// Format date for display
function formatDate($date) {
    return date("F j, Y", strtotime($date));
}

// Format time for display
function formatTime($time) {
    return date("g:i A", strtotime($time));
}
?>
