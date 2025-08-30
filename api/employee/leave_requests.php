<?php
require_once '../../config/database.php';
require_once '../common/response.php';

// Set content type and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get and verify authentication token
$token = getAuthHeader();
if (!$token) {
    sendError('Authorization token required', 401);
    exit;
}

$employee = verifyEmployeeToken($pdo, $token);
if (!$employee) {
    sendError('Invalid or expired token', 401);
    exit;
}

// Route based on method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getLeaveRequests($pdo, $employee);
        break;
    case 'POST':
        createLeaveRequest($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
        break;
}

function getLeaveRequests($pdo, $employee) {
    try {
        // Get query parameters
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        // Build query conditions
        $where_conditions = ['lr.employee_id = ?'];
        $params = [$employee['id']];
        
        if (!empty($status)) {
            $valid_statuses = ['pending', 'approved_l1', 'approved_l2', 'rejected'];
            if (in_array($status, $valid_statuses)) {
                $where_conditions[] = 'lr.status = ?';
                $params[] = $status;
            }
        }
        
        // Get total count for pagination
        $count_sql = "
            SELECT COUNT(*) as total
            FROM leave_requests lr
            WHERE " . implode(' AND ', $where_conditions);
        
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total = (int)$count_stmt->fetchColumn();
        
        // Get leave requests with approver details - FIXED QUERY
        // Using string concatenation for LIMIT/OFFSET to avoid PDO quoting issues
        $sql = "
            SELECT lr.id, lr.start_date, lr.end_date, lr.leave_type, lr.reason, 
                   lr.total_days, lr.status, lr.created_at, lr.updated_at,
                   lr.l1_approved_by, lr.l1_approval_date, lr.l1_comments,
                   lr.l2_approved_by, lr.l2_approval_date, lr.l2_comments,
                   lr.rejected_by, lr.rejection_date, lr.rejection_reason,
                   l1.username as l1_approver_name,
                   l2.username as l2_approver_name,
                   r.username as rejected_by_name
            FROM leave_requests lr
            LEFT JOIN users l1 ON lr.l1_approved_by = l1.id
            LEFT JOIN users l2 ON lr.l2_approved_by = l2.id
            LEFT JOIN users r ON lr.rejected_by = r.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY lr.created_at DESC
            LIMIT " . $limit . " OFFSET " . $offset;
        
        // Don't add limit and offset to params - they're directly in the query
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formatted_requests = array_map(function($request) {
            return [
                'id' => (int)$request['id'],
                'start_date' => $request['start_date'],
                'end_date' => $request['end_date'],
                'leave_type' => $request['leave_type'],
                'reason' => $request['reason'],
                'total_days' => (int)$request['total_days'],
                'status' => $request['status'],
                'status_display' => getStatusDisplay($request['status']),
                'l1_approval' => [
                    'approved_by' => $request['l1_approver_name'],
                    'approval_date' => $request['l1_approval_date'],
                    'comments' => $request['l1_comments']
                ],
                'l2_approval' => [
                    'approved_by' => $request['l2_approver_name'],
                    'approval_date' => $request['l2_approval_date'],
                    'comments' => $request['l2_comments']
                ],
                'rejection' => [
                    'rejected_by' => $request['rejected_by_name'],
                    'rejection_date' => $request['rejection_date'],
                    'reason' => $request['rejection_reason']
                ],
                'created_at' => $request['created_at'],
                'updated_at' => $request['updated_at']
            ];
        }, $leave_requests);
        
        // Prepare pagination info
        $has_more = ($offset + $limit) < $total;
        
        sendSuccess('Leave requests fetched successfully', [
            'requests' => $formatted_requests,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $has_more,
                'current_count' => count($formatted_requests)
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Get leave requests error: " . $e->getMessage());
        sendError('Failed to fetch leave requests', 500);
    } catch (Exception $e) {
        error_log("Get leave requests error: " . $e->getMessage());
        sendError('An error occurred while fetching leave requests', 500);
    }
}

function createLeaveRequest($pdo, $employee) {
    try {
        // Get and validate JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendError('Invalid JSON data provided', 400);
            return;
        }
        
        // Validate required fields
        $start_date = trim($input['start_date'] ?? '');
        $end_date = trim($input['end_date'] ?? '');
        $leave_type = trim($input['leave_type'] ?? '');
        $reason = trim($input['reason'] ?? '');
        
        if (empty($start_date)) {
            sendError('Start date is required', 400);
            return;
        }
        
        if (empty($end_date)) {
            sendError('End date is required', 400);
            return;
        }
        
        if (empty($leave_type)) {
            sendError('Leave type is required', 400);
            return;
        }
        
        if (empty($reason)) {
            sendError('Reason for leave is required', 400);
            return;
        }
        
        // Validate leave type
        if (!in_array($leave_type, ['paid', 'unpaid'])) {
            sendError('Invalid leave type. Must be "paid" or "unpaid"', 400);
            return;
        }
        
        // Validate and parse dates
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        $today = strtotime(date('Y-m-d'));
        
        if ($start_timestamp === false) {
            sendError('Invalid start date format. Use YYYY-MM-DD', 400);
            return;
        }
        
        if ($end_timestamp === false) {
            sendError('Invalid end date format. Use YYYY-MM-DD', 400);
            return;
        }
        
        if ($start_timestamp < $today) {
            sendError('Start date cannot be in the past', 400);
            return;
        }
        
        if ($end_timestamp < $start_timestamp) {
            sendError('End date cannot be before start date', 400);
            return;
        }
        
        // Calculate total days
        $total_days = ceil(($end_timestamp - $start_timestamp) / (60 * 60 * 24)) + 1;
        
        if ($total_days > 30) {
            sendError('Leave request cannot exceed 30 days', 400);
            return;
        }
        
        if (strlen($reason) < 10) {
            sendError('Please provide a more detailed reason (minimum 10 characters)', 400);
            return;
        }
        
        if (strlen($reason) > 1000) {
            sendError('Reason is too long (maximum 1000 characters)', 400);
            return;
        }
        
        // Check for overlapping requests
        $overlap_stmt = $pdo->prepare("
            SELECT COUNT(*) as overlap_count, 
                   GROUP_CONCAT(CONCAT(start_date, ' to ', end_date) SEPARATOR ', ') as overlapping_dates
            FROM leave_requests 
            WHERE employee_id = ? 
            AND status IN ('pending', 'approved_l1', 'approved_l2')
            AND (
                (start_date <= ? AND end_date >= ?) OR
                (start_date <= ? AND end_date >= ?) OR
                (start_date >= ? AND end_date <= ?)
            )
        ");
        $overlap_stmt->execute([
            $employee['id'],
            $start_date, $start_date,
            $end_date, $end_date,
            $start_date, $end_date
        ]);
        
        $overlap_result = $overlap_stmt->fetch(PDO::FETCH_ASSOC);
        if ($overlap_result['overlap_count'] > 0) {
            sendError('You already have a leave request for overlapping dates: ' . $overlap_result['overlapping_dates'], 400);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Create leave request
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests (
                    employee_id, start_date, end_date, leave_type, 
                    reason, total_days, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            
            $stmt->execute([
                $employee['id'],
                $start_date,
                $end_date,
                $leave_type,
                $reason,
                $total_days
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            // Log activity if activity_logs table exists
            try {
                $activity_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (employee_id, action, details, created_at) 
                    VALUES (?, 'leave_request_created', ?, NOW())
                ");
                $activity_stmt->execute([
                    $employee['id'], 
                    "Leave request created for $start_date to $end_date ($leave_type, $total_days days)"
                ]);
            } catch (PDOException $e) {
                // Continue even if activity logging fails
                error_log("Activity log error: " . $e->getMessage());
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Get the created request with full details
            $created_stmt = $pdo->prepare("
                SELECT lr.id, lr.start_date, lr.end_date, lr.leave_type, lr.reason, 
                       lr.total_days, lr.status, lr.created_at, lr.updated_at,
                       e.name as employee_name, e.employee_code
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                WHERE lr.id = ?
            ");
            $created_stmt->execute([$request_id]);
            $created_request = $created_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$created_request) {
                sendError('Failed to retrieve created leave request', 500);
                return;
            }
            
            sendSuccess('Leave request submitted successfully', [
                'request' => [
                    'id' => (int)$created_request['id'],
                    'start_date' => $created_request['start_date'],
                    'end_date' => $created_request['end_date'],
                    'leave_type' => $created_request['leave_type'],
                    'reason' => $created_request['reason'],
                    'total_days' => (int)$created_request['total_days'],
                    'status' => $created_request['status'],
                    'status_display' => getStatusDisplay($created_request['status']),
                    'created_at' => $created_request['created_at'],
                    'updated_at' => $created_request['updated_at']
                ]
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database transaction error: " . $e->getMessage());
            sendError('Failed to create leave request due to database error', 500);
        }
        
    } catch (PDOException $e) {
        error_log("Create leave request error: " . $e->getMessage());
        sendError('Database error occurred while creating leave request', 500);
    } catch (Exception $e) {
        error_log("Create leave request error: " . $e->getMessage());
        sendError('An error occurred while creating leave request', 500);
    }
}

function getStatusDisplay($status) {
    switch ($status) {
        case 'pending':
            return 'Pending L1 Approval';
        case 'approved_l1':
            return 'Pending L2 Approval';
        case 'approved_l2':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Unknown Status';
    }
}

// Helper function to get auth header (if not in common/response.php)
if (!function_exists('getAuthHeader')) {
    function getAuthHeader() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }
        return null;
    }
}

// Helper function to verify employee token (if not in common/response.php)
if (!function_exists('verifyEmployeeToken')) {
    function verifyEmployeeToken($pdo, $token) {
        try {
            // Decode the token
            $decoded = base64_decode($token);
            if (!$decoded) return false;
            
            $parts = explode(':', $decoded);
            if (count($parts) !== 3) return false;
            
            $employee_id = (int)$parts[0];
            $timestamp = (int)$parts[1];
            $hash = $parts[2];
            
            // Check if token is expired (24 hours)
            if (time() - $timestamp > 86400) return false;
            
            // Get employee from database
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active'");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
}
?>