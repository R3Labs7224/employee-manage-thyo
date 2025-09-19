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
    // Get task details
    $stmt = $pdo->prepare("SELECT title, description FROM admin_tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }

    // Get task assignments
    $stmt = $pdo->prepare("
        SELECT ta.*,
               u.username,
               COALESCE(e.name, u.username) as display_name,
               COALESCE(e.employee_code, 'N/A') as employee_code,
               u.role
        FROM task_assignments ta
        JOIN users u ON ta.assigned_to = u.id
        LEFT JOIN employees e ON u.username = e.email OR u.id = e.id
        WHERE ta.task_id = ?
        ORDER BY u.role, display_name
    ");
    $stmt->execute([$task_id]);
    $assignments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'task' => $task,
        'assignments' => $assignments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>