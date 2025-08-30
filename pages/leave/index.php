<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Leave Requests Management';
$message = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $comments = sanitize($_POST['comments'] ?? '');
    $user = getUser();
    
    try {
        if ($action === 'approve_l1' && hasPermission('supervisor')) {
            // L1 Approval (Site Manager)
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved_l1', l1_approved_by = ?, l1_approval_date = NOW(), l1_comments = ?
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$user['id'], $comments, $request_id]);
            $message = '<div class="alert alert-success">Request approved at L1 level successfully!</div>';
            
        } elseif ($action === 'approve_l2' && (hasPermission('superadmin') || $user['role'] === 'hr')) {
            // L2 Approval (HR Manager)
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved_l2', l2_approved_by = ?, l2_approval_date = NOW(), l2_comments = ?
                WHERE id = ? AND status = 'approved_l1'
            ");
            $stmt->execute([$user['id'], $comments, $request_id]);
            $message = '<div class="alert alert-success">Request approved at L2 level successfully!</div>';
            
        } elseif ($action === 'reject' && (hasPermission('supervisor') || hasPermission('superadmin') || $user['role'] === 'hr')) {
            // Rejection
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', rejected_by = ?, rejection_date = NOW(), rejection_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], $comments, $request_id]);
            $message = '<div class="alert alert-error">Request rejected successfully!</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error updating request status.</div>';
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = 'lr.status = ?';
    $params[] = $status_filter;
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'e.id = ?';
    $params[] = $employee_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = 'lr.start_date >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = 'lr.start_date <= ?';
    $params[] = $date_to;
}

try {
    // Get leave requests with employee details
    $sql = "
        SELECT lr.*, 
               e.name as employee_name,
               e.employee_code,
               l1.username as l1_approver_name,
               l2.username as l2_approver_name,
               r.username as rejected_by_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN users l1 ON lr.l1_approved_by = l1.id
        LEFT JOIN users l2 ON lr.l2_approved_by = l2.id
        LEFT JOIN users r ON lr.rejected_by = r.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY lr.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll();

    // Get employees for filter dropdown
    $employees_stmt = $pdo->query("
        SELECT id, name, employee_code 
        FROM employees 
        WHERE status = 'active' 
        ORDER BY name
    ");
    $employees = $employees_stmt->fetchAll();

} catch (PDOException $e) {
    $message = '<div class="alert alert-error">Error fetching leave requests.</div>';
    $leave_requests = [];
    $employees = [];
}

// Get current user permissions
$user = getUser();
$can_approve_l1 = hasPermission('supervisor');
$can_approve_l2 = hasPermission('superadmin') || $user['role'] === 'hr';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - EMS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filters-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-approved_l1 {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-approved_l2 {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .leave-type-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .leave-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .leave-unpaid {
            background-color: #fef3c7;
            color: #92400e;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <?php echo $message; ?>
                
                <!-- Filters Section -->
                <div class="filters-section">
                    <form method="GET" class="filters-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved_l1" <?php echo $status_filter === 'approved_l1' ? 'selected' : ''; ?>>L1 Approved</option>
                                <option value="approved_l2" <?php echo $status_filter === 'approved_l2' ? 'selected' : ''; ?>>L2 Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="employee">Employee</label>
                            <select name="employee" id="employee" class="form-control">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                            <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Leave Requests (<?php echo count($leave_requests); ?>)</h3>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($leave_requests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No leave requests found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Dates</th>
                                            <th>Type</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leave_requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($request['employee_code']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo date('M d, Y', strtotime($request['start_date'])); ?></strong><br>
                                                    <small class="text-muted">to <?php echo date('M d, Y', strtotime($request['end_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="leave-type-badge leave-<?php echo $request['leave_type']; ?>">
                                                        <?php echo ucfirst($request['leave_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $request['total_days']; ?> days</td>
                                                <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                    <?php echo htmlspecialchars(substr($request['reason'], 0, 50) . (strlen($request['reason']) > 50 ? '...' : '')); ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                                        <?php 
                                                        switch($request['status']) {
                                                            case 'pending': echo 'Pending'; break;
                                                            case 'approved_l1': echo 'L1 Approved'; break;
                                                            case 'approved_l2': echo 'L2 Approved'; break;
                                                            case 'rejected': echo 'Rejected'; break;
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-info btn-sm" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($request['status'] === 'pending' && $can_approve_l1): ?>
                                                            <button class="btn btn-success btn-sm" onclick="approveL1(<?php echo $request['id']; ?>)">
                                                                <i class="fas fa-check"></i> L1
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($request['status'] === 'approved_l1' && $can_approve_l2): ?>
                                                            <button class="btn btn-success btn-sm" onclick="approveL2(<?php echo $request['id']; ?>)">
                                                                <i class="fas fa-check-double"></i> L2
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($request['status'], ['pending', 'approved_l1']) && ($can_approve_l1 || $can_approve_l2)): ?>
                                                            <button class="btn btn-danger btn-sm" onclick="rejectRequest(<?php echo $request['id']; ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <h4 id="modalTitle">Action</h4>
            <form id="actionForm" method="POST">
                <input type="hidden" id="requestId" name="request_id">
                <input type="hidden" id="actionType" name="action">
                
                <div class="form-group">
                    <label for="comments">Comments</label>
                    <textarea name="comments" id="comments" rows="3" class="form-control" placeholder="Optional comments..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Confirm</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h4>Leave Request Details</h4>
            <div id="detailsContent"></div>
            <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
        </div>
    </div>

    <script>
        function approveL1(requestId) {
            openModal('L1 Approval - Site Manager', requestId, 'approve_l1');
        }

        function approveL2(requestId) {
            openModal('L2 Approval - HR Manager', requestId, 'approve_l2');
        }

        function rejectRequest(requestId) {
            openModal('Reject Leave Request', requestId, 'reject');
        }

        function openModal(title, requestId, action) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('requestId').value = requestId;
            document.getElementById('actionType').value = action;
            document.getElementById('comments').value = '';
            document.getElementById('actionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
        }

        function viewDetails(requestId) {
            // Find the request data (you'd normally fetch this via AJAX)
            const row = event.target.closest('tr');
            const cells = row.querySelectorAll('td');
            
            const details = `
                <div style="line-height: 1.6;">
                    <strong>Employee:</strong> ${cells[0].querySelector('strong').textContent}<br>
                    <strong>Employee Code:</strong> ${cells[0].querySelector('small').textContent}<br>
                    <strong>Leave Dates:</strong> ${cells[1].querySelector('strong').textContent} ${cells[1].querySelector('small').textContent}<br>
                    <strong>Type:</strong> ${cells[2].textContent.trim()}<br>
                    <strong>Total Days:</strong> ${cells[3].textContent}<br>
                    <strong>Reason:</strong> ${cells[4].getAttribute('title')}<br>
                    <strong>Status:</strong> ${cells[5].textContent.trim()}<br>
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = details;
            document.getElementById('detailsModal').style.display = 'block';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const actionModal = document.getElementById('actionModal');
            const detailsModal = document.getElementById('detailsModal');
            
            if (event.target === actionModal) {
                actionModal.style.display = 'none';
            }
            if (event.target === detailsModal) {
                detailsModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>