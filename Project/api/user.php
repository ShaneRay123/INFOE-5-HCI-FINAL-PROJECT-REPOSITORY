<?php
/**
 * User API
 * GET: Fetch current user info
 * POST: Update user profile
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

requireAuth();
$userId = getUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();

    if ($method === 'GET') {
        // Fetch user data
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, bio, phone, avatar_url, job_title, location, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'User not found']));
        }
        
        echo json_encode(['success' => true, 'data' => $user]);

    } elseif ($method === 'POST') {
        // Update user profile
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid input']));
        }

        // Prepare update fields
        $updates = [];
        $params = [];

        // Allow updating specific fields
        $allowedFields = ['full_name', 'bio', 'phone', 'avatar_url', 'job_title', 'location'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
            exit;
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            throw new Exception("Failed to update profile");
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    error_log("User API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
