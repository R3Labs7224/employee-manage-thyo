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
        getEmployeeProfile($pdo, $employee);
        break;
    case 'PUT':
        updateEmployeeProfile($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getEmployeeProfile($pdo, $employee) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.id, e.employee_code, e.name, e.email, e.phone, 
                   e.basic_salary, e.daily_wage, e.joining_date,
                   d.name as department_name,
                   s.name as shift_name, s.start_time, s.end_time,
                   st.name as site_name, st.address as site_address
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN shifts s ON e.shift_id = s.id
            LEFT JOIN sites st ON e.site_id = st.id
            WHERE e.id = ?
        ");
        
        $stmt->execute([$employee['id']]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            sendError('Employee profile not found', 404);
        }
        
        // Get attendance stats for current month
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
                SUM(working_hours) as total_hours
            FROM attendance 
            WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
        ");
        
        $stmt->execute([$employee['id']]);
        $attendance_stats = $stmt->fetch();
        
        // Get petty cash stats for current month
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
            FROM petty_cash_requests 
            WHERE employee_id = ? AND MONTH(request_date) = MONTH(CURDATE()) AND YEAR(request_date) = YEAR(CURDATE())
        ");
        
        $stmt->execute([$employee['id']]);
        $petty_cash_stats = $stmt->fetch();
        
        sendSuccess('Profile retrieved successfully', [
            'profile' => $profile,
            'attendance_stats' => $attendance_stats,
            'petty_cash_stats' => $petty_cash_stats
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function updateEmployeeProfile($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Only allow updating certain fields
    $allowed_fields = ['email', 'phone'];
    $updates = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = trim($input[$field]);
        }
    }
    
    if (empty($updates)) {
        sendError('No valid fields to update', 400);
    }
    
    try {
        $params[] = $employee['id'];
        $sql = "UPDATE employees SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendSuccess('Profile updated successfully');
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendError('Email already exists', 400);
        }
        sendError('Database error occurred', 500);
    }
}
?>