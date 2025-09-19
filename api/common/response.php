<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function sendResponse($success = true, $message = '', $data = null, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'server_time' => time()
    ]);
    exit();
}

function sendError($message = 'An error occurred', $status_code = 400, $data = null) {
    sendResponse(false, $message, $data, $status_code);
}

function sendSuccess($message = 'Success', $data = null) {
    sendResponse(true, $message, $data, 200);
}

function validateRequired($fields, $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendError('Missing required fields: ' . implode(', ', $missing), 422);
    }
}

function getAuthHeader() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        return str_replace('Bearer ', '', $headers['Authorization']);
    }
    if (isset($headers['authorization'])) {
        return str_replace('Bearer ', '', $headers['authorization']);
    }
    return null;
}

function verifyEmployeeToken($pdo, $token) {
    if (!$token) {
        return false;
    }
    
    try {
        // Decode the token (employee_id:timestamp:hash)
        $decoded = base64_decode($token);
        $parts = explode(':', $decoded);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        $employee_id = (int)$parts[0];
        $timestamp = (int)$parts[1];
        $hash = $parts[2];
        
        // Check if token is not expired (24 hours)
        if (time() - $timestamp > 86400) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active'");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            // Verify hash
            $expected_hash = hash('sha256', $employee_id . ':' . $timestamp . ':' . $employee['password']);
            if (hash_equals($expected_hash, $hash)) {
                return $employee;
            }
        }
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
    
    return false;
}

function generateEmployeeToken($employee_id, $password_hash) {
    $timestamp = time();
    $hash = hash('sha256', $employee_id . ':' . $timestamp . ':' . $password_hash);
    return base64_encode($employee_id . ':' . $timestamp . ':' . $hash);
}

// Log activity function
function logActivity($pdo, $employee_id, $action, $details = null) {
    try {
        // Check if activity_logs table exists first
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (employee_id, action, details, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$employee_id, $action, $details]);
        } else {
            // Just log to error log if table doesn't exist
            error_log("Activity: Employee $employee_id - $action - $details");
        }
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        // Don't throw error, just log it
    }
}

// Check if user has permission
function hasPermission($user, $permission) {
    if ($user['role'] === 'superadmin') {
        return true;
    }
    
    // Define role permissions
    $permissions = [
        'admin' => [
            'view_employees',
            'add_employee', 
            'edit_employee',
            'view_attendance',
            'manage_attendance',
            'view_reports',
            'manage_expenses',
            'view_salary'
        ],
        'hr' => [
            'view_employees',
            'add_employee',
            'edit_employee', 
            'view_attendance',
            'view_reports',
            'manage_petty_cash'
        ],
        'manager' => [
            'view_employees',
            'view_attendance',
            'view_reports'
        ]
    ];
    
    $userPermissions = $permissions[$user['role']] ?? [];
    return in_array($permission, $userPermissions);
}

// Validate employee permissions for mobile app
function validateEmployeePermission($employee, $action) {
    // Basic permission checks for employee actions
    switch ($action) {
        case 'checkin':
        case 'checkout':
            return $employee['status'] === 'active';
        case 'create_task':
            return $employee['status'] === 'active';
        case 'expense_request':
            return $employee['status'] === 'active';
        default:
            return false;
    }
}
?>