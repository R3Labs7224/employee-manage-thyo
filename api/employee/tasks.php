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
        // Get tasks for specific date or recent tasks
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name, a.date as attendance_date
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            JOIN attendance a ON t.attendance_id = a.id
            WHERE t.employee_id = ? AND DATE(t.created_at) = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$employee['id'], $date, $limit]);
        $tasks = $stmt->fetchAll();
        
        // Get summary stats for today
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
        $summary = $stmt->fetch();
        
        // Check if employee can create new task
        $can_create_task = false;
        $stmt = $pdo->prepare("
            SELECT a.*, COUNT(t.id) as active_task_count
            FROM attendance a
            LEFT JOIN tasks t ON a.id = t.attendance_id AND t.status = 'active'
            WHERE a.employee_id = ? AND a.date = CURDATE() AND a.check_in_time IS NOT NULL
            GROUP BY a.id
        ");
        $stmt->execute([$employee['id']]);
        $attendance_check = $stmt->fetch();
        
        if ($attendance_check && $attendance_check['active_task_count'] == 0) {
            $can_create_task = true;
        }
        
        sendSuccess('Tasks retrieved successfully', [
            'tasks' => $tasks,
            'summary' => $summary,
            'can_create_task' => $can_create_task,
            'attendance_status' => $attendance_check ? 'checked_in' : 'not_checked_in'
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function createTask($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired(['title', 'site_id', 'latitude', 'longitude'], $input);
    
    $title = trim($input['title']);
    $description = trim($input['description'] ?? '');
    $site_id = (int)$input['site_id'];
    $latitude = (float)$input['latitude'];
    $longitude = (float)$input['longitude'];
    $current_time = date('Y-m-d H:i:s');
    
    if (strlen($title) < 3) {
        sendError('Task title must be at least 3 characters long', 400);
    }
    
    try {
        // Check if employee is checked in today
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date = CURDATE() 
            AND check_in_time IS NOT NULL
        ");
        $stmt->execute([$employee['id']]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            sendError('You must check in first before creating tasks', 400);
        }
        
        if ($attendance['check_out_time']) {
            sendError('Cannot create tasks after check out', 400);
        }
        
        // Check if employee has any active tasks
        $stmt = $pdo->prepare("
            SELECT id FROM tasks 
            WHERE employee_id = ? AND status = 'active'
        ");
        $stmt->execute([$employee['id']]);
        $active_task = $stmt->fetch();
        
        if ($active_task) {
            sendError('You have an active task. Please complete it first before creating a new one', 400);
        }
        
        // Validate site exists
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ? AND status = 'active'");
        $stmt->execute([$site_id]);
        $site = $stmt->fetch();
        
        if (!$site) {
            sendError('Invalid site selected', 400);
        }
        
        // Check if task location is within reasonable distance from check-in location
        if ($attendance['check_in_latitude'] && $attendance['check_in_longitude']) {
            $distance = calculateDistance(
                $latitude, $longitude,
                $attendance['check_in_latitude'], $attendance['check_in_longitude']
            );
            
            // Allow 5km radius from check-in location
            if ($distance > 5000) {
                sendError('Task location is too far from your check-in location', 400);
            }
        }
        
        // Handle task image upload
        $image_filename = null;
        if (isset($input['image']) && !empty($input['image'])) {
            $image_filename = uploadBase64Image($input['image'], '../../assets/images/uploads/tasks/');
            if (!$image_filename) {
                sendError('Failed to upload task image. Please try again.', 400);
            }
        }
        
        // Create new task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                employee_id, site_id, attendance_id, title, description,
                task_image, start_time, latitude, longitude
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $employee['id'], $site_id, $attendance['id'], $title, $description,
            $image_filename, $current_time, $latitude, $longitude
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($pdo, $employee['id'], 'task_created', "Task: $title, Site: {$site['name']}");
        
        sendSuccess('Task created successfully', [
            'task_id' => $task_id,
            'title' => $title,
            'site_name' => $site['name'],
            'start_time' => $current_time
        ]);
        
    } catch (PDOException $e) {
        error_log("Task creation error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

function completeTask($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired(['task_id'], $input);
    
    $task_id = (int)$input['task_id'];
    $completion_notes = trim($input['completion_notes'] ?? '');
    $completion_image = $input['completion_image'] ?? '';
    $latitude = (float)($input['latitude'] ?? 0);
    $longitude = (float)($input['longitude'] ?? 0);
    $current_time = date('Y-m-d H:i:s');
    
    try {
        // Verify task belongs to employee and is active
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name 
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ? AND t.employee_id = ? AND t.status = 'active'
        ");
        $stmt->execute([$task_id, $employee['id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            sendError('Task not found or already completed', 404);
        }
        
        // Handle completion image upload
        $completion_image_filename = null;
        if (!empty($completion_image)) {
            $completion_image_filename = uploadBase64Image($completion_image, '../../assets/images/uploads/tasks/');
        }
        
        // Calculate task duration
        $start_time = new DateTime($task['start_time']);
        $end_time = new DateTime($current_time);
        $duration_minutes = $end_time->diff($start_time)->i + ($end_time->diff($start_time)->h * 60);
        
        // Update task as completed
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'completed', end_time = ?, 
                description = CONCAT(description, CASE WHEN ? != '' THEN CONCAT('\n\nCompletion Notes: ', ?) ELSE '' END),
                task_image = CASE WHEN ? IS NOT NULL THEN ? ELSE task_image END
            WHERE id = ?
        ");
        $stmt->execute([
            $current_time, $completion_notes, $completion_notes, 
            $completion_image_filename, $completion_image_filename, $task_id
        ]);
        
        // Log activity
        logActivity($pdo, $employee['id'], 'task_completed', "Task: {$task['title']}, Duration: {$duration_minutes} minutes");
        
        sendSuccess('Task completed successfully', [
            'task_id' => $task_id,
            'title' => $task['title'],
            'site_name' => $task['site_name'],
            'duration_minutes' => $duration_minutes,
            'end_time' => $current_time
        ]);
        
    } catch (PDOException $e) {
        error_log("Task completion error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>