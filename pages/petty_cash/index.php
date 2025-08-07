<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Petty Cash Management';
$message = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $notes = sanitize($_POST['notes'] ?? '');
    $user = getUser();
    
    if (in_array($action, ['approve', 'reject']) && hasPermission('supervisor')) {
        try {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("
                UPDATE petty_cash_requests 
                SET status = ?, approved_by = ?, approval_date = NOW(), notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $user['id'], $notes, $request_id]);
            $message = '<div class="alert alert-success">Request ' . $status . ' successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Error updating request status.</div>';
        }
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
    $where_conditions[] = 'p.status = ?';
    $params[] = $status_filter;
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'e.id = ?';
    $params[] = $employee_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = 'p.request_date >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = 'p.request_date <= ?';
    $params[] = $date_to;
}

try {
    // Get petty cash requests
    $sql = "
        SELECT p.*, 
               e.name as employee_name,
               e.employee_code,
               u.username as approved_by_name
        FROM petty_cash_requests p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN users u ON p.approved_by = u.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Get employees for filter dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();
    
    // Get summary stats
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
        FROM petty_cash_requests
    ");
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $requests = [];
    $employees = [];
    $stats = ['total_requests' => 0, 'pending_requests' => 0, 'approved_amount' => 0, 'pending_amount' => 0];
    $message = '<div class="alert alert-error">Error fetching petty cash requests.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <?php echo $message; ?>
                
                <!-- Summary Stats -->
                <div class="stats-grid" style="margin-bottom: 2rem;">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['total_requests']; ?></h3>
                            <p>Total Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-list"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($stats['approved_amount']); ?></h3>
                            <p>Approved Amount</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($stats['pending_amount']); ?></h3>
                            <p>Pending Amount</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" action="">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="employee">Employee</label>
                                    <select id="employee" name="employee" class="form-control">
                                        <option value="">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" 
                                                <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="date_from">Date From</label>
                                    <input type="date" id="date_from" name="date_from" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="date_to">Date To</label>
                                    <input type="date" id="date_to" name="date_to" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="index.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Petty Cash Requests -->
                <div class="card">
                    <div class="card-header">
                        <h3>Petty Cash Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-money-bill fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Petty Cash Requests Found</h3>
                                <p>No requests match the selected filters.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Amount</th>
                                            <th>Reason</th>
                                            <th>Request Date</th>
                                            <th>Receipt</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($request['employee_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($request['employee_name']); ?></small>
                                            </td>
                                            <td><strong><?php echo formatCurrency($request['amount']); ?></strong></td>
                                            <td>
                                                <div style="max-width: 200px; word-wrap: break-word;">
                                                    <?php echo htmlspecialchars($request['reason']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo formatDate($request['request_date'], 'M d, Y'); ?></td>
                                            <td>
                                                <?php if ($request['receipt_image']): ?>
                                                    <a href="../../assets/images/uploads/petty_cash/<?php echo $request['receipt_image']; ?>" 
                                                       target="_blank" class="btn" style="background: #17a2b8; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-file-image"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #666;">No receipt</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $request['status'] === 'approved' ? 'success' : 
                                                        ($request['status'] === 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                                <?php if ($request['approved_by_name']): ?>
                                                    <br><small style="color: #666;">by <?php echo htmlspecialchars($request['approved_by_name']); ?></small>
                                                    <?php if ($request['approval_date']): ?>
                                                        <br><small style="color: #666;"><?php echo formatDate($request['approval_date'], 'M d, Y'); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="max-width: 150px; word-wrap: break-word;">
                                                    <?php echo htmlspecialchars($request['notes'] ?: '-'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'pending' && hasPermission('supervisor')): ?>
                                                    <button type="button" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="showApprovalModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['employee_name']); ?>', <?php echo $request['amount']; ?>)">
                                                        <i class="fas fa-edit"></i> Review
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color: #666; font-size: 0.8rem;">-</span>
                                                <?php endif; ?>
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

    <!-- Approval Modal -->
    <div id="approvalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
            <h3 style="margin-bottom: 1rem;">Review Petty Cash Request</h3>
            <form method="POST" action="">
                <input type="hidden" id="modal_request_id" name="request_id">
                
                <div style="margin-bottom: 1rem;">
                    <strong>Employee:</strong> <span id="modal_employee_name"></span><br>
                    <strong>Amount:</strong> <span id="modal_amount"></span>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Add any notes about this request..."></textarea>
                </div>
                
                <div style="text-align: right; gap: 1rem; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeApprovalModal()">Cancel</button>
                    <button type="submit" name="action" value="reject" class="btn" style="background: #dc3545; color: white;">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <button type="submit" name="action" value="approve" class="btn" style="background: #28a745; color: white;">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showApprovalModal(requestId, employeeName, amount) {
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modal_employee_name').textContent = employeeName;
            document.getElementById('modal_amount').textContent = formatCurrency(amount);
            document.getElementById('approvalModal').style.display = 'block';
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });
    </script>

<?php include '../../components/footer.php'; ?>