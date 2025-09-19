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
        
        // UPDATED: Get both self-created tasks AND admin-assigned tasks
        $tasks_query = "
            SELECT 
                t.id,
                t.employee_id,
                t.attendance_id,
                t.site_id,
                t.title,
                t.description,
                t.task_image,
                t.latitude,
                t.longitude,
                t.status,
                t.created_at,
                t.completed_at,
                t.completion_notes,
                t.completion_latitude,
                t.completion_longitude,
                t.completion_image,
                t.admin_created,
                t.assigned_by,
                t.priority,
                t.due_date,
                s.name as site_name,
                u.username as assigned_by_name,
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
                DATE_FORMAT(t.completed_at, '%H:%i') as completed_time_display,
                ta.id as assignment_id,
                ta.status as assignment_status,
                ta.notes as assignment_notes,
                ta.started_at as assignment_started_at,
                ta.completed_at as assignment_completed_at,
                CASE 
                    WHEN t.admin_created = 1 THEN 'assigned'
                    ELSE 'self_created'
                END as task_type
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            LEFT JOIN users u ON t.assigned_by = u.id
            LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
            WHERE (
                -- Self-created tasks
                (t.employee_id = ? AND DATE(t.created_at) = ?) OR
                -- Admin-assigned tasks (show all assigned tasks, not just today's)
                (t.admin_created = 1 AND ta.assigned_to = ?)
            )
            ORDER BY t.created_at DESC
        ";
        
        if ($limit) {
            $tasks_query .= " LIMIT " . $limit;
        }
        
        $tasks_stmt = $pdo->prepare($tasks_query);
        $tasks_stmt->execute([
            $employee['id'], // for task_assignments join
            $employee['id'], // for self-created tasks
            $date,          // for self-created tasks date
            $employee['id']  // for admin-assigned tasks
        ]);
        $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // UPDATED: Get combined task summary
        $summary_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE 
                    WHEN (t.status = 'completed' OR ta.status = 'completed') THEN 1 
                    ELSE 0 
                END) as completed_tasks,
                SUM(CASE 
                    WHEN (t.status = 'active' AND (ta.status IS NULL OR ta.status IN ('pending', 'in_progress'))) THEN 1 
                    ELSE 0 
                END) as active_tasks,
                SUM(CASE 
                    WHEN (t.status = 'cancelled' OR ta.status = 'cancelled') THEN 1 
                    ELSE 0 
                END) as cancelled_tasks,
                SUM(CASE 
                    WHEN t.admin_created = 1 THEN 1 
                    ELSE 0 
                END) as assigned_tasks,
                SUM(CASE 
                    WHEN t.admin_created = 0 THEN 1 
                    ELSE 0 
                END) as self_created_tasks
            FROM tasks t
            LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
            WHERE (
                (t.employee_id = ? AND DATE(t.created_at) = ?) OR
                (t.admin_created = 1 AND ta.assigned_to = ?)
            )
        ");
        $summary_stmt->execute([
            $employee['id'], // for task_assignments join
            $employee['id'], // for self-created tasks
            $date,          // for self-created tasks date
            $employee['id']  // for admin-assigned tasks
        ]);
        $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if there's already an active self-created task
        $active_self_task_exists = false;
        foreach ($tasks as $task) {
            if ($task['task_type'] === 'self_created' && $task['status'] === 'active') {
                $active_self_task_exists = true;
                break;
            }
        }
        
        // Can't create task if there's already an active self-created one
        if ($active_self_task_exists) {
            $can_create_task = false;
        }
        
        $response = [
            'tasks' => $tasks,
            'summary' => $summary,
            'can_create_task' => $can_create_task,
            'attendance_status' => $attendance_status
        ];
        
        sendSuccess('Tasks retrieved successfully', $response);
        
    } catch (PDOException $e) {
        error_log("Get tasks error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

// Add this function to api/employee/tasks.php
function updateAssignmentStatus($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }
        
        $assignment_id = (int)($input['assignment_id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
        $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
        
        // Validate required fields
        if (!$assignment_id || !$status) {
            sendError('Assignment ID and status are required', 400);
            return;
        }
        
        // Validate status values
        $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            sendError('Invalid status. Must be one of: ' . implode(', ', $valid_statuses), 400);
            return;
        }
        
        // Verify assignment exists and belongs to employee
        $stmt = $pdo->prepare("
            SELECT ta.*, t.title, t.status as task_status 
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = ? AND ta.assigned_to = ?
        ");
        $stmt->execute([$assignment_id, $employee['id']]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            sendError('Assignment not found or not assigned to you', 404);
            return;
        }
        
        // Prepare update fields based on status
        $update_fields = [];
        $update_values = [];
        
        $update_fields[] = "status = ?";
        $update_values[] = $status;
        
        $update_fields[] = "notes = ?";
        $update_values[] = $notes;
        
        $update_fields[] = "updated_at = NOW()";
        
        // Set timestamps based on status
        if ($status === 'in_progress' && $assignment['started_at'] === null) {
            $update_fields[] = "started_at = NOW()";
        }
        
        if ($status === 'completed') {
            $update_fields[] = "completed_at = NOW()";
            // Also update the main task status to completed
            $update_task_stmt = $pdo->prepare("
                UPDATE tasks SET 
                    status = 'completed',
                    completed_at = NOW(),
                    completion_notes = ?,
                    completion_latitude = ?,
                    completion_longitude = ?
                WHERE id = ?
            ");
            $update_task_stmt->execute([
                $notes,
                $latitude,
                $longitude,
                $assignment['task_id']
            ]);
        }
        
        // Update the assignment
        $update_values[] = $assignment_id;
        $update_values[] = $employee['id'];
        
        $update_sql = "
            UPDATE task_assignments SET " . implode(', ', $update_fields) . "
            WHERE id = ? AND assigned_to = ?
        ";
        
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($update_values);
        
        // Get updated assignment data
        $stmt = $pdo->prepare("
            SELECT ta.*, t.title, t.status as task_status, s.name as site_name,
                   u.username as assigned_by_name
            FROM task_assignments ta
            JOIN tasks t ON ta.task_id = t.id
            JOIN sites s ON t.site_id = s.id
            LEFT JOIN users u ON t.assigned_by = u.id
            WHERE ta.id = ?
        ");
        $stmt->execute([$assignment_id]);
        $updated_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Assignment status updated - ID: $assignment_id, Status: $status, Employee: {$employee['id']}");
        
        sendSuccess('Assignment status updated successfully', $updated_assignment);
        
    } catch (PDOException $e) {
        error_log("Database error in updateAssignmentStatus: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in updateAssignmentStatus: " . $e->getMessage());
        sendError('An error occurred: ' . $e->getMessage(), 500);
    }
}

// Update the main switch statement to handle PATCH requests
// Replace the existing switch statement with:
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
    case 'PATCH':
        updateAssignmentStatus($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
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