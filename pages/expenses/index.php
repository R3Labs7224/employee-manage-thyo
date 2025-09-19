<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Expense Management';
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
                UPDATE expenses
                SET status = ?, approved_by = ?, approval_date = NOW(), notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $user['id'], $notes, $request_id]);
            $message = '<div class="alert alert-success">Expense request ' . $status . ' successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Error updating request status.</div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) && hasPermission('superadmin')) {
        try {
            $ids = array_map('intval', $_POST['selected_ids']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = '<div class="alert alert-success">' . count($ids) . ' expense request(s) deleted successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Error deleting expense requests.</div>';
        }
    }
}

// Handle single delete
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && hasPermission('superadmin')) {
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="alert alert-success">Expense request deleted successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deleting expense request.</div>';
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$employee_filter = $_GET['employee_id'] ?? '';
$category_filter = $_GET['category_id'] ?? '';
$task_filter = $_GET['task_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$month_filter = $_GET['month'] ?? '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = 'ex.status = ?';
    $params[] = $status_filter;
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'ex.employee_id = ?';
    $params[] = $employee_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = 'ex.category_id = ?';
    $params[] = $category_filter;
}

if (!empty($task_filter)) {
    $where_conditions[] = 'ex.task_id = ?';
    $params[] = $task_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = 'ex.request_date >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = 'ex.request_date <= ?';
    $params[] = $date_to;
}

if (!empty($month_filter)) {
    $where_conditions[] = 'DATE_FORMAT(ex.request_date, "%Y-%m") = ?';
    $params[] = $month_filter;
}

try {
    // Get expense requests with all related data
    $sql = "
        SELECT ex.*,
               e.name as employee_name,
               e.employee_code,
               u.username as approved_by_name,
               ec.name as category_name,
               ec.description as category_description,
               t.title as task_title,
               s.name as site_name
        FROM expenses ex
        JOIN employees e ON ex.employee_id = e.id
        LEFT JOIN users u ON ex.approved_by = u.id
        LEFT JOIN expense_categories ec ON ex.category_id = ec.id
        LEFT JOIN tasks t ON ex.task_id = t.id
        LEFT JOIN sites s ON t.site_id = s.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY ex.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Get employees for filter dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();

    // Get categories for filter dropdown
    $categories_stmt = $pdo->query("SELECT id, name FROM expense_categories WHERE is_active = 1 ORDER BY name");
    $categories = $categories_stmt->fetchAll();

    // Get tasks for filter dropdown
    $tasks_stmt = $pdo->query("SELECT id, title FROM tasks WHERE status IN ('active', 'completed') ORDER BY title LIMIT 100");
    $tasks = $tasks_stmt->fetchAll();

    // Get summary stats with current filters
    $summary_sql = "
        SELECT
            COUNT(*) as total_requests,
            SUM(ex.amount) as total_amount,
            SUM(CASE WHEN ex.status = 'pending' THEN ex.amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN ex.status = 'approved' THEN ex.amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN ex.status = 'rejected' THEN ex.amount ELSE 0 END) as rejected_amount,
            COUNT(CASE WHEN ex.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN ex.status = 'approved' THEN 1 END) as approved_count,
            COUNT(CASE WHEN ex.status = 'rejected' THEN 1 END) as rejected_count
        FROM expenses ex
        JOIN employees e ON ex.employee_id = e.id
        LEFT JOIN expense_categories ec ON ex.category_id = ec.id
        LEFT JOIN tasks t ON ex.task_id = t.id
        WHERE " . implode(' AND ', $where_conditions);

    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute($params);
    $stats = $summary_stmt->fetch();

    // Get category breakdown with current filters
    $category_sql = "
        SELECT
            ec.name as category_name,
            COUNT(*) as count,
            SUM(ex.amount) as total_amount
        FROM expenses ex
        JOIN employees e ON ex.employee_id = e.id
        LEFT JOIN expense_categories ec ON ex.category_id = ec.id
        LEFT JOIN tasks t ON ex.task_id = t.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY ex.category_id, ec.name
        ORDER BY total_amount DESC
    ";

    $category_stmt = $pdo->prepare($category_sql);
    $category_stmt->execute($params);
    $category_breakdown = $category_stmt->fetchAll();

} catch (PDOException $e) {
    $requests = [];
    $employees = [];
    $categories = [];
    $tasks = [];
    $stats = ['total_requests' => 0, 'pending_count' => 0, 'approved_amount' => 0, 'pending_amount' => 0];
    $category_breakdown = [];
    $message = '<div class="alert alert-error">Error fetching expense requests.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/logo.png">
    <link rel="shortcut icon" href="../../assets/images/logo.png">
    <link rel="apple-touch-icon" href="../../assets/images/logo.png">
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

                <!-- Enhanced Summary Stats -->
                <div class="stats-grid" style="margin-bottom: 2rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
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
                            <h3><?php echo $stats['pending_count']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($stats['total_amount'] ?? 0); ?></h3>
                            <p>Total Amount</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-calculator"></i>
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

                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo formatCurrency($stats['rejected_amount'] ?? 0); ?></h3>
                            <p>Rejected Amount</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Filters -->
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> Advanced Filters</h3>
                    </div>
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" action="">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="employee_id">Employee</label>
                                    <select id="employee_id" name="employee_id" class="form-control">
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
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" class="form-control">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                                <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="task_id">Task</label>
                                    <select id="task_id" name="task_id" class="form-control">
                                        <option value="">All Tasks</option>
                                        <?php foreach ($tasks as $task): ?>
                                        <option value="<?php echo $task['id']; ?>"
                                                <?php echo $task_filter == $task['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($task['title']); ?>
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
                                    <label for="month">Month</label>
                                    <input type="month" id="month" name="month" class="form-control"
                                           value="<?php echo htmlspecialchars($month_filter); ?>">
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
                                        <i class="fas fa-search"></i> Apply Filters
                                    </button>
                                    <a href="index.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Category Breakdown -->
                <?php if (!empty($category_breakdown)): ?>
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Expense Breakdown by Category</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <?php foreach ($category_breakdown as $breakdown): ?>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center;">
                                <h4 style="margin: 0; color: #495057;"><?php echo htmlspecialchars($breakdown['category_name']); ?></h4>
                                <p style="margin: 0.5rem 0; font-size: 1.2rem; font-weight: bold; color: #28a745;">
                                    <?php echo formatCurrency($breakdown['total_amount']); ?>
                                </p>
                                <p style="margin: 0; color: #6c757d; font-size: 0.9rem;">
                                    <?php echo $breakdown['count']; ?> request<?php echo $breakdown['count'] != 1 ? 's' : ''; ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Expense Requests -->
                <div class="card">
                    <div class="card-header">
                        <h3>Expense Requests (<?php echo count($requests); ?> results)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="table-empty-state">
                                <i class="fas fa-receipt"></i>
                                <h3>No Expense Requests Found</h3>
                                <p>No requests match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="bulkForm">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <?php if (hasPermission('superadmin')): ?>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                                </th>
                                                <?php endif; ?>
                                                <th>Employee</th>
                                                <th>Amount</th>
                                                <th>Category</th>
                                                <th>Task</th>
                                                <th>Reason</th>
                                                <th>Receipt</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Approved By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <?php if (hasPermission('superadmin')): ?>
                                                <td>
                                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $request['id']; ?>" class="row-checkbox">
                                                </td>
                                                <?php endif; ?>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                                        <small style="color: #666; display: block;"><?php echo htmlspecialchars($request['employee_code']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="amount">₹<?php echo number_format($request['amount'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: #6c757d; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;">
                                                        <?php echo htmlspecialchars($request['category_name'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($request['task_title']): ?>
                                                        <span title="<?php echo htmlspecialchars($request['task_title']); ?>">
                                                            <?php echo strlen($request['task_title']) > 30 ? htmlspecialchars(substr($request['task_title'], 0, 27)) . '...' : htmlspecialchars($request['task_title']); ?>
                                                        </span>
                                                        <?php if ($request['site_name']): ?>
                                                            <small style="color: #666; display: block;"><?php echo htmlspecialchars($request['site_name']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: #999;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span title="<?php echo htmlspecialchars($request['reason']); ?>">
                                                        <?php echo strlen($request['reason']) > 40 ? htmlspecialchars(substr($request['reason'], 0, 37)) . '...' : htmlspecialchars($request['reason']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($request['receipt_image']): ?>
                                                        <i class="fas fa-paperclip" style="color: #28a745;" title="Receipt attached"></i>
                                                    <?php else: ?>
                                                        <span style="color: #999;">-</span>
                                                    <?php endif; ?>
                                                    <?php if ($request['receipt_number']): ?>
                                                        <small style="display: block; color: #666;">#<?php echo htmlspecialchars($request['receipt_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($request['request_date']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $request['approved_by_name'] ? htmlspecialchars($request['approved_by_name']) : '-'; ?>
                                                    <?php if ($request['approval_date']): ?>
                                                        <small style="color: #666; display: block;"><?php echo formatDate($request['approval_date']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 0.25rem;">
                                                        <?php if ($request['status'] == 'pending' && hasPermission('supervisor')): ?>
                                                        <button type="button" class="btn" style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                onclick="showApprovalModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['employee_name']); ?>', <?php echo $request['amount']; ?>, '<?php echo htmlspecialchars($request['reason']); ?>', '<?php echo $request['request_date']; ?>', '<?php echo htmlspecialchars($request['category_name']); ?>', '<?php echo htmlspecialchars($request['task_title'] ?? ''); ?>')">
                                                            <i class="fas fa-check"></i> Review
                                                        </button>
                                                        <?php endif; ?>

                                                        <?php if (hasPermission('superadmin')): ?>
                                                        <a href="?delete=<?php echo $request['id']; ?>"
                                                        class="btn" style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                        onclick="return confirm('Are you sure you want to delete this expense request?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Bulk Actions Bar -->
                                <div id="bulkActions" class="bulk-actions" style="display: none;">
                                    <span id="selectedCount">0 items selected</span>
                                    <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                                        <i class="fas fa-trash"></i> Delete Selected
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                                        <i class="fas fa-times"></i> Clear Selection
                                    </button>
                                </div>

                                <input type="hidden" name="bulk_delete" value="1">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Approval Modal -->
<div id="approvalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);">
        <h3 style="margin-bottom: 1rem; color: #333;">
            <i class="fas fa-clipboard-check"></i> Review Expense Request
        </h3>

        <div id="modalContent">
            <input type="hidden" id="modal_request_id_hidden" name="request_id">

            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <strong>Employee:</strong><br>
                        <span id="modal_employee_name" style="color: #666;"></span>
                    </div>
                    <div>
                        <strong>Amount:</strong><br>
                        <span id="modal_amount" style="color: #28a745; font-weight: bold; font-size: 1.1em;"></span>
                    </div>
                </div>
                <div style="margin-top: 0.5rem;">
                    <strong>Category:</strong><br>
                    <span id="modal_category" style="color: #666;"></span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <strong>Task:</strong><br>
                    <span id="modal_task" style="color: #666;"></span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <strong>Reason:</strong><br>
                    <span id="modal_reason" style="color: #666;"></span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <strong>Request Date:</strong><br>
                    <span id="modal_date" style="color: #666;"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="modal_notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                <textarea id="modal_notes" class="form-control" rows="3"
                          placeholder="Add any notes about this request..."></textarea>
            </div>

            <div style="text-align: right; gap: 1rem; display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem;" onclick="closeApprovalModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" id="rejectBtn" class="btn" style="background: #dc3545; color: white; padding: 0.5rem 1rem;" onclick="submitApprovalAction('reject')">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
                <button type="button" id="approveBtn" class="btn" style="background: #28a745; color: white; padding: 0.5rem 1rem;" onclick="submitApprovalAction('approve')">
                    <i class="fas fa-check-circle"></i> Approve
                </button>
            </div>
        </div>

        <!-- Hidden form for submission -->
        <form id="hiddenApprovalForm" method="POST" action="" style="display: none;">
            <input type="hidden" id="form_request_id" name="request_id">
            <input type="hidden" id="form_action" name="action">
            <input type="hidden" id="form_notes" name="notes">
        </form>
    </div>
</div>

<script>
    console.log('Loading enhanced expense management functionality...');

    // Ensure formatCurrency function is available
    function formatCurrency(amount) {
        if (typeof window.EMS !== 'undefined' && window.EMS.formatCurrency) {
            return window.EMS.formatCurrency(amount);
        }
        return '₹' + parseFloat(amount).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Global variable to store current request ID
    let currentRequestId = null;

    function showApprovalModal(requestId, employeeName, amount, reason, requestDate, category, task) {
        console.log('=== OPENING ENHANCED MODAL ===');
        console.log('Request ID:', requestId);
        console.log('Employee:', employeeName);
        console.log('Amount:', amount);
        console.log('Category:', category);
        console.log('Task:', task);

        // Store the request ID globally
        currentRequestId = requestId;

        // Populate modal fields
        document.getElementById('modal_request_id_hidden').value = requestId;
        document.getElementById('modal_employee_name').textContent = employeeName || 'Unknown Employee';
        document.getElementById('modal_amount').textContent = formatCurrency(amount || 0);
        document.getElementById('modal_category').textContent = category || 'N/A';
        document.getElementById('modal_task').textContent = task || 'No task linked';
        document.getElementById('modal_reason').textContent = reason || '';
        document.getElementById('modal_date').textContent = requestDate || '';

        // Clear notes field
        document.getElementById('modal_notes').value = '';

        // Show modal
        document.getElementById('approvalModal').style.display = 'block';

        console.log('✅ Enhanced modal opened successfully');

        // Focus on notes field
        setTimeout(() => {
            document.getElementById('modal_notes').focus();
        }, 300);
    }

    function closeApprovalModal() {
        document.getElementById('approvalModal').style.display = 'none';
        currentRequestId = null;
        console.log('Modal closed');
    }

    function submitApprovalAction(action) {
        console.log('=== SUBMITTING ACTION ===');
        console.log('Action:', action);
        console.log('Request ID:', currentRequestId);

        if (!currentRequestId) {
            console.error('❌ No request ID available');
            alert('Error: No request ID found. Please try again.');
            return false;
        }

        if (!action || !['approve', 'reject'].includes(action)) {
            console.error('❌ Invalid action:', action);
            alert('Error: Invalid action specified.');
            return false;
        }

        // Get notes value
        const notes = document.getElementById('modal_notes').value.trim();

        console.log('Form data:', {
            request_id: currentRequestId,
            action: action,
            notes: notes
        });

        // Populate hidden form
        document.getElementById('form_request_id').value = currentRequestId;
        document.getElementById('form_action').value = action;
        document.getElementById('form_notes').value = notes;

        // Disable buttons and show loading state
        const approveBtn = document.getElementById('approveBtn');
        const rejectBtn = document.getElementById('rejectBtn');

        approveBtn.disabled = true;
        rejectBtn.disabled = true;

        if (action === 'approve') {
            approveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';
        } else {
            rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';
        }

        console.log('✅ Submitting form...');

        // Submit the hidden form
        document.getElementById('hiddenApprovalForm').submit();

        return true;
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.row-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });

        updateBulkActions();
    }

    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        if (checkboxes.length > 0) {
            bulkActions.style.display = 'flex';
            selectedCount.textContent = checkboxes.length + ' item' + (checkboxes.length !== 1 ? 's' : '') + ' selected';
        } else {
            bulkActions.style.display = 'none';
        }
    }

    function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one item to delete.');
            return;
        }

        if (confirm(`Are you sure you want to delete ${checkboxes.length} expense request(s)? This action cannot be undone.`)) {
            document.getElementById('bulkForm').submit();
        }
    }

    function clearSelection() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        const selectAll = document.getElementById('selectAll');

        checkboxes.forEach(checkbox => checkbox.checked = false);
        selectAll.checked = false;
        updateBulkActions();
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== INITIALIZING ENHANCED EXPENSE MANAGEMENT ===');

        const modal = document.getElementById('approvalModal');
        if (!modal) {
            console.error('❌ Modal not found!');
            return;
        }

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeApprovalModal();
            }
        });

        // ESC key support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'block') {
                closeApprovalModal();
            }
        });

        // Add event listeners to checkboxes
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert-success, .alert-error');
        alerts.forEach((alert, index) => {
            setTimeout(() => {
                if (alert && alert.parentNode) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }
            }, 5000 + (index * 500));
        });

        console.log('✅ Enhanced expense management initialized successfully');
    });

    console.log('✅ Enhanced Expense Management Script Loaded');
</script>

<?php include '../../components/footer.php'; ?>