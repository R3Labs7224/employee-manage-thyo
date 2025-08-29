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

try {
    // Get employee by employee code
    $stmt = $pdo->prepare("
        SELECT e.*, 
               d.name as department_name,
               s.name as shift_name,
               s.start_time,
               s.end_time,
               st.name as site_name,
               st.address as site_address,
               st.latitude as site_latitude,
               st.longitude as site_longitude
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
    
    // Generate proper token using the existing generateEmployeeToken function
    $token = generateEmployeeToken($employee['id'], $employee['password']);
    
    // Get today's attendance
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND date = CURDATE()
    ");
    $stmt->execute([$employee['id']]);
    $today_attendance = $stmt->fetch();
    
    // Get monthly stats (removed working_hours reference)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as total_approved_sessions
        FROM attendance 
        WHERE employee_id = ? 
        AND MONTH(date) = MONTH(CURDATE()) 
        AND YEAR(date) = YEAR(CURDATE())
    ");
    $stmt->execute([$employee['id']]);
    $monthly_stats = $stmt->fetch();
    
    // Get pending petty cash count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count
        FROM petty_cash_requests 
        WHERE employee_id = ? AND status = 'pending'
    ");
    $stmt->execute([$employee['id']]);
    $pending_petty_cash = $stmt->fetchColumn();
    
    // Get active tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_count
        FROM tasks 
        WHERE employee_id = ? AND status IN ('active', 'in_progress')
    ");
    $stmt->execute([$employee['id']]);
    $active_tasks = $stmt->fetchColumn();
    
    // Remove sensitive data
    unset($employee['password']);
    
    // Prepare response data
    $response_data = [
        'employee' => $employee,
        'token' => $token,
        'today_attendance' => $today_attendance ?: null,
        'monthly_stats' => [
            'total_days' => (int)($monthly_stats['total_days'] ?? 0),
            'approved_days' => (int)($monthly_stats['approved_days'] ?? 0),
            'total_sessions' => (int)($monthly_stats['total_approved_sessions'] ?? 0)
        ],
        'pending_petty_cash' => (int)$pending_petty_cash,
        'active_tasks' => (int)$active_tasks,
        'permissions' => [
            'can_checkin' => true,
            'can_checkout' => true,
            'can_create_task' => true
        ]
    ];
    
    sendSuccess('Login successful', $response_data);
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendError('Database error occurred', 500);
}
?>