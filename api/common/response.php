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
        return false;
    }
    
    return false;
}

function generateEmployeeToken($employee_id, $password_hash) {
    $timestamp = time();
    $hash = hash('sha256', $employee_id . ':' . $timestamp . ':' . $password_hash);
    return base64_encode($employee_id . ':' . $timestamp . ':' . $hash);
}

function uploadBase64Image($base64_string, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png']) {
    if (empty($base64_string)) {
        return null;
    }
    
    // Remove data:image/jpeg;base64, prefix if present
    if (strpos($base64_string, 'data:image') === 0) {
        $base64_string = substr($base64_string, strpos($base64_string, ',') + 1);
    }
    
    $image_data = base64_decode($base64_string);
    if ($image_data === false) {
        return null;
    }
    
    // Validate image
    $temp_file = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($temp_file, $image_data);
    
    $image_info = getimagesize($temp_file);
    if ($image_info === false) {
        unlink($temp_file);
        return null;
    }
    
    // Check file type
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png'
    ];
    
    $extension = $mime_to_ext[$image_info['mime']] ?? null;
    if (!$extension || !in_array($extension, $allowed_types)) {
        unlink($temp_file);
        return null;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    if (rename($temp_file, $file_path)) {
        return $filename;
    }
    
    unlink($temp_file);
    return null;
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meters
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat/2) * sin($delta_lat/2) + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lon/2) * sin($delta_lon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

function logActivity($pdo, $employee_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (employee_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $employee_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>