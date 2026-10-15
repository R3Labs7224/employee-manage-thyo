<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

requireLogin();

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$task_id = (int)($_GET['task_id'] ?? 0);

if (!$task_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit;
}

try {
    // Get task details from unified table
    $stmt = $pdo->prepare("SELECT title, description FROM tasks WHERE id = ? AND task_type = 'admin_assigned'");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }

    // Get all tasks with same title and assigned_by to show all assignments
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.status, t.notes, t.started_at, t.completed_at,
               e.name as display_name,
               e.employee_code,
               u.username,
               u.role
        FROM tasks t
        JOIN employees e ON t.assigned_to_employee_id = e.id
        LEFT JOIN users u ON t.assigned_by_user_id = u.id
        WHERE t.title = ? AND t.task_type = 'admin_assigned'
        ORDER BY e.name
    ");
    $stmt->execute([$task['title']]);
    $assignments = $stmt->fetchAll();

    // Format assignments to match expected structure
    $formatted_assignments = [];
    foreach ($assignments as $assignment) {
        $formatted_assignments[] = [
            'id' => $assignment['id'],
            'status' => $assignment['status'],
            'notes' => $assignment['notes'],
            'started_at' => $assignment['started_at'],
            'completed_at' => $assignment['completed_at'],
            'username' => $assignment['username'] ?? 'N/A',
            'display_name' => $assignment['display_name'],
            'employee_code' => $assignment['employee_code'] ?? 'N/A',
            'role' => $assignment['role'] ?? 'employee'
        ];
    }

    echo json_encode([
        'success' => true,
        'task' => $task,
        'assignments' => $formatted_assignments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>