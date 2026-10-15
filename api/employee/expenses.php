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

    // Validate task if provided (must be assigned to employee in unified tasks table)
    if ($task_id) {
        $task_stmt = $pdo->prepare("
            SELECT id FROM tasks
            WHERE id = ? AND assigned_to_employee_id = ?
        ");
        $task_stmt->execute([$task_id, $employee['id']]);
        if (!$task_stmt->fetch()) {
            sendError('Invalid task selected or task not assigned to you', 400);
        }
    }

    try {
        // Handle receipt image upload
        $receipt_filename = null;
        if (isset($input['receipt_image']) && !empty($input['receipt_image'])) {
            try {
                $receipt_filename = uploadBase64Image($input['receipt_image'], '../../assets/images/uploads/expenses/');
                error_log("Receipt image uploaded successfully: $receipt_filename");
            } catch (Exception $e) {
                error_log("Receipt image upload failed: " . $e->getMessage());
                sendError('Failed to upload receipt image: ' . $e->getMessage(), 400);
                return;
            }
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
    try {
        if (empty($base64_string)) {
            throw new Exception('Empty base64 string provided');
        }

        error_log("Starting receipt image upload to directory: $upload_dir");

        // Remove data:image/jpeg;base64, prefix if present
        if (strpos($base64_string, 'data:image') === 0) {
            $comma_pos = strpos($base64_string, ',');
            if ($comma_pos === false) {
                throw new Exception('Invalid base64 format - missing comma separator');
            }
            $base64_string = substr($base64_string, $comma_pos + 1);
        }

        // Decode base64
        $image_data = base64_decode($base64_string, true);
        if ($image_data === false) {
            throw new Exception('Invalid base64 image data - decode failed');
        }

        $image_size = strlen($image_data);
        error_log("Decoded receipt image size: $image_size bytes");

        // Validate image size (max 10MB)
        if ($image_size > 10 * 1024 * 1024) {
            throw new Exception('Image too large - maximum 10MB allowed');
        }

        if ($image_size < 100) {
            throw new Exception('Image too small - minimum 100 bytes required');
        }

        // Get absolute path
        $abs_upload_dir = realpath(dirname(__FILE__)) . '/' . $upload_dir;
        error_log("Absolute upload directory: $abs_upload_dir");

        // Create directory if it doesn't exist
        if (!file_exists($abs_upload_dir)) {
            error_log("Creating directory: $abs_upload_dir");
            if (!mkdir($abs_upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory: ' . $abs_upload_dir);
            }
        }

        // Check if directory is writable
        if (!is_writable($abs_upload_dir)) {
            error_log("Directory not writable, attempting to fix permissions: $abs_upload_dir");
            if (!chmod($abs_upload_dir, 0755)) {
                throw new Exception('Upload directory is not writable and cannot fix permissions: ' . $abs_upload_dir);
            }
        }

        // Generate unique filename
        $filename = 'expense_receipt_' . uniqid() . '_' . time() . '.jpg';
        $file_path = $abs_upload_dir . '/' . $filename;

        error_log("Writing receipt image to: $file_path");

        // Write the file
        $bytes_written = file_put_contents($file_path, $image_data);
        if ($bytes_written === false) {
            throw new Exception('Failed to write image file: ' . $file_path);
        }

        error_log("Receipt image written successfully. Bytes: $bytes_written");

        // Verify the file was created and has correct size
        if (!file_exists($file_path)) {
            throw new Exception('Image file was not created successfully');
        }

        $actual_file_size = filesize($file_path);
        if ($actual_file_size !== $image_size) {
            error_log("Size mismatch: expected $image_size, got $actual_file_size");
            unlink($file_path); // Clean up
            throw new Exception('Image file size verification failed');
        }

        // Set proper permissions
        chmod($file_path, 0644);

        error_log("Receipt image upload completed successfully: $filename");
        return $filename;

    } catch (Exception $e) {
        error_log("Receipt image upload error: " . $e->getMessage());
        throw $e;
    }
}
?>