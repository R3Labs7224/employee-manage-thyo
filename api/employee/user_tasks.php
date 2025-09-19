<?php
require_once '../../config/database.php';
require_once '../common/response.php';

// Verify authentication
$token = getAuthHeader();
if (!$token) {
    sendError('Authorization token required', 401);
}

$employee = verifyEmployeeToken($pdo, $token);
if (!$employee) {
    sendError('Invalid or expired token', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUserTasks($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getUserTasks($pdo, $employee) {
    try {
        // Get all tasks for the employee (both self-created and assigned)
        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.title,
                t.status,
                t.created_at,
                s.name as site_name,
                CASE
                    WHEN t.admin_created = 1 THEN 'assigned'
                    ELSE 'self_created'
                END as task_type
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
            WHERE (
                -- Self-created tasks
                t.employee_id = ? OR
                -- Admin-assigned tasks (only those with valid assignment records)
                (t.admin_created = 1 AND ta.assigned_to = ? AND ta.id IS NOT NULL)
            )
            AND t.status IN ('active', 'completed')
            ORDER BY t.created_at DESC
            LIMIT 50
        ");

        $stmt->execute([
            $employee['id'], // for task_assignments join
            $employee['id'], // for self-created tasks
            $employee['id']  // for admin-assigned tasks
        ]);
        $tasks = $stmt->fetchAll();

        sendSuccess('User tasks retrieved successfully', $tasks);

    } catch (PDOException $e) {
        error_log("Get user tasks error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>