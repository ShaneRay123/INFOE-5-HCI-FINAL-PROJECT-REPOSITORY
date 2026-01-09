<?php
/**
 * User Registration API
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
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $fullName = trim($input['full_name'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => implode(', ', $errors)]));
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        die(json_encode(['success' => false, 'message' => 'Username or email already exists']));
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $fullName]);
    
    // Get the new user ID
    $userId = $pdo->lastInsertId();
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $fullName;
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'full_name' => $fullName
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
?>


