<?php
// Modified api/employee/attendance.php for multiple check-ins/check-outs
// Maintains exact same API response format as original
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
            ORDER BY a.date DESC, a.check_in_time DESC
        ");
        
        $stmt->execute([$employee['id'], $month]);
        $all_attendance = $stmt->fetchAll();
        
        // Group by date and aggregate for frontend compatibility
        $grouped_attendance = [];
        foreach ($all_attendance as $record) {
            $date = $record['date'];
            if (!isset($grouped_attendance[$date])) {
                // Use the first record found (most recent due to ORDER BY)
                $grouped_attendance[$date] = $record;
                $grouped_attendance[$date]['session_count'] = 0;
                $grouped_attendance[$date]['total_working_hours'] = 0;
            }
            
            // Count sessions and sum working hours
            $grouped_attendance[$date]['session_count']++;
            if ($record['working_hours']) {
                $grouped_attendance[$date]['total_working_hours'] += $record['working_hours'];
            }
            
            // Update working_hours field with total
            $grouped_attendance[$date]['working_hours'] = $grouped_attendance[$date]['total_working_hours'];
            
            // Take last check-out time if exists
            if ($record['check_out_time'] && 
                (!$grouped_attendance[$date]['check_out_time'] || 
                 $record['check_out_time'] > $grouped_attendance[$date]['check_out_time'])) {
                $grouped_attendance[$date]['check_out_time'] = $record['check_out_time'];
                $grouped_attendance[$date]['check_out_latitude'] = $record['check_out_latitude'];
                $grouped_attendance[$date]['check_out_longitude'] = $record['check_out_longitude'];
            }
        }
        
        // Convert back to indexed array and remove helper fields
        $attendance = array_values($grouped_attendance);
        foreach ($attendance as &$record) {
            unset($record['session_count'], $record['total_working_hours']);
        }
        
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
        if ($action === 'check_in') {
            // REMOVED: Check if already checked in today (now allows multiple check-ins)
            
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
            // MODIFIED: Find the most recent check-in without a check-out for today
            $stmt = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE employee_id = ? AND date = ? 
                AND check_in_time IS NOT NULL 
                AND check_out_time IS NULL
                ORDER BY check_in_time DESC
                LIMIT 1
            ");
            $stmt->execute([$employee['id'], $date]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                sendError('Must check in first', 400);
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