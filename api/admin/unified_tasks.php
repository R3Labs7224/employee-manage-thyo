<?php
// api/admin/unified_tasks.php - Admin interface for unified task management
require_once '../../config/database.php';
require_once '../common/response.php';

$token = getAuthHeader();
if (!$token) {
    sendError('Authorization token required', 401);
}

// Verify admin token
$admin = verifyAdminToken($pdo, $token);
if (!$admin) {
    sendError('Invalid or expired admin token', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getAdminTasks($pdo, $admin);
        break;
    case 'POST':
        createAdminTask($pdo, $admin);
        break;
    case 'PUT':
        updateAdminTask($pdo, $admin);
        break;
    case 'DELETE':
        deleteAdminTask($pdo, $admin);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getAdminTasks($pdo, $admin) {
    try {
        $employee_id = $_GET['employee_id'] ?? null;
        $status_filter = $_GET['status'] ?? null;
        $task_type = $_GET['task_type'] ?? 'all'; // all, self_created, admin_assigned
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

        // Build query
        $query = "
            SELECT t.id, t.title, t.description, t.task_type, t.priority, t.status,
                   t.start_date, t.due_date, t.expense_required, t.budget_amount,
                   t.start_latitude, t.start_longitude, t.completion_latitude, t.completion_longitude,
                   t.task_image, t.completion_image, t.notes, t.completion_notes,
                   t.created_at, t.started_at, t.completed_at, t.updated_at,
                   s.name as site_name,
                   assigned_emp.name as assigned_to_name,
                   assigned_emp.employee_code as assigned_to_code,
                   creator_emp.name as created_by_name,
                   assigner.username as assigned_by_name,
                   DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display,
                   DATE_FORMAT(t.started_at, '%Y-%m-%d %H:%i') as started_display,
                   DATE_FORMAT(t.completed_at, '%Y-%m-%d %H:%i') as completed_display,
                   DATE_FORMAT(t.start_date, '%Y-%m-%d') as start_date_display,
                   DATE_FORMAT(t.due_date, '%Y-%m-%d') as due_date_display,
                   CASE
                       WHEN t.start_latitude IS NOT NULL AND t.start_longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.start_latitude, 6), ', Lng: ', ROUND(t.start_longitude, 6))
                       ELSE NULL
                   END as start_location_display,
                   CASE
                       WHEN t.completion_latitude IS NOT NULL AND t.completion_longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.completion_latitude, 6), ', Lng: ', ROUND(t.completion_longitude, 6))
                       ELSE NULL
                   END as completion_location_display,
                   CASE
                       WHEN t.expense_required = 1 AND t.budget_amount IS NOT NULL THEN
                           CONCAT('₹', FORMAT(t.budget_amount, 2))
                       ELSE NULL
                   END as budget_display,
                   CASE
                       WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status NOT IN ('completed', 'cancelled')
                       THEN 'overdue'
                       WHEN t.due_date IS NOT NULL AND t.due_date = CURDATE() AND t.status NOT IN ('completed', 'cancelled')
                       THEN 'due_today'
                       ELSE 'normal'
                   END as urgency_status
            FROM tasks t
            LEFT JOIN sites s ON t.site_id = s.id
            LEFT JOIN employees assigned_emp ON t.assigned_to_employee_id = assigned_emp.id
            LEFT JOIN employees creator_emp ON t.created_by_employee_id = creator_emp.id
            LEFT JOIN users assigner ON t.assigned_by_user_id = assigner.id
            WHERE 1=1
        ";

        $params = [];

        // Add employee filter
        if ($employee_id) {
            $query .= " AND t.assigned_to_employee_id = ?";
            $params[] = $employee_id;
        }

        // Add task type filter
        if ($task_type !== 'all') {
            $query .= " AND t.task_type = ?";
            $params[] = $task_type;
        }

        // Add status filter
        if ($status_filter) {
            $query .= " AND t.status = ?";
            $params[] = $status_filter;
        }

        $query .= " ORDER BY
                    CASE WHEN t.status = 'pending' THEN 1
                         WHEN t.status = 'active' THEN 1
                         WHEN t.status = 'in_progress' THEN 2
                         WHEN t.status = 'completed' THEN 3
                         WHEN t.status = 'cancelled' THEN 4
                         ELSE 5 END,
                    t.due_date ASC,
                    t.created_at DESC";

        if ($limit) {
            $query .= " LIMIT " . $limit;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get summary statistics
        $summary_query = "
            SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN t.status IN ('active', 'in_progress') THEN 1 ELSE 0 END) as active_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
                SUM(CASE WHEN t.task_type = 'admin_assigned' THEN 1 ELSE 0 END) as admin_assigned_tasks,
                SUM(CASE WHEN t.task_type = 'self_created' THEN 1 ELSE 0 END) as self_created_tasks,
                SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date = CURDATE() AND t.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as due_today_tasks
            FROM tasks t WHERE 1=1
        ";

        if ($employee_id) {
            $summary_query .= " AND t.assigned_to_employee_id = ?";
        }
        if ($task_type !== 'all') {
            $summary_query .= " AND t.task_type = ?";
        }

        $summary_stmt = $pdo->prepare($summary_query);
        $summary_params = [];
        if ($employee_id) $summary_params[] = $employee_id;
        if ($task_type !== 'all') $summary_params[] = $task_type;
        $summary_stmt->execute($summary_params);
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

function createAdminTask($pdo, $admin) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        // Validate required fields
        $required_fields = ['title', 'assigned_to_employee_ids', 'site_id'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) ||
                (is_string($input[$field]) && trim($input[$field]) === '') ||
                (is_array($input[$field]) && empty($input[$field]))) {
                sendError("Field '$field' is required", 400);
                return;
            }
        }

        $title = trim($input['title']);
        $description = trim($input['description'] ?? '');
        $assigned_to_employee_ids = $input['assigned_to_employee_ids']; // Array of employee IDs
        $site_id = (int)$input['site_id'];
        $priority = $input['priority'] ?? 'medium';
        $start_date = $input['start_date'] ?? null;
        $due_date = $input['due_date'] ?? null;
        $expense_required = isset($input['expense_required']) ? (int)$input['expense_required'] : 0;
        $budget_amount = isset($input['budget_amount']) ? (float)$input['budget_amount'] : null;
        $notes = trim($input['notes'] ?? '');
        $start_latitude = isset($input['start_latitude']) ? (float)$input['start_latitude'] : null;
        $start_longitude = isset($input['start_longitude']) ? (float)$input['start_longitude'] : null;

        // Validate input
        if (strlen($title) < 3) {
            sendError('Task title must be at least 3 characters long', 400);
            return;
        }

        if (!is_array($assigned_to_employee_ids)) {
            sendError('assigned_to_employee_ids must be an array', 400);
            return;
        }

        $valid_priorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $valid_priorities)) {
            sendError('Invalid priority. Must be one of: ' . implode(', ', $valid_priorities), 400);
            return;
        }

        // Validate coordinates if provided
        if (($start_latitude !== null || $start_longitude !== null)) {
            if ($start_latitude === null || $start_longitude === null) {
                sendError('Both latitude and longitude must be provided together', 400);
                return;
            }
            if ($start_latitude < -90 || $start_latitude > 90 || $start_longitude < -180 || $start_longitude > 180) {
                sendError('Invalid coordinates provided', 400);
                return;
            }
        }

        // Verify all employee IDs exist
        $employee_placeholders = str_repeat('?,', count($assigned_to_employee_ids) - 1) . '?';
        $employee_check_stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM employees
            WHERE id IN ($employee_placeholders) AND status = 'active'
        ");
        $employee_check_stmt->execute($assigned_to_employee_ids);
        $employee_count = $employee_check_stmt->fetch()['count'];

        if ($employee_count !== count($assigned_to_employee_ids)) {
            sendError('One or more employee IDs are invalid or inactive', 400);
            return;
        }

        $created_tasks = [];

        // Create separate task for each assigned employee
        foreach ($assigned_to_employee_ids as $employee_id) {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    title, description, task_type, assigned_to_employee_id, assigned_by_user_id,
                    site_id, priority, start_date, due_date, expense_required, budget_amount,
                    notes, start_latitude, start_longitude, status, created_at
                ) VALUES (?, ?, 'admin_assigned', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $stmt->execute([
                $title,
                $description,
                $employee_id,
                $admin['id'],
                $site_id,
                $priority,
                $start_date,
                $due_date,
                $expense_required,
                $budget_amount,
                $notes,
                $start_latitude,
                $start_longitude
            ]);

            $task_id = $pdo->lastInsertId();

            // Get the created task with employee and site information
            $task_stmt = $pdo->prepare("
                SELECT t.*, s.name as site_name, e.name as assigned_to_name, e.employee_code,
                       u.username as assigned_by_name,
                       CASE
                           WHEN t.start_latitude IS NOT NULL AND t.start_longitude IS NOT NULL THEN
                               CONCAT('Lat: ', ROUND(t.start_latitude, 6), ', Lng: ', ROUND(t.start_longitude, 6))
                           ELSE NULL
                       END as start_location_display,
                       DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display,
                       DATE_FORMAT(t.start_date, '%Y-%m-%d') as start_date_display,
                       DATE_FORMAT(t.due_date, '%Y-%m-%d') as due_date_display
                FROM tasks t
                LEFT JOIN sites s ON t.site_id = s.id
                LEFT JOIN employees e ON t.assigned_to_employee_id = e.id
                LEFT JOIN users u ON t.assigned_by_user_id = u.id
                WHERE t.id = ?
            ");
            $task_stmt->execute([$task_id]);
            $created_task = $task_stmt->fetch(PDO::FETCH_ASSOC);

            $created_tasks[] = $created_task;

            error_log("Admin task created - ID: $task_id, Employee: $employee_id, Title: $title, Admin: {$admin['id']}");
        }

        sendSuccess('Admin task(s) created successfully', [
            'tasks' => $created_tasks,
            'total_created' => count($created_tasks)
        ]);

    } catch (PDOException $e) {
        error_log("Database error in createAdminTask: " . $e->getMessage());
        sendError('Database error occurred: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log("General error in createAdminTask: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

function updateAdminTask($pdo, $admin) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        $task_id = (int)($input['task_id'] ?? 0);

        if (!$task_id) {
            sendError('Task ID is required', 400);
            return;
        }

        // Verify task exists and is admin-assigned
        $stmt = $pdo->prepare("
            SELECT * FROM tasks
            WHERE id = ? AND task_type = 'admin_assigned'
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();

        if (!$task) {
            sendError('Admin task not found', 404);
            return;
        }

        // Prepare update fields
        $update_fields = ['updated_at = NOW()'];
        $update_params = [];

        // Update allowed fields
        $updatable_fields = [
            'title', 'description', 'priority', 'start_date', 'due_date',
            'expense_required', 'budget_amount', 'notes', 'start_latitude', 'start_longitude'
        ];

        foreach ($updatable_fields as $field) {
            if (isset($input[$field])) {
                if (in_array($field, ['title', 'description', 'notes'])) {
                    $value = trim($input[$field]);
                    if ($field === 'title' && strlen($value) < 3) {
                        sendError('Task title must be at least 3 characters long', 400);
                        return;
                    }
                } elseif (in_array($field, ['expense_required'])) {
                    $value = (int)$input[$field];
                } elseif (in_array($field, ['budget_amount', 'start_latitude', 'start_longitude'])) {
                    $value = $input[$field] !== null ? (float)$input[$field] : null;
                } else {
                    $value = $input[$field];
                }

                if ($field === 'priority') {
                    $valid_priorities = ['low', 'medium', 'high'];
                    if (!in_array($value, $valid_priorities)) {
                        sendError('Invalid priority. Must be one of: ' . implode(', ', $valid_priorities), 400);
                        return;
                    }
                }

                $update_fields[] = "$field = ?";
                $update_params[] = $value;
            }
        }

        // Validate coordinates if provided
        if (isset($input['start_latitude']) || isset($input['start_longitude'])) {
            $lat = isset($input['start_latitude']) ? (float)$input['start_latitude'] : $task['start_latitude'];
            $lng = isset($input['start_longitude']) ? (float)$input['start_longitude'] : $task['start_longitude'];

            if (($lat !== null || $lng !== null) && ($lat === null || $lng === null)) {
                sendError('Both latitude and longitude must be provided together', 400);
                return;
            }

            if ($lat !== null && $lng !== null) {
                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    sendError('Invalid coordinates provided', 400);
                    return;
                }
            }
        }

        if (count($update_fields) === 1) {
            sendError('No valid fields to update', 400);
            return;
        }

        // Update the task
        $update_params[] = $task_id;
        $update_sql = "
            UPDATE tasks SET " . implode(', ', $update_fields) . "
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($update_params);

        // Get updated task data
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name, e.name as assigned_to_name, e.employee_code,
                   u.username as assigned_by_name,
                   CASE
                       WHEN t.start_latitude IS NOT NULL AND t.start_longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.start_latitude, 6), ', Lng: ', ROUND(t.start_longitude, 6))
                       ELSE NULL
                   END as start_location_display,
                   CASE
                       WHEN t.completion_latitude IS NOT NULL AND t.completion_longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.completion_latitude, 6), ', Lng: ', ROUND(t.completion_longitude, 6))
                       ELSE NULL
                   END as completion_location_display,
                   DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display,
                   DATE_FORMAT(t.started_at, '%Y-%m-%d %H:%i') as started_display,
                   DATE_FORMAT(t.completed_at, '%Y-%m-%d %H:%i') as completed_display,
                   DATE_FORMAT(t.start_date, '%Y-%m-%d') as start_date_display,
                   DATE_FORMAT(t.due_date, '%Y-%m-%d') as due_date_display
            FROM tasks t
            LEFT JOIN sites s ON t.site_id = s.id
            LEFT JOIN employees e ON t.assigned_to_employee_id = e.id
            LEFT JOIN users u ON t.assigned_by_user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $updated_task = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Admin task updated - ID: $task_id, Admin: {$admin['id']}");

        sendSuccess('Admin task updated successfully', $updated_task);

    } catch (PDOException $e) {
        error_log("Database error in updateAdminTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in updateAdminTask: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

function deleteAdminTask($pdo, $admin) {
    try {
        $task_id = (int)($_GET['task_id'] ?? 0);

        if (!$task_id) {
            sendError('Task ID is required', 400);
            return;
        }

        // Verify task exists and is admin-assigned
        $stmt = $pdo->prepare("
            SELECT * FROM tasks
            WHERE id = ? AND task_type = 'admin_assigned'
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();

        if (!$task) {
            sendError('Admin task not found', 404);
            return;
        }

        // Prevent deletion of started tasks
        if ($task['status'] === 'in_progress') {
            sendError('Cannot delete task that is in progress', 400);
            return;
        }

        // Delete the task
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);

        error_log("Admin task deleted - ID: $task_id, Admin: {$admin['id']}");

        sendSuccess('Admin task deleted successfully', ['task_id' => $task_id]);

    } catch (PDOException $e) {
        error_log("Database error in deleteAdminTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in deleteAdminTask: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

// Helper function to verify admin token (implement as needed)
function verifyAdminToken($pdo, $token) {
    // This should be implemented based on your existing admin authentication system
    // For now, returning a placeholder - you'll need to adapt this to your auth system
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'superadmin') AND status = 'active'");
        // You'll need to decode the token and extract user ID based on your token system
        // This is a placeholder implementation
        $user_id = 1; // Replace with actual token decoding
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Admin token verification error: " . $e->getMessage());
        return false;
    }
}
?>