<?php
// api/employee/tasks.php - Final working version
require_once '../../config/database.php';
require_once '../common/response.php';

// Increase limits for image processing
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 60);

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
    $limit = (int)($_GET['limit'] ?? 50);
    
    try {
        // Sanitize limit value and use it directly in query
        $limit = max(1, min(100, $limit));
        
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name, a.date as attendance_date
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            JOIN attendance a ON t.attendance_id = a.id
            WHERE t.employee_id = ? AND DATE(t.created_at) = ?
            ORDER BY t.created_at DESC
            LIMIT " . $limit
        );
        
        $stmt->execute([$employee['id'], $date]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tasks,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks
            FROM tasks 
            WHERE employee_id = ? AND DATE(created_at) = ?
        ");
        
        $stmt->execute([$employee['id'], $date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$summary) {
            $summary = [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'active_tasks' => 0,
                'cancelled_tasks' => 0
            ];
        }
        
        // Check if employee can create new task
        $can_create_task = false;
        $attendance_status = 'not_checked_in';
        
        // Get the latest available checkin/checkout session (not just today)
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ?
            ORDER BY date DESC, check_in_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employee['id']]);
        $latest_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($latest_attendance) {
            if ($latest_attendance['check_in_time'] && !$latest_attendance['check_out_time']) {
                $attendance_status = 'checked_in';
                
                // Check if they have any active tasks
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as active_count
                    FROM tasks 
                    WHERE employee_id = ? AND status = 'active'
                ");
                $stmt->execute([$employee['id']]);
                $active_task_count = $stmt->fetchColumn();
                
                if ($active_task_count == 0) {
                    $can_create_task = true;
                }
            } elseif ($latest_attendance['check_out_time']) {
                $attendance_status = 'checked_out';
            }
        }
        
        sendSuccess('Tasks retrieved successfully', [
            'tasks' => $tasks,
            'summary' => $summary,
            'can_create_task' => $can_create_task,
            'attendance_status' => $attendance_status
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in getTasks: " . $e->getMessage());
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
        $required_fields = ['title', 'site_id', 'latitude', 'longitude'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field])) {
                sendError("Field '$field' is required", 400);
                return;
            }
        }
        
        $title = trim($input['title']);
        $description = trim($input['description'] ?? '');
        $site_id = (int)$input['site_id'];
        $latitude = (float)$input['latitude'];
        $longitude = (float)$input['longitude'];
        
        if (strlen($title) < 3) {
            sendError('Task title must be at least 3 characters long', 400);
            return;
        }
        
        // Check if employee is checked in (using latest session, not just today)
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ?
            ORDER BY date DESC, check_in_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employee['id']]);
        $latest_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$latest_attendance || !$latest_attendance['check_in_time'] || $latest_attendance['check_out_time']) {
            sendError('You must be checked in to create a task', 400);
            return;
        }
        
        // Check if employee has any active tasks
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_count
            FROM tasks 
            WHERE employee_id = ? AND status = 'active'
        ");
        $stmt->execute([$employee['id']]);
        $active_task_count = $stmt->fetchColumn();
        
        if ($active_task_count > 0) {
            sendError('You have an active task. Please complete it before creating a new one', 400);
            return;
        }
        
        // Verify site exists
        $stmt = $pdo->prepare("SELECT id FROM sites WHERE id = ?");
        $stmt->execute([$site_id]);
        if (!$stmt->fetch()) {
            sendError('Invalid site selected', 400);
            return;
        }
        
        // Handle image upload if provided
        $image_filename = null;
        if (isset($input['image']) && !empty($input['image'])) {
            try {
                $image_filename = uploadBase64Image($input['image'], '../../assets/images/uploads/tasks/');
            } catch (Exception $e) {
                sendError('Failed to upload image: ' . $e->getMessage(), 400);
                return;
            }
        }
        
        // Create the task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                employee_id, attendance_id, site_id, title, description, 
                latitude, longitude, image, status, created_at
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
        
        // Get the created task with site name
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccess('Task created successfully', $task);
        
    } catch (PDOException $e) {
        error_log("Database error in createTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
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
        
        if (!$task_id) {
            sendError('Task ID is required', 400);
            return;
        }
        
        // Verify task exists and belongs to employee
        $stmt = $pdo->prepare("
            SELECT * FROM tasks 
            WHERE id = ? AND employee_id = ? AND status = 'active'
        ");
        $stmt->execute([$task_id, $employee['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendError('Task not found or already completed', 404);
            return;
        }
        
        // Check if employee is still checked in
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ?
            ORDER BY date DESC, check_in_time DESC
            LIMIT 1
        ");
        $stmt->execute([$employee['id']]);
        $latest_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$latest_attendance || !$latest_attendance['check_in_time'] || $latest_attendance['check_out_time']) {
            sendError('You must be checked in to complete a task', 400);
            return;
        }
        
        // Handle completion image upload if provided
        $completion_image = null;
        if (isset($input['completion_image']) && !empty($input['completion_image'])) {
            try {
                $completion_image = uploadBase64Image($input['completion_image'], '../../assets/images/uploads/tasks/completed/');
            } catch (Exception $e) {
                sendError('Failed to upload completion image: ' . $e->getMessage(), 400);
                return;
            }
        }
        
        // Update task status to completed
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'completed', 
                completion_notes = ?, 
                completion_latitude = ?, 
                completion_longitude = ?, 
                completion_image = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $completion_notes,
            $latitude,
            $longitude,
            $completion_image,
            $task_id
        ]);
        
        // Get the updated task
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $updated_task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccess('Task completed successfully', $updated_task);
        
    } catch (PDOException $e) {
        error_log("Database error in completeTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>