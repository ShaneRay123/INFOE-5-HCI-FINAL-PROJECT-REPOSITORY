<?php
/**
 * Subjects API - CRUD Operations
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

requireAuth();
$userId = getUserId();
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get all subjects for current user
            $stmt = $pdo->prepare("SELECT * FROM subjects WHERE user_id = ? ORDER BY name ASC");
            $stmt->execute([$userId]);
            $subjects = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $subjects]);
            break;
            
        case 'POST':
            // Create new subject
            $input = json_decode(file_get_contents('php://input'), true);
            
            $name = trim($input['name'] ?? '');
            $color = trim($input['color'] ?? '#3498db');
            $description = trim($input['description'] ?? '');
            
            if (empty($name)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Subject name is required']));
            }
            
            // Validate color format
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                $color = '#3498db';
            }
            
            $stmt = $pdo->prepare("INSERT INTO subjects (user_id, name, color, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $color, $description]);
            
            $subjectId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
            $stmt->execute([$subjectId]);
            $subject = $stmt->fetch();
            
            echo json_encode(['success' => true, 'message' => 'Subject created successfully', 'data' => $subject]);
            break;
            
        case 'PUT':
            // Update subject
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            
            $name = trim($input['name'] ?? '');
            $color = trim($input['color'] ?? '#3498db');
            $description = trim($input['description'] ?? '');
            
            if (empty($name) || empty($id)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Subject ID and name are required']));
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Subject not found or access denied']));
            }
            
            // Validate color format
            if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                $color = '#3498db';
            }
            
            $stmt = $pdo->prepare("UPDATE subjects SET name = ?, color = ?, description = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $color, $description, $id, $userId]);
            
            $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
            $stmt->execute([$id]);
            $subject = $stmt->fetch();
            
            echo json_encode(['success' => true, 'message' => 'Subject updated successfully', 'data' => $subject]);
            break;
            
        case 'DELETE':
            // Delete subject
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            
            if (empty($id)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Subject ID is required']));
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Subject not found or access denied']));
            }
            
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Subject deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log("Subjects API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Operation failed. Please try again.']);
}
?>


