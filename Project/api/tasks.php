<?php
/**
 * Tasks API - CRUD Operations
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
            // Get all tasks for current user
            $subjectId = $_GET['subject_id'] ?? null;
            
            if ($subjectId) {
                // Verify subject belongs to user
                $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ?");
                $stmt->execute([$subjectId, $userId]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    die(json_encode(['success' => false, 'message' => 'Subject not found']));
                }
                
                $stmt = $pdo->prepare("
                    SELECT t.*, s.name as subject_name, s.color as subject_color 
                    FROM tasks t 
                    JOIN subjects s ON t.subject_id = s.id 
                    WHERE t.user_id = ? AND t.subject_id = ? 
                    ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC
                ");
                $stmt->execute([$userId, $subjectId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT t.*, s.name as subject_name, s.color as subject_color 
                    FROM tasks t 
                    JOIN subjects s ON t.subject_id = s.id 
                    WHERE t.user_id = ? 
                    ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC
                ");
                $stmt->execute([$userId]);
            }
            
            $tasks = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $tasks]);
            break;
            
        case 'POST':
            // Create new task
            $input = json_decode(file_get_contents('php://input'), true);
            
            $subjectId = $input['subject_id'] ?? 0;
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $dueDate = $input['due_date'] ?? null;
            $priority = $input['priority'] ?? 'medium';
            $status = $input['status'] ?? 'pending';
            
            if (empty($title) || empty($subjectId)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Title and subject are required']));
            }
            
            // Validate priority and status
            if (!in_array($priority, ['low', 'medium', 'high'])) {
                $priority = 'medium';
            }
            if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
                $status = 'pending';
            }
            
            // Verify subject belongs to user
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ?");
            $stmt->execute([$subjectId, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Subject not found']));
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO tasks (user_id, subject_id, title, description, due_date, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $subjectId, $title, $description, $dueDate, $priority, $status]);
            
            $taskId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT t.*, s.name as subject_name, s.color as subject_color 
                FROM tasks t 
                JOIN subjects s ON t.subject_id = s.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            echo json_encode(['success' => true, 'message' => 'Task created successfully', 'data' => $task]);
            break;
            
        case 'PUT':
            // Update task
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $dueDate = $input['due_date'] ?? null;
            $priority = $input['priority'] ?? 'medium';
            $status = $input['status'] ?? 'pending';
            $subjectId = $input['subject_id'] ?? null;
            
            if (empty($title) || empty($id)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Task ID and title are required']));
            }
            
            // Validate priority and status
            if (!in_array($priority, ['low', 'medium', 'high'])) {
                $priority = 'medium';
            }
            if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
                $status = 'pending';
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Task not found or access denied']));
            }
            
            // If subject_id is being changed, verify new subject belongs to user
            if ($subjectId) {
                $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ?");
                $stmt->execute([$subjectId, $userId]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    die(json_encode(['success' => false, 'message' => 'Subject not found']));
                }
            }
            
            // Build update query dynamically
            $updateFields = ['title = ?', 'description = ?', 'due_date = ?', 'priority = ?', 'status = ?'];
            $params = [$title, $description, $dueDate, $priority, $status];
            
            if ($subjectId) {
                $updateFields[] = 'subject_id = ?';
                $params[] = $subjectId;
            }
            
            $params[] = $id;
            $params[] = $userId;
            
            $sql = "UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $stmt = $pdo->prepare("
                SELECT t.*, s.name as subject_name, s.color as subject_color 
                FROM tasks t 
                JOIN subjects s ON t.subject_id = s.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            
            echo json_encode(['success' => true, 'message' => 'Task updated successfully', 'data' => $task]);
            break;
            
        case 'DELETE':
            // Delete task
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            
            if (empty($id)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Task ID is required']));
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Task not found or access denied']));
            }
            
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Operation failed. Please try again.']);
}
?>


