<?php
require_once '../../config/database.php';
require_once '../common/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Verify authentication
$token = getAuthHeader();
if (!$token) {
    sendError('Authorization token required', 401);
}

$employee = verifyEmployeeToken($pdo, $token);
if (!$employee) {
    sendError('Invalid or expired token', 401);
}

try {
    // Log logout activity
    logActivity($pdo, $employee['id'], 'logout', 'Mobile app logout');
    
    // In a real implementation, you might want to blacklist the token
    // For now, we'll just log the activity and return success
    
    sendSuccess('Logged out successfully', [
        'message' => 'You have been logged out successfully'
    ]);
    
} catch (PDOException $e) {
    sendError('Database error occurred', 500);
}
?>