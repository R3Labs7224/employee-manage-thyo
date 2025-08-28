<?php
// api/employee/attendance.php - UPDATED: Removed image and time capturing
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
            ORDER BY a.date DESC, a.created_at DESC
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
            }
            
            // Count sessions
            $grouped_attendance[$date]['session_count']++;
            
            // Take last check-out location if exists
            if ($record['check_out_latitude'] && 
                (!$grouped_attendance[$date]['check_out_latitude'] || 
                 $record['created_at'] > $grouped_attendance[$date]['created_at'])) {
                $grouped_attendance[$date]['check_out_latitude'] = $record['check_out_latitude'];
                $grouped_attendance[$date]['check_out_longitude'] = $record['check_out_longitude'];
            }
        }
        
        // Convert back to indexed array and remove helper fields
        $attendance = array_values($grouped_attendance);
        foreach ($attendance as &$record) {
            unset($record['session_count']);
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
            // Create new attendance record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (
                    employee_id, site_id, date,
                    check_in_latitude, check_in_longitude
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $employee['id'], $site_id, $date, 
                $latitude, $longitude
            ]);
            
            sendSuccess('Checked in successfully');
            
        } else { // check_out
            // Find the most recent check-in without a check-out for today
            $stmt = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE employee_id = ? AND date = ? 
                AND check_in_latitude IS NOT NULL 
                AND check_out_latitude IS NULL
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$employee['id'], $date]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                sendError('Must check in first', 400);
            }
            
            // Update attendance record with checkout location only
            $stmt = $pdo->prepare("
                UPDATE attendance SET 
                    check_out_latitude = ?,
                    check_out_longitude = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $latitude, $longitude, $existing['id']
            ]);
            
            sendSuccess('Checked out successfully');
        }
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}
?>