<?php
require_once '../../config/database.php';
require_once '../common/response.php';

// Verify authentication
$token = getAuthHeader();
if (!$token) {
    sendError('Authorization token required', 401);
}

// For admin endpoints, verify admin token instead of employee token
$headers = apache_request_headers();
if (isset($headers['Authorization'])) {
    $admin_token = str_replace('Bearer ', '', $headers['Authorization']);
} else {
    sendError('Admin authorization required', 401);
}

// Simple admin verification (you may want to create a verifyAdminToken function)
// For now, checking if it's a valid user token
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'superadmin')");
// Extract user ID from token (simplified - in production, implement proper admin token verification)
$decoded = base64_decode($admin_token);
$parts = explode(':', $decoded);
if (count($parts) >= 1) {
    $user_id = (int)$parts[0];
    $user_stmt->execute([$user_id]);
    $admin_user = $user_stmt->fetch();
    if (!$admin_user) {
        sendError('Admin authorization required', 401);
    }
} else {
    sendError('Invalid admin token', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getExpensesWithFilters($pdo);
        break;
    case 'PUT':
        updateExpenseStatus($pdo, $admin_user);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getExpensesWithFilters($pdo) {
    try {
        // Build dynamic WHERE clause based on filters
        $where_conditions = [];
        $params = [];

        // Filter by employee
        if (!empty($_GET['employee_id'])) {
            $where_conditions[] = "e.employee_id = ?";
            $params[] = (int)$_GET['employee_id'];
        }

        // Filter by task
        if (!empty($_GET['task_id'])) {
            $where_conditions[] = "e.task_id = ?";
            $params[] = (int)$_GET['task_id'];
        }

        // Filter by category
        if (!empty($_GET['category_id'])) {
            $where_conditions[] = "e.category_id = ?";
            $params[] = (int)$_GET['category_id'];
        }

        // Filter by status
        if (!empty($_GET['status'])) {
            $where_conditions[] = "e.status = ?";
            $params[] = $_GET['status'];
        }

        // Filter by date range
        if (!empty($_GET['start_date'])) {
            $where_conditions[] = "e.request_date >= ?";
            $params[] = $_GET['start_date'];
        }

        if (!empty($_GET['end_date'])) {
            $where_conditions[] = "e.request_date <= ?";
            $params[] = $_GET['end_date'];
        }

        // Filter by specific month (if no date range provided)
        if (empty($_GET['start_date']) && empty($_GET['end_date']) && !empty($_GET['month'])) {
            $where_conditions[] = "DATE_FORMAT(e.request_date, '%Y-%m') = ?";
            $params[] = $_GET['month'];
        }

        // Build WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Main query with all related data
        $main_query = "
            SELECT e.*,
                   emp.name as employee_name,
                   emp.employee_id as employee_code,
                   u.username as approved_by_name,
                   ec.name as category_name,
                   ec.description as category_description,
                   t.title as task_title,
                   t.id as task_id,
                   s.name as site_name
            FROM expenses e
            LEFT JOIN employees emp ON e.employee_id = emp.id
            LEFT JOIN users u ON e.approved_by = u.id
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN tasks t ON e.task_id = t.id
            LEFT JOIN sites s ON t.site_id = s.id
            $where_clause
            ORDER BY e.created_at DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $pdo->prepare($main_query);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        // Count total records for pagination
        $count_query = "
            SELECT COUNT(*) as total
            FROM expenses e
            $where_clause
        ";

        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetch()['total'];

        // Calculate summary statistics with filters applied
        $summary_query = "
            SELECT
                COUNT(*) as total_requests,
                SUM(e.amount) as total_amount,
                SUM(CASE WHEN e.status = 'pending' THEN e.amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN e.status = 'approved' THEN e.amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN e.status = 'rejected' THEN e.amount ELSE 0 END) as rejected_amount,
                COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN e.status = 'rejected' THEN 1 END) as rejected_count
            FROM expenses e
            $where_clause
        ";

        $summary_stmt = $pdo->prepare($summary_query);
        $summary_stmt->execute($params);
        $summary = $summary_stmt->fetch();

        // Get category breakdown
        $category_query = "
            SELECT
                ec.name as category_name,
                COUNT(*) as count,
                SUM(e.amount) as total_amount
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            $where_clause
            GROUP BY e.category_id, ec.name
            ORDER BY total_amount DESC
        ";

        $category_stmt = $pdo->prepare($category_query);
        $category_stmt->execute($params);
        $category_breakdown = $category_stmt->fetchAll();

        $response = [
            'expenses' => $expenses,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total_records / $limit),
                'per_page' => $limit,
                'total_records' => $total_records
            ],
            'summary' => $summary,
            'category_breakdown' => $category_breakdown,
            'filters_applied' => [
                'employee_id' => $_GET['employee_id'] ?? null,
                'task_id' => $_GET['task_id'] ?? null,
                'category_id' => $_GET['category_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'month' => $_GET['month'] ?? null
            ]
        ];

        sendSuccess('Expenses retrieved successfully', $response);

    } catch (PDOException $e) {
        error_log("Get expenses with filters error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}

function updateExpenseStatus($pdo, $admin_user) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data', 400);
            return;
        }

        $expense_id = (int)($input['expense_id'] ?? 0);
        $status = trim($input['status'] ?? '');
        $notes = trim($input['notes'] ?? '');

        // Validate required fields
        if (!$expense_id || !$status) {
            sendError('Expense ID and status are required', 400);
            return;
        }

        // Validate status values
        $valid_statuses = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $valid_statuses)) {
            sendError('Invalid status. Must be one of: ' . implode(', ', $valid_statuses), 400);
            return;
        }

        // Check if expense exists
        $expense_stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
        $expense_stmt->execute([$expense_id]);
        $expense = $expense_stmt->fetch();

        if (!$expense) {
            sendError('Expense not found', 404);
            return;
        }

        // Update expense status
        $update_stmt = $pdo->prepare("
            UPDATE expenses SET
                status = ?,
                approved_by = ?,
                approval_date = NOW(),
                notes = ?
            WHERE id = ?
        ");

        $update_stmt->execute([
            $status,
            $admin_user['id'],
            $notes,
            $expense_id
        ]);

        // Get updated expense
        $stmt = $pdo->prepare("
            SELECT e.*,
                   emp.name as employee_name,
                   u.username as approved_by_name,
                   ec.name as category_name,
                   t.title as task_title
            FROM expenses e
            LEFT JOIN employees emp ON e.employee_id = emp.id
            LEFT JOIN users u ON e.approved_by = u.id
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN tasks t ON e.task_id = t.id
            WHERE e.id = ?
        ");
        $stmt->execute([$expense_id]);
        $updated_expense = $stmt->fetch();

        sendSuccess('Expense status updated successfully', $updated_expense);

    } catch (PDOException $e) {
        error_log("Update expense status error: " . $e->getMessage());
        sendError('Database error occurred', 500);
    }
}
?>