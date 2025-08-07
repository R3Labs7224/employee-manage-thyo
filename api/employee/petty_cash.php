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
        getPettyCashRequests($pdo, $employee);
        break;
    case 'POST':
        createPettyCashRequest($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getPettyCashRequests($pdo, $employee) {
    $month = $_GET['month'] ?? date('Y-m');
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as approved_by_name
            FROM petty_cash_requests p
            LEFT JOIN users u ON p.approved_by = u.id
            WHERE p.employee_id = ? AND DATE_FORMAT(p.request_date, '%Y-%m') = ?
            ORDER BY p.created_at DESC
        ");
        
        $stmt->execute([$employee['id'], $month]);
        $requests = $stmt->fetchAll();
        
        // Get summary stats
        $total_amount = 0;
        $approved_amount = 0;
        $pending_amount = 0;
        $rejected_amount = 0;
        
        foreach ($requests as $request) {
            $total_amount += $request['amount'];
            switch ($request['status']) {
                case 'approved':
                    $approved_amount += $request['amount'];
                    break;
                case 'pending':
                    $pending_amount += $request['amount'];
                    break;
                case 'rejected':
                    $rejected_amount += $request['amount'];
                    break;
            }
        }
        
        sendSuccess('Petty cash requests retrieved successfully', [
            'requests' => $requests,
            'summary' => [
                'total_requests' => count($requests),
                'total_amount' => $total_amount,
                'approved_amount' => $approved_amount,
                'pending_amount' => $pending_amount,
                'rejected_amount' => $rejected_amount
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}

function createPettyCashRequest($pdo, $employee) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    validateRequired(['amount', 'reason'], $input);
    
    $amount = (float)$input['amount'];
    $reason = trim($input['reason']);
    $request_date = $input['request_date'] ?? date('Y-m-d');
    
    if ($amount <= 0) {
        sendError('Amount must be greater than zero', 400);
    }
    
    if (strlen($reason) < 10) {
        sendError('Reason must be at least 10 characters long', 400);
    }
    
    try {
        // Handle receipt image upload
        $receipt_filename = null;
        if (isset($input['receipt_image']) && !empty($input['receipt_image'])) {
            $receipt_filename = uploadBase64Image($input['receipt_image'], '../../assets/images/uploads/petty_cash/');
        }
        
        // Create new petty cash request
        $stmt = $pdo->prepare("
            INSERT INTO petty_cash_requests (
                employee_id, amount, reason, receipt_image, request_date
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $employee['id'], $amount, $reason, $receipt_filename, $request_date
        ]);
        
        $request_id = $pdo->lastInsertId();
        
        sendSuccess('Petty cash request submitted successfully', ['request_id' => $request_id]);
        
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