<?php
/**
 * User Login API
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Redirect if already logged in
if (isLoggedIn()) {
    die(json_encode(['success' => false, 'message' => 'Already logged in']));
}

try {
    $pdo = getDBConnection();
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Username and password are required']));
    }
    
    // Get user from database
    $stmt = $pdo->prepare("SELECT id, username, email, password, full_name FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Invalid username or password']));
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
}
?>


