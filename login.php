<?php
require_once '../../config/database.php';
require_once '../common/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
validateRequired(['employee_code', 'password'], $input);

$employee_code = trim($input['employee_code']);
$password = trim($input['password']);
$device_info = $input['device_info'] ?? '';
$app_version = $input['app_version'] ?? '';

if (empty($employee_code) || empty($password)) {
    sendError('Employee code and password are required', 400);
}

try {
    // Get employee by employee code with additional details
    $stmt = $pdo->prepare("
        SELECT e.*, 
               d.name as department_name,
               s.name as shift_name, s.start_time, s.end_time,
               st.name as site_name, st.address as site_address,
               st.latitude as site_latitude, st.longitude as site_longitude
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN shifts s ON e.shift_id = s.id
        LEFT JOIN sites st ON e.site_id = st.id
        WHERE e.employee_code = ? AND e.status = 'active'
    ");
    
    $stmt->execute([$employee_code]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        sendError('Invalid employee code or password', 401);
    }
    
    // Verify password
    if (!password_verify($password, $employee['password'])) {
        sendError('Invalid employee code or password', 401);
    }
    
    // Generate secure token
    $token = generateEmployeeToken($employee['id'], $employee['password']);
    
    // Get today's attendance status
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND date = CURDATE()
    ");
    $stmt->execute([$employee['id']]);
    $today_attendance = $stmt->fetch();
    
    // Get current month stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
            SUM(working_hours) as total_hours
        FROM attendance 
        WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
    ");
    $stmt->execute([$employee['id']]);
    $monthly_stats = $stmt->fetch();
    
    // Get pending petty cash amount
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as pending_amount 
        FROM petty_cash_requests 
        WHERE employee_id = ? AND status = 'pending'
    ");
    $stmt->execute([$employee['id']]);
    $pending_petty_cash = $stmt->fetch()['pending_amount'] ?: 0;
    
    // Get active tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_tasks 
        FROM tasks 
        WHERE employee_id = ? AND status = 'active'
    ");
    $stmt->execute([$employee['id']]);
    $active_tasks = $stmt->fetch()['active_tasks'] ?: 0;
    
    // Remove sensitive data
    unset($employee['password']);
    
    // Log successful login
    logActivity($pdo, $employee['id'], 'login', "Device: $device_info, App: $app_version");
    
    sendSuccess('Login successful', [
        'employee' => $employee,
        'token' => $token,
        'today_attendance' => $today_attendance,
        'monthly_stats' => $monthly_stats,
        'pending_petty_cash' => $pending_petty_cash,
        'active_tasks' => $active_tasks,
        'server_time' => date('Y-m-d H:i:s'),
        'permissions' => [
            'can_checkin' => !$today_attendance || !$today_attendance['check_in_time'],
            'can_checkout' => $today_attendance && $today_attendance['check_in_time'] && !$today_attendance['check_out_time'],
            'can_create_task' => $today_attendance && $today_attendance['check_in_time'] && $active_tasks == 0
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendError('Database error occurred', 500);
}
?>