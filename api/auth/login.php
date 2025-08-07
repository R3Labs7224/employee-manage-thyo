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
               st.name as site_name
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
    
    // Create simple token (employee_id:password_hash encoded in base64)
    // In production, use JWT or proper session tokens
    $token = base64_encode($employee['id'] . ':' . $password);
    
    // Remove sensitive data
    unset($employee['password']);
    
    sendSuccess('Login successful', [
        'employee' => $employee,
        'token' => $token
    ]);
    
} catch (PDOException $e) {
    sendError('Database error occurred', 500);
}
?>