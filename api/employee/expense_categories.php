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
        getExpenseCategories($pdo);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getExpenseCategories($pdo) {
    try {
        // Get all active expense categories
        $stmt = $pdo->prepare("
            SELECT id, name, description
            FROM expense_categories
            WHERE is_active = 1
            ORDER BY name ASC
        ");

        $stmt->execute();
        $categories = $stmt->fetchAll();

        sendSuccess('Expense categories retrieved successfully', $categories);

    } catch (PDOException $e) {
        error_log("Get expense categories error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>