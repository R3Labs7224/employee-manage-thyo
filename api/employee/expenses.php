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
        getExpenseRequests($pdo, $employee);
        break;
    case 'POST':
        createExpenseRequest($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getExpenseRequests($pdo, $employee) {
    $month = $_GET['month'] ?? date('Y-m');

    try {
        // Get all expense requests for the employee in the specified month with category and task info
        $stmt = $pdo->prepare("
            SELECT e.*,
                   u.username as approved_by_name,
                   ec.name as category_name,
                   ec.description as category_description,
                   t.title as task_title,
                   t.id as task_id
            FROM expenses e
            LEFT JOIN users u ON e.approved_by = u.id
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN tasks t ON e.task_id = t.id
            WHERE e.employee_id = ? AND DATE_FORMAT(e.request_date, '%Y-%m') = ?
            ORDER BY e.created_at DESC
        ");

        $stmt->execute([$employee['id'], $month]);
        $all_requests = $stmt->fetchAll();
        
        // Separate requests by status
        $pending_requests = [];
        $approved_requests = [];
        $rejected_requests = [];
        
        foreach ($all_requests as $request) {
            switch ($request['status']) {
                case 'pending':
                    $pending_requests[] = $request;
                    break;
                case 'approved':
                    $approved_requests[] = $request;
                    break;
                case 'rejected':
                    $rejected_requests[] = $request;
                    break;
            }
        }
        
        $requests_to_return = [];
        
        if (count($pending_requests) > 0) {
            // If there are pending requests, return pending + rejected (exclude approved)
            $requests_to_return = array_merge($pending_requests, $rejected_requests);
        } else {
            // If no pending requests exist, return empty list (even if rejected exist)
            $requests_to_return = [];
        }
        
        // Calculate summary stats for returned requests only
        $total_amount = 0;
        $pending_amount = 0;
        $rejected_amount = 0;
        
        foreach ($requests_to_return as $request) {
            $total_amount += $request['amount'];
            if ($request['status'] === 'pending') {
                $pending_amount += $request['amount'];
            } else if ($request['status'] === 'rejected') {
                $rejected_amount += $request['amount'];
            }
        }
        
        sendSuccess('Expense requests retrieved successfully', [
            'requests' => $requests_to_return,
            'summary' => [
                'total_requests' => count($requests_to_return),
                'total_amount' => $total_amount,
                'approved_amount' => 0, // Never include approved amount in summary
                'pending_amount' => $pending_amount,
                'rejected_amount' => $rejected_amount
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function createExpenseRequest($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);

    validateRequired(['amount', 'reason', 'category_id'], $input);

    $amount = (float)$input['amount'];
    $reason = trim($input['reason']);
    $category_id = (int)$input['category_id'];
    $task_id = isset($input['task_id']) ? (int)$input['task_id'] : null;
    $receipt_number = trim($input['receipt_number'] ?? '');
    $request_date = $input['request_date'] ?? date('Y-m-d');

    if ($amount <= 0) {
        sendError('Amount must be greater than zero', 400);
    }

    if (strlen($reason) < 10) {
        sendError('Reason must be at least 10 characters long', 400);
    }

    // Validate category exists
    $category_stmt = $pdo->prepare("SELECT id FROM expense_categories WHERE id = ? AND is_active = 1");
    $category_stmt->execute([$category_id]);
    if (!$category_stmt->fetch()) {
        sendError('Invalid category selected', 400);
    }

    // Validate task if provided (must belong to employee or be assigned to them)
    if ($task_id) {
        $task_stmt = $pdo->prepare("
            SELECT t.id FROM tasks t
            LEFT JOIN task_assignments ta ON t.id = ta.task_id
            WHERE t.id = ? AND (
                t.employee_id = ? OR
                (t.admin_created = 1 AND ta.assigned_to = ?)
            )
        ");
        $task_stmt->execute([$task_id, $employee['id'], $employee['id']]);
        if (!$task_stmt->fetch()) {
            sendError('Invalid task selected or task not assigned to you', 400);
        }
    }

    try {
        // Handle receipt image upload
        $receipt_filename = null;
        if (isset($input['receipt_image']) && !empty($input['receipt_image'])) {
            $receipt_filename = uploadBase64Image($input['receipt_image'], '../../assets/images/uploads/expenses/');
        }

        // Create new expense request
        $stmt = $pdo->prepare("
            INSERT INTO expenses (
                employee_id, amount, category_id, task_id, reason,
                receipt_image, receipt_number, request_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $employee['id'], $amount, $category_id, $task_id, $reason,
            $receipt_filename, $receipt_number, $request_date
        ]);

        $request_id = $pdo->lastInsertId();

        sendSuccess('Expense request submitted successfully', ['request_id' => $request_id]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function uploadBase64Image($base64_string, $upload_dir) {
    // Remove data:image/jpeg;base64, prefix if present
    if (strpos($base64_string, 'data:image') === 0) {
        $base64_string = substr($base64_string, strpos($base64_string, ',') + 1);
    }
    
    $image_data = base64_decode($base64_string);
    if ($image_data === false) {
        return null;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '.jpg';
    $file_path = $upload_dir . $filename;
    
    if (file_put_contents($file_path, $image_data)) {
        return $filename;
    }
    
    return null;
}
?>