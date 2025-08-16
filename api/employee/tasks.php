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
        
        // Check today's attendance
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date = CURDATE()
        ");
        $stmt->execute([$employee['id']]);
        $attendance_today = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attendance_today) {
            if ($attendance_today['check_in_time'] && !$attendance_today['check_out_time']) {
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
            } elseif ($attendance_today['check_out_time']) {
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
        
        // Check if employee is checked in today
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date = CURDATE() 
            AND check_in_time IS NOT NULL
        ");
        $stmt->execute([$employee['id']]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attendance) {
            sendError('You must check in first before creating tasks', 400);
            return;
        }
        
        if ($attendance['check_out_time']) {
            sendError('Cannot create tasks after check out', 400);
            return;
        }
        
        // Check if employee has any active tasks
        $stmt = $pdo->prepare("
            SELECT id FROM tasks 
            WHERE employee_id = ? AND status = 'active'
        ");
        $stmt->execute([$employee['id']]);
        $active_task = $stmt->fetch();
        
        if ($active_task) {
            sendError('You have an active task. Please complete it first', 400);
            return;
        }
        
        // Validate site exists (remove status check if it causes issues)
        $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$site_id]);
        $site = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$site) {
            sendError('Invalid site selected', 400);
            return;
        }
        
        // Handle task image upload
        $image_filename = null;
        $task_image_data = $input['task_image'] ?? $input['image'] ?? null;
        
        if (!empty($task_image_data)) {
            $image_filename = uploadBase64Image($task_image_data, '../../assets/images/uploads/tasks/');
            if (!$image_filename) {
                sendError('Failed to upload task image', 400);
                return;
            }
        }
        
        // Create new task
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("
            INSERT INTO tasks (
                employee_id, site_id, attendance_id, title, description,
                task_image, start_time, latitude, longitude, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $result = $stmt->execute([
            $employee['id'], $site_id, $attendance['id'], $title, $description,
            $image_filename, $current_time, $latitude, $longitude
        ]);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            sendError('Database error: ' . $error[2], 500);
            return;
        }
        
        $task_id = $pdo->lastInsertId();
        
        sendSuccess('Task created successfully', [
            'task_id' => $task_id,
            'title' => $title,
            'site_name' => $site['name'],
            'start_time' => $current_time
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in createTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in createTask: " . $e->getMessage());
        sendError('Error occurred', 500);
    }
}

function completeTask($pdo, $employee) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }
        
        if (!isset($input['task_id'])) {
            sendError('Task ID is required', 400);
            return;
        }
        
        $task_id = (int)$input['task_id'];
        $completion_notes = trim($input['completion_notes'] ?? '');
        $completion_image = $input['completion_image'] ?? '';
        $latitude = (float)($input['latitude'] ?? 0);
        $longitude = (float)($input['longitude'] ?? 0);
        $current_time = date('Y-m-d H:i:s');
        
        // Verify task belongs to employee and is active
        $stmt = $pdo->prepare("
            SELECT t.*, s.name as site_name 
            FROM tasks t
            JOIN sites s ON t.site_id = s.id
            WHERE t.id = ? AND t.employee_id = ? AND t.status = 'active'
        ");
        $stmt->execute([$task_id, $employee['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendError('Task not found or already completed', 404);
            return;
        }
        
        // Handle completion image upload
        $completion_image_filename = null;
        if (!empty($completion_image)) {
            $completion_image_filename = uploadBase64Image($completion_image, '../../assets/images/uploads/tasks/');
            if (!$completion_image_filename) {
                sendError('Failed to upload completion image', 400);
                return;
            }
        }
        
        // Calculate task duration
        $start_time = new DateTime($task['start_time']);
        $end_time = new DateTime($current_time);
        $interval = $end_time->diff($start_time);
        $duration_minutes = $interval->h * 60 + $interval->i;
        
        // Update task as completed
        $description_update = $task['description'];
        if (!empty($completion_notes)) {
            $description_update .= "\n\nCompletion Notes: " . $completion_notes;
        }
        
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET status = 'completed', 
                end_time = ?, 
                description = ?,
                task_image = COALESCE(?, task_image)
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $current_time, 
            $description_update,
            $completion_image_filename, 
            $task_id
        ]);
        
        if (!$result) {
            $error = $stmt->errorInfo();
            sendError('Database error: ' . $error[2], 500);
            return;
        }
        
        sendSuccess('Task completed successfully', [
            'task_id' => $task_id,
            'title' => $task['title'],
            'site_name' => $task['site_name'],
            'duration_minutes' => $duration_minutes,
            'end_time' => $current_time
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error in completeTask: " . $e->getMessage());
        sendError('Database error occurred', 500);
    } catch (Exception $e) {
        error_log("General error in completeTask: " . $e->getMessage());
        sendError('Error occurred', 500);
    }
}

function uploadBase64Image($base64_string, $upload_dir) {
    try {
        // Remove data:image/jpeg;base64, prefix if present
        if (strpos($base64_string, 'data:image') === 0) {
            $base64_string = substr($base64_string, strpos($base64_string, ',') + 1);
        }
        
        $image_data = base64_decode($base64_string);
        if ($image_data === false) {
            return null;
        }
        
        // Check image size (limit to 5MB)
        if (strlen($image_data) > 5 * 1024 * 1024) {
            return null;
        }
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return null;
            }
        }
        
        $filename = uniqid() . '.jpg';
        $file_path = $upload_dir . $filename;
        
        if (file_put_contents($file_path, $image_data)) {
            return $filename;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error in uploadBase64Image: " . $e->getMessage());
        return null;
    }
}
?>