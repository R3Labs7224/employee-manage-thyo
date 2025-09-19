<?php
// api/employee/tasks.php - UPDATED: Remove time fields, focus on location and image
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
        getTasks($pdo, $employee);
        break;
    case 'POST':
        createTask($pdo, $employee);
        break;
    case 'PUT':
        completeTask($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getTasks($pdo, $employee) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

    try {
        // Get attendance status
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
        $can_create_task = false;

        if ($attendance) {
            if ($attendance['check_out_latitude'] !== null) {
                $attendance_status = 'checked_out';
            } else {
                $attendance_status = 'checked_in';
                $can_create_task = true;
            }
        }

        // Get field tasks (original mobile app tasks) for the date
        $field_tasks_query = "
            SELECT t.*, s.name as site_name, 'field' as task_source,
                   CASE
                       WHEN t.latitude IS NOT NULL AND t.longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.latitude, 6), ', Lng: ', ROUND(t.longitude, 6))
                       ELSE 'Location not available'
                   END as location_display,
                   CASE
                       WHEN t.completion_latitude IS NOT NULL AND t.completion_longitude IS NOT NULL THEN
                           CONCAT('Lat: ', ROUND(t.completion_latitude, 6), ', Lng: ', ROUND(t.completion_longitude, 6))
                       ELSE NULL
                   END as completion_location_display,
                   DATE_FORMAT(t.created_at, '%H:%i') as created_time_display,
                   DATE_FORMAT(t.completed_at, '%H:%i') as completed_time_display
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.employee_id = ? AND DATE(t.created_at) = ?
            ORDER BY t.created_at DESC
        ";

        if ($limit) {
            $field_tasks_query .= " LIMIT " . $limit;
        }

        $field_tasks_stmt = $pdo->prepare($field_tasks_query);
        $field_tasks_stmt->execute([$employee['id'], $date]);
        $field_tasks = $field_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get admin-assigned tasks for this user
        $admin_tasks_query = "
            SELECT t.id, t.title, t.description, t.priority, t.due_date, t.created_at,
                   ta.status, ta.notes, ta.started_at, ta.completed_at, ta.id as assignment_id,
                   'admin' as task_source,
                   u.username as created_by_username,
                   s.name as site_name,
                   DATE_FORMAT(t.created_at, '%H:%i') as created_time_display,
                   DATE_FORMAT(ta.completed_at, '%H:%i') as completed_time_display
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            LEFT JOIN users u ON t.assigned_by = u.id
            LEFT JOIN sites s ON t.site_id = s.id
            WHERE ta.assigned_to = ? AND t.admin_created = 1
            ORDER BY t.created_at DESC
        ";

        if ($limit) {
            $admin_tasks_query .= " LIMIT " . $limit;
        }

        $admin_tasks_stmt = $pdo->prepare($admin_tasks_query);
        $admin_tasks_stmt->execute([$employee['id']]);
        $admin_tasks = $admin_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Combine all tasks
        $all_tasks = array_merge($field_tasks, $admin_tasks);

        // Sort by created_at descending
        usort($all_tasks, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Get task summary (field tasks only for mobile app logic)
        $summary_stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tasks,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks
            FROM tasks
            WHERE employee_id = ? AND DATE(created_at) = ?
        ");
        $summary_stmt->execute([$employee['id'], $date]);
        $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

        // Get admin task summary
        $admin_summary_stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_admin_tasks,
                SUM(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) as completed_admin_tasks,
                SUM(CASE WHEN ta.status = 'pending' THEN 1 ELSE 0 END) as pending_admin_tasks,
                SUM(CASE WHEN ta.status = 'in_progress' THEN 1 ELSE 0 END) as active_admin_tasks
            FROM task_assignments ta
            WHERE ta.assigned_to = ?
        ");
        $admin_summary_stmt->execute([$employee['id']]);
        $admin_summary = $admin_summary_stmt->fetch(PDO::FETCH_ASSOC);

        // Check if there's already an active field task
        $active_task_exists = false;
        foreach ($field_tasks as $task) {
            if ($task['status'] === 'active') {
                $active_task_exists = true;
                break;
            }
        }

        // Can't create task if there's already an active one
        if ($active_task_exists) {
            $can_create_task = false;
        }

        $response = [
            'tasks' => $all_tasks,
            'field_tasks' => $field_tasks,
            'admin_tasks' => $admin_tasks,
            'summary' => array_merge($summary, $admin_summary),
            'can_create_task' => $can_create_task,
            'attendance_status' => $attendance_status
        ];

        sendSuccess('Tasks retrieved successfully', $response);

    } catch (PDOException $e) {
        error_log("Get tasks error: " . $e->getMessage());
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
        
        // Validate required fields - NO TIME FIELDS
        $required_fields = ['title', 'site_id', 'latitude', 'longitude'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || 
                ($field !== 'latitude' && $field !== 'longitude' && trim($input[$field]) === '')) {
                sendError("Field '$field' is required", 400);
                return;
            }
        }
        
        $title = trim($input['title']);
        $description = trim($input['description'] ?? '');
        $site_id = (int)$input['site_id'];
        $latitude = (float)$input['latitude'];
        $longitude = (float)$input['longitude'];
        $task_image_base64 = $input['task_image'] ?? null;
        
        // Validate input
        if (strlen($title) < 3) {
            sendError('Task title must be at least 3 characters long', 400);
            return;
        }
        
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            sendError('Invalid coordinates provided', 400);
            return;
        }
        
        // Check if employee is checked in today
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
        
        // Check if there's already an active task
        $active_task_stmt = $pdo->prepare("
            SELECT id FROM tasks 
            WHERE employee_id = ? AND status = 'active'
        ");
        $active_task_stmt->execute([$employee['id']]);
        if ($active_task_stmt->fetch()) {
            sendError('You already have an active task. Complete it before creating a new one.', 400);
            return;
        }
        
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
        
        // Create the task - REMOVED time fields, focus on location
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                employee_id, attendance_id, site_id, title, description, 
                latitude, longitude, task_image, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $employee['id'],
            $latest_attendance['id'],
            $site_id,
            $title,
            $description,
            $latitude,
            $longitude,
            $image_filename
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Get the created task with site name and location display
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name,
                   CONCAT('Lat: ', ROUND(t.latitude, 6), ', Lng: ', ROUND(t.longitude, 6)) as location_display,
                   DATE_FORMAT(t.created_at, '%H:%i') as created_time_display
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log successful creation
        error_log("Task created successfully - ID: $task_id, Employee: {$employee['id']}, Title: $title, Location: $latitude,$longitude");
        
        sendSuccess('Task created successfully', $task);
        
    } catch (PDOException $e) {
        error_log("Database error in createTask: " . $e->getMessage());
        sendError('Database error occurred: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log("General error in createTask: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

function completeTask($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }
        
        $task_id = (int)($input['task_id'] ?? 0);
        $completion_notes = trim($input['completion_notes'] ?? '');
        $latitude = (float)($input['latitude'] ?? 0);
        $longitude = (float)($input['longitude'] ?? 0);
        $completion_image_base64 = $input['completion_image'] ?? null;
        
        if (!$task_id) {
            sendError('Task ID is required', 400);
            return;
        }
        
        // Verify task exists, belongs to employee, and is active
        $stmt = $pdo->prepare("
            SELECT * FROM tasks 
            WHERE id = ? AND employee_id = ? AND status = 'active'
        ");
        $stmt->execute([$task_id, $employee['id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            sendError('Task not found or already completed', 404);
            return;
        }
        
        // Handle completion image upload if provided
        $completion_image_filename = null;
        if ($completion_image_base64) {
            try {
                $completion_image_filename = uploadBase64Image($completion_image_base64, '../../uploads/tasks/');
                error_log("Task completion image uploaded successfully: $completion_image_filename");
            } catch (Exception $e) {
                error_log("Completion image upload failed: " . $e->getMessage());
                sendError('Failed to upload completion image: ' . $e->getMessage(), 400);
                return;
            }
        }
        
        // Update task - REMOVED end_time, focus on completion location and image
        $stmt = $pdo->prepare("
            UPDATE tasks SET 
                status = 'completed',
                completed_at = NOW(),
                completion_notes = ?,
                completion_latitude = ?,
                completion_longitude = ?,
                completion_image = ?
            WHERE id = ? AND employee_id = ?
        ");
        
        $stmt->execute([
            $completion_notes,
            $latitude,
            $longitude,
            $completion_image_filename,
            $task_id,
            $employee['id']
        ]);
        
        // Get updated task with location display
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name,
                   CONCAT('Lat: ', ROUND(t.latitude, 6), ', Lng: ', ROUND(t.longitude, 6)) as location_display,
                   CASE 
                       WHEN t.completion_latitude IS NOT NULL AND t.completion_longitude IS NOT NULL THEN 
                           CONCAT('Lat: ', ROUND(t.completion_latitude, 6), ', Lng: ', ROUND(t.completion_longitude, 6))
                       ELSE NULL 
                   END as completion_location_display,
                   DATE_FORMAT(t.created_at, '%H:%i') as created_time_display,
                   DATE_FORMAT(t.completed_at, '%H:%i') as completed_time_display
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $updated_task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Task completed successfully - ID: $task_id, Completion Location: $latitude,$longitude");
        
        sendSuccess('Task completed successfully', $updated_task);
        
    } catch (PDOException $e) {
        error_log("Database error in completeTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

/**
 * ENHANCED IMAGE UPLOAD FUNCTION
 * With better error handling, directory creation, and file permissions
 */
function uploadBase64Image($base64_string, $upload_dir) {
    try {
        // Validate input
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
        $filename = 'task_' . uniqid() . '_' . time() . '.jpg';
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