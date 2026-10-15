<?php
// api/employee/unified_tasks.php - Unified task management for both self-created and admin-assigned tasks
require_once '../../config/database.php';
require_once '../common/response.php';
require_once '../../includes/task_identifier.php';

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
        getTasks($pdo, $employee);
        break;
    case 'POST':
        createTask($pdo, $employee);
        break;
    case 'PUT':
        updateTaskStatus($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getTasks($pdo, $employee) {
    try {
        $date = $_GET['date'] ?? date('Y-m-d');
        $task_type = $_GET['task_type'] ?? 'all'; // all, self_created, admin_assigned
        $status_filter = $_GET['status'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

        // Build query for unified task retrieval
        $query = "
            SELECT t.id, t.title, t.description, t.task_identifier, t.task_type, t.priority, t.status,
                   t.start_date, t.due_date, t.expense_required, t.budget_amount,
                   t.start_latitude, t.start_longitude, t.completion_latitude, t.completion_longitude,
                   t.task_image, t.completion_image, t.notes, t.completion_notes,
                   t.created_at, t.started_at, t.completed_at, t.updated_at,
                   s.name as site_name,
                   creator.name as created_by_name,
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
            LEFT JOIN employees creator ON t.created_by_employee_id = creator.id
            LEFT JOIN users assigner ON t.assigned_by_user_id = assigner.id
            WHERE t.assigned_to_employee_id = ?
        ";

        $params = [$employee['id']];

        // Add task type filter
        if ($task_type !== 'all') {
            $query .= " AND t.task_type = ?";
            $params[] = $task_type;
        }

        // Add date filter for self-created tasks
        if ($task_type === 'self_created' || $task_type === 'all') {
            $query .= " AND (t.task_type = 'admin_assigned' OR DATE(t.created_at) = ?)";
            $params[] = $date;
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

        // Get attendance status for self-created task creation
        $attendance_stmt = $pdo->prepare("
            SELECT * FROM attendance
            WHERE employee_id = ? AND date = ?
            AND check_in_latitude IS NOT NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $attendance_stmt->execute([$employee['id'], $date]);
        $attendance = $attendance_stmt->fetch();

        $attendance_status = 'not_checked_in';
        $can_create_self_task = false;

        if ($attendance) {
            if ($attendance['check_out_latitude'] !== null) {
                $attendance_status = 'checked_out';
            } else {
                $attendance_status = 'checked_in';
                $can_create_self_task = true;
            }
        }

        // Multiple self-created tasks are now allowed
        // Task creation is only limited by attendance status

        // Get summary statistics
        $summary_stmt = $pdo->prepare("
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
            FROM tasks t
            WHERE t.assigned_to_employee_id = ?
        ");
        $summary_stmt->execute([$employee['id']]);
        $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

        $response = [
            'tasks' => $tasks,
            'summary' => $summary,
            'can_create_self_task' => $can_create_self_task,
            'attendance_status' => $attendance_status
        ];

        sendSuccess('Tasks retrieved successfully', $response);

    } catch (PDOException $e) {
        error_log("Get unified tasks error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

function createTask($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        // Validate required fields
        $required_fields = ['title', 'site_id', 'start_latitude', 'start_longitude'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) ||
                ($field !== 'start_latitude' && $field !== 'start_longitude' && trim($input[$field]) === '')) {
                sendError("Field '$field' is required", 400);
                return;
            }
        }

        $title = trim($input['title']);
        $description = trim($input['description'] ?? '');
        $site_id = (int)$input['site_id'];
        $start_latitude = (float)$input['start_latitude'];
        $start_longitude = (float)$input['start_longitude'];
        $task_image_base64 = $input['task_image'] ?? null;

        // Validate input
        if (strlen($title) < 3) {
            sendError('Task title must be at least 3 characters long', 400);
            return;
        }

        if ($start_latitude < -90 || $start_latitude > 90 || $start_longitude < -180 || $start_longitude > 180) {
            sendError('Invalid coordinates provided', 400);
            return;
        }

        // Check if employee is checked in today (for self-created tasks)
        $today = date('Y-m-d');
        $attendance_stmt = $pdo->prepare("
            SELECT * FROM attendance
            WHERE employee_id = ? AND date = ?
            AND check_in_latitude IS NOT NULL
            AND check_out_latitude IS NULL
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $attendance_stmt->execute([$employee['id'], $today]);
        $latest_attendance = $attendance_stmt->fetch();

        if (!$latest_attendance) {
            sendError('You must be checked in to create a task', 400);
            return;
        }

        // Multiple self-created tasks are now allowed
        // Removed restriction check

        // Handle image upload if provided
        $image_filename = null;
        if ($task_image_base64) {
            try {
                $image_filename = uploadBase64Image($task_image_base64, '../../uploads/tasks/');
                error_log("Task image uploaded successfully: $image_filename");
            } catch (Exception $e) {
                error_log("Image upload failed: " . $e->getMessage());
                sendError('Failed to upload image: ' . $e->getMessage(), 400);
                return;
            }
        }

        // Create the self-created task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                title, description, task_type, created_by_employee_id, assigned_to_employee_id,
                site_id, attendance_id, start_latitude, start_longitude, task_image,
                status, created_at
            ) VALUES (?, ?, 'self_created', ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        $stmt->execute([
            $title,
            $description,
            $employee['id'], // created_by_employee_id
            $employee['id'], // assigned_to_employee_id
            $site_id,
            $latest_attendance['id'],
            $start_latitude,
            $start_longitude,
            $image_filename
        ]);

        $task_id = $pdo->lastInsertId();

        // Generate and assign task identifier
        $task_identifier = assignTaskIdentifier($pdo, $task_id, $title, $site_id);
        if (!$task_identifier) {
            error_log("Failed to generate task identifier for task ID: $task_id");
        }

        // Get the created task with site information
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name,
                   CONCAT('Lat: ', ROUND(t.start_latitude, 6), ', Lng: ', ROUND(t.start_longitude, 6)) as start_location_display,
                   DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as created_display
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Self-created task created successfully - ID: $task_id, Employee: {$employee['id']}, Title: $title");

        sendSuccess('Task created successfully', $task);

    } catch (PDOException $e) {
        error_log("Database error in createTask: " . $e->getMessage());
        sendError('Database error occurred: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log("General error in createTask: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

function updateTaskStatus($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        $task_id = (int)($input['task_id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $completion_notes = trim($input['completion_notes'] ?? '');
        $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
        $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
        $completion_image_base64 = $input['completion_image'] ?? null;
        $start_image_base64 = $input['start_image'] ?? null;

        if (!$task_id || !$status) {
            sendError('Task ID and status are required', 400);
            return;
        }

        // Validate status
        $valid_statuses = ['pending', 'active', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            sendError('Invalid status. Must be one of: ' . implode(', ', $valid_statuses), 400);
            return;
        }

        // Verify task exists and belongs to employee
        $stmt = $pdo->prepare("
            SELECT * FROM tasks
            WHERE id = ? AND assigned_to_employee_id = ?
        ");
        $stmt->execute([$task_id, $employee['id']]);
        $task = $stmt->fetch();

        if (!$task) {
            sendError('Task not found or not assigned to you', 404);
            return;
        }

        // Prevent changing from completed/cancelled status
        if (in_array($task['status'], ['completed', 'cancelled']) && $status !== $task['status']) {
            sendError('Cannot change status of completed or cancelled tasks', 400);
            return;
        }

        // Handle start image upload if provided (for admin tasks when employee starts working)
        $start_image_filename = null;
        if ($start_image_base64 && in_array($status, ['in_progress', 'active'])) {
            try {
                $start_image_filename = uploadBase64Image($start_image_base64, '../../uploads/tasks/');
                error_log("Task start image uploaded successfully: $start_image_filename");
            } catch (Exception $e) {
                error_log("Start image upload failed: " . $e->getMessage());
                sendError('Failed to upload start image: ' . $e->getMessage(), 400);
                return;
            }
        }

        // Handle completion image upload if provided
        $completion_image_filename = null;
        if ($completion_image_base64 && $status === 'completed') {
            try {
                $completion_image_filename = uploadBase64Image($completion_image_base64, '../../uploads/tasks/');
                error_log("Task completion image uploaded successfully: $completion_image_filename");
            } catch (Exception $e) {
                error_log("Completion image upload failed: " . $e->getMessage());
                sendError('Failed to upload completion image: ' . $e->getMessage(), 400);
                return;
            }
        }

        // Prepare update fields
        $update_fields = ['status = ?', 'updated_at = NOW()'];
        $update_params = [$status];

        // Add notes
        if ($notes) {
            $update_fields[] = 'notes = ?';
            $update_params[] = $notes;
        }

        // Set timestamps and location based on status
        if ($status === 'in_progress' && $task['started_at'] === null) {
            $update_fields[] = 'started_at = NOW()';
            if ($latitude !== null && $longitude !== null) {
                $update_fields[] = 'start_latitude = ?';
                $update_fields[] = 'start_longitude = ?';
                $update_params[] = $latitude;
                $update_params[] = $longitude;
            }
        }

        // Update start image if provided (for admin tasks when employee starts working)
        if ($start_image_filename) {
            $update_fields[] = 'task_image = ?';
            $update_params[] = $start_image_filename;
        }

        if ($status === 'completed') {
            $update_fields[] = 'completed_at = NOW()';
            if ($completion_notes) {
                $update_fields[] = 'completion_notes = ?';
                $update_params[] = $completion_notes;
            }
            if ($latitude !== null && $longitude !== null) {
                $update_fields[] = 'completion_latitude = ?';
                $update_fields[] = 'completion_longitude = ?';
                $update_params[] = $latitude;
                $update_params[] = $longitude;
            }
            if ($completion_image_filename) {
                $update_fields[] = 'completion_image = ?';
                $update_params[] = $completion_image_filename;
            }
        }

        // Update the task
        $update_params[] = $task_id;
        $update_params[] = $employee['id'];

        $update_sql = "
            UPDATE tasks SET " . implode(', ', $update_fields) . "
            WHERE id = ? AND assigned_to_employee_id = ?
        ";

        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($update_params);

        // Get updated task data
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name,
                   creator.name as created_by_name,
                   assigner.username as assigned_by_name,
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
                   DATE_FORMAT(t.completed_at, '%Y-%m-%d %H:%i') as completed_display
            FROM tasks t
            LEFT JOIN sites s ON t.site_id = s.id
            LEFT JOIN employees creator ON t.created_by_employee_id = creator.id
            LEFT JOIN users assigner ON t.assigned_by_user_id = assigner.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $updated_task = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Task status updated - ID: $task_id, Status: $status, Employee: {$employee['id']}");

        sendSuccess('Task status updated successfully', $updated_task);

    } catch (PDOException $e) {
        error_log("Database error in updateTaskStatus: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in updateTaskStatus: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

/**
 * Enhanced image upload function
 */
function uploadBase64Image($base64_string, $upload_dir) {
    try {
        if (empty($base64_string)) {
            throw new Exception('Empty base64 string provided');
        }

        error_log("Starting image upload to directory: $upload_dir");

        // Remove data:image/jpeg;base64, prefix if present
        if (strpos($base64_string, 'data:image') === 0) {
            $comma_pos = strpos($base64_string, ',');
            if ($comma_pos === false) {
                throw new Exception('Invalid base64 format - missing comma separator');
            }
            $base64_string = substr($base64_string, $comma_pos + 1);
        }

        // Decode base64
        $image_data = base64_decode($base64_string, true);
        if ($image_data === false) {
            throw new Exception('Invalid base64 image data - decode failed');
        }

        $image_size = strlen($image_data);
        error_log("Decoded image size: $image_size bytes");

        // Validate image size (max 10MB)
        if ($image_size > 10 * 1024 * 1024) {
            throw new Exception('Image too large - maximum 10MB allowed');
        }

        if ($image_size < 100) {
            throw new Exception('Image too small - minimum 100 bytes required');
        }

        // Get absolute path
        $abs_upload_dir = realpath(dirname(__FILE__)) . '/' . $upload_dir;
        error_log("Absolute upload directory: $abs_upload_dir");

        // Create directory if it doesn't exist
        if (!file_exists($abs_upload_dir)) {
            error_log("Creating directory: $abs_upload_dir");
            if (!mkdir($abs_upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory: ' . $abs_upload_dir);
            }
        }

        // Check if directory is writable
        if (!is_writable($abs_upload_dir)) {
            error_log("Directory not writable, attempting to fix permissions: $abs_upload_dir");
            if (!chmod($abs_upload_dir, 0755)) {
                throw new Exception('Upload directory is not writable and cannot fix permissions: ' . $abs_upload_dir);
            }
        }

        // Generate unique filename
        $filename = 'unified_task_' . uniqid() . '_' . time() . '.jpg';
        $file_path = $abs_upload_dir . '/' . $filename;

        error_log("Writing image to: $file_path");

        // Write the file
        $bytes_written = file_put_contents($file_path, $image_data);
        if ($bytes_written === false) {
            throw new Exception('Failed to write image file: ' . $file_path);
        }

        error_log("Image written successfully. Bytes: $bytes_written");

        // Verify the file was created and has correct size
        if (!file_exists($file_path)) {
            throw new Exception('Image file was not created successfully');
        }

        $actual_file_size = filesize($file_path);
        if ($actual_file_size !== $image_size) {
            error_log("Size mismatch: expected $image_size, got $actual_file_size");
            unlink($file_path); // Clean up
            throw new Exception('Image file size verification failed');
        }

        // Set proper permissions
        chmod($file_path, 0644);

        error_log("Image upload completed successfully: $filename");
        return $filename;

    } catch (Exception $e) {
        error_log("Image upload error: " . $e->getMessage());
        throw $e;
    }
}
?>