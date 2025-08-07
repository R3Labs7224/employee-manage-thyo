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

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getAttendanceHistory($pdo, $employee);
        break;
    case 'POST':
        handleCheckInOut($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getAttendanceHistory($pdo, $employee) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as site_name
            FROM attendance a
            JOIN sites s ON a.site_id = s.id
            WHERE a.employee_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
            ORDER BY a.date DESC
        ");
        
        $stmt->execute([$employee['id'], $month]);
        $attendance = $stmt->fetchAll();
        
        sendSuccess('Attendance history retrieved', $attendance);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function handleCheckInOut($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired(['action', 'site_id', 'latitude', 'longitude'], $input);
    
    $action = $input['action']; // 'check_in' or 'check_out'
    $site_id = (int)$input['site_id'];
    $latitude = (float)$input['latitude'];
    $longitude = (float)$input['longitude'];
    $date = date('Y-m-d');
    
    if (!in_array($action, ['check_in', 'check_out'])) {
        sendError('Invalid action. Must be check_in or check_out', 400);
    }
    
    try {
        // Check if attendance record exists for today
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$employee['id'], $date]);
        $existing = $stmt->fetch();
        
        if ($action === 'check_in') {
            if ($existing) {
                sendError('Already checked in today', 400);
            }
            
            // Handle selfie upload (base64)
            $selfie_filename = null;
            if (isset($input['selfie']) && !empty($input['selfie'])) {
                $selfie_filename = uploadBase64Image($input['selfie'], '../../assets/images/uploads/attendance/');
            }
            
            // Create new attendance record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (
                    employee_id, site_id, date, check_in_time, 
                    check_in_latitude, check_in_longitude, check_in_selfie
                ) VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $employee['id'], $site_id, $date, 
                $latitude, $longitude, $selfie_filename
            ]);
            
            sendSuccess('Checked in successfully');
            
        } else { // check_out
            if (!$existing) {
                sendError('Must check in first', 400);
            }
            
            if ($existing['check_out_time']) {
                sendError('Already checked out today', 400);
            }
            
            // Handle selfie upload
            $selfie_filename = null;
            if (isset($input['selfie']) && !empty($input['selfie'])) {
                $selfie_filename = uploadBase64Image($input['selfie'], '../../assets/images/uploads/attendance/');
            }
            
            // Calculate working hours
            $check_in = new DateTime($existing['check_in_time']);
            $check_out = new DateTime();
            $working_hours = $check_out->diff($check_in)->h + ($check_out->diff($check_in)->i / 60);
            
            // Update attendance record
            $stmt = $pdo->prepare("
                UPDATE attendance SET 
                    check_out_time = NOW(),
                    check_out_latitude = ?,
                    check_out_longitude = ?,
                    check_out_selfie = ?,
                    working_hours = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $latitude, $longitude, $selfie_filename, 
                $working_hours, $existing['id']
            ]);
            
            sendSuccess('Checked out successfully', [
                'working_hours' => round($working_hours, 2)
            ]);
        }
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function uploadBase64Image($base64_string, $upload_dir) {
    // Remove data:image/jpeg;base64, prefix if present
    if (strpos($base64_string, 'data:image') === 0) {
        $base64_string = substr($base64_string, strpos($base64_string, ',') + 1);
    }
    
    $image_data = base64_decode($base64_string);
    if ($image_data === false) {
        return null;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '.jpg';
    $file_path = $upload_dir . $filename;
    
    if (file_put_contents($file_path, $image_data)) {
        return $filename;
    }
    
    return null;
}
?>