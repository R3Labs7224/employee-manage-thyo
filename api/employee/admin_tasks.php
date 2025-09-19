<?php
// api/employee/admin_tasks.php - Manage admin-assigned tasks
require_once '../../config/database.php';
require_once '../common/response.php';

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
        getAdminTasks($pdo, $employee);
        break;
    case 'PUT':
        updateTaskAssignment($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getAdminTasks($pdo, $employee) {
    try {
        $status_filter = $_GET['status'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

        // Build query with optional status filter
        $query = "
            SELECT t.id, t.title, t.description, t.priority, t.due_date, t.created_at,
                   ta.status, ta.notes, ta.started_at, ta.completed_at, ta.id as assignment_id,
                   u.username as created_by_username,
                   s.name as site_name,
                   DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display,
                   DATE_FORMAT(ta.started_at, '%Y-%m-%d %H:%i') as started_display,
                   DATE_FORMAT(ta.completed_at, '%Y-%m-%d %H:%i') as completed_display,
                   DATE_FORMAT(t.due_date, '%Y-%m-%d') as due_date_display,
                   CASE
                       WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND ta.status != 'completed'
                       THEN 'overdue'
                       WHEN t.due_date IS NOT NULL AND t.due_date = CURDATE() AND ta.status != 'completed'
                       THEN 'due_today'
                       ELSE 'normal'
                   END as urgency_status
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            LEFT JOIN users u ON t.assigned_by = u.id
            LEFT JOIN sites s ON t.site_id = s.id
            WHERE ta.assigned_to = ? AND t.admin_created = 1
        ";

        $params = [$employee['id']];

        if ($status_filter) {
            $query .= " AND ta.status = ?";
            $params[] = $status_filter;
        }

        $query .= " ORDER BY
                    CASE WHEN ta.status = 'pending' THEN 1
                         WHEN ta.status = 'in_progress' THEN 2
                         WHEN ta.status = 'completed' THEN 3
                         ELSE 4 END,
                    at.due_date ASC NULLS LAST,
                    at.created_at DESC";

        if ($limit) {
            $query .= " LIMIT " . $limit;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get summary statistics
        $summary_stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN ta.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN ta.status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
                SUM(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND ta.status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date = CURDATE() AND ta.status != 'completed' THEN 1 ELSE 0 END) as due_today_tasks
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            WHERE ta.assigned_to = ? AND t.admin_created = 1
        ");
        $summary_stmt->execute([$employee['id']]);
        $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

        $response = [
            'tasks' => $tasks,
            'summary' => $summary
        ];

        sendSuccess('Admin tasks retrieved successfully', $response);

    } catch (PDOException $e) {
        error_log("Get admin tasks error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

function updateTaskAssignment($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        $assignment_id = (int)($input['assignment_id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if (!$assignment_id) {
            sendError('Assignment ID is required', 400);
            return;
        }

        // Validate status
        $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            sendError('Invalid status. Must be one of: ' . implode(', ', $valid_statuses), 400);
            return;
        }

        // Verify assignment exists and belongs to employee
        $stmt = $pdo->prepare("
            SELECT ta.*, t.title, t.description
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = ? AND ta.assigned_to = ? AND t.admin_created = 1
        ");
        $stmt->execute([$assignment_id, $employee['id']]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            sendError('Task assignment not found or access denied', 404);
            return;
        }

        // Prevent changing from completed/cancelled status
        if (in_array($assignment['status'], ['completed', 'cancelled']) && $status !== $assignment['status']) {
            sendError('Cannot change status of completed or cancelled tasks', 400);
            return;
        }

        // Prepare update fields
        $update_fields = ['status = ?', 'notes = ?', 'updated_at = CURRENT_TIMESTAMP'];
        $update_params = [$status, $notes];

        // Set timestamps based on status
        if ($status === 'in_progress' && $assignment['status'] === 'pending') {
            $update_fields[] = 'started_at = CURRENT_TIMESTAMP';
        } elseif ($status === 'completed' && $assignment['status'] !== 'completed') {
            $update_fields[] = 'completed_at = CURRENT_TIMESTAMP';
        } elseif ($status === 'pending' && $assignment['status'] === 'in_progress') {
            // Reset started_at if going back to pending
            $update_fields[] = 'started_at = NULL';
        }

        // Update the assignment
        $stmt = $pdo->prepare("
            UPDATE task_assignments
            SET " . implode(', ', $update_fields) . "
            WHERE id = ? AND assigned_to = ?
        ");

        $update_params[] = $assignment_id;
        $update_params[] = $employee['id'];

        if ($stmt->execute($update_params)) {
            // Log the activity
            logActivity($pdo, $employee['id'], 'admin_task_update', json_encode([
                'assignment_id' => $assignment_id,
                'task_title' => $assignment['title'],
                'old_status' => $assignment['status'],
                'new_status' => $status,
                'notes' => $notes
            ]));

            // Get updated assignment data
            $stmt = $pdo->prepare("
                SELECT t.id, t.title, t.description, t.priority, t.due_date, t.created_at,
                       ta.status, ta.notes, ta.started_at, ta.completed_at, ta.id as assignment_id,
                       u.username as created_by_username,
                       s.name as site_name,
                       DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display,
                       DATE_FORMAT(ta.started_at, '%Y-%m-%d %H:%i') as started_display,
                       DATE_FORMAT(ta.completed_at, '%Y-%m-%d %H:%i') as completed_display,
                       DATE_FORMAT(t.due_date, '%Y-%m-%d') as due_date_display,
                       CASE
                           WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND ta.status != 'completed'
                           THEN 'overdue'
                           WHEN t.due_date IS NOT NULL AND t.due_date = CURDATE() AND ta.status != 'completed'
                           THEN 'due_today'
                           ELSE 'normal'
                       END as urgency_status
                FROM task_assignments ta
                JOIN tasks t ON ta.task_id = t.id
                LEFT JOIN users u ON t.assigned_by = u.id
                LEFT JOIN sites s ON t.site_id = s.id
                WHERE ta.id = ? AND t.admin_created = 1
            ");
            $stmt->execute([$assignment_id]);
            $updated_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            sendSuccess('Task assignment updated successfully', $updated_assignment);
        } else {
            sendError('Failed to update task assignment', 500);
        }

    } catch (PDOException $e) {
        error_log("Update task assignment error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>