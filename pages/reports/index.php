<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_helpers.php';

requireLogin();

// Check if user has admin permission to view reports
if (!hasAdminPermission('reports.view')) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

$pageTitle = 'Reports & Analytics';
$message = '';

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'attendance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$employee_id = $_GET['employee_id'] ?? '';

// Legacy support for month parameter
if (isset($_GET['month']) && !isset($_GET['start_date'])) {
    $month = $_GET['month'];
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
}

try {
    // Get employees for dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();
    
    $report_data = [];
    
    switch ($report_type) {
        case 'attendance':
            $report_data = getAttendanceReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'petty_cash':
            $report_data = getPettyCashReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'salary':
            $report_data = getSalaryReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'tasks':
            $report_data = getTasksReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'expenses':
            $report_data = getExpensesReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'leave':
            $report_data = getLeaveReport($pdo, $start_date, $end_date, $employee_id);
            break;
    }
    
} catch (PDOException $e) {
    $employees = [];
    $report_data = [];
    error_log("Reports error: " . $e->getMessage());
    $message = '<div class="alert alert-error">Error generating report: ' . $e->getMessage() . '</div>';
} catch (Exception $e) {
    $employees = [];
    $report_data = [];
    error_log("Reports general error: " . $e->getMessage());
    $message = '<div class="alert alert-error">Error: ' . $e->getMessage() . '</div>';
}

function getAttendanceReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["a.date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if (!empty($employee_id)) {
        $where_conditions[] = 'a.employee_id = ?';
        $params[] = $employee_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_days,
            SUM(CASE WHEN a.check_in_latitude IS NOT NULL THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN a.check_in_latitude IS NULL THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_days,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_days,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_days,
            8.0 as avg_hours,
            SUM(CASE WHEN a.check_in_latitude IS NOT NULL THEN 8.0 ELSE 0 END) as total_hours
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY a.employee_id
        ORDER BY e.name
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPettyCashReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["ex.request_date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];

    if (!empty($employee_id)) {
        $where_conditions[] = 'ex.employee_id = ?';
        $params[] = $employee_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_requests,
            SUM(CASE WHEN ex.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN ex.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN ex.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(ex.amount) as total_amount,
            SUM(CASE WHEN ex.status = 'approved' THEN ex.amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN ex.status = 'pending' THEN ex.amount ELSE 0 END) as pending_amount
        FROM expenses ex
        JOIN employees e ON ex.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY ex.employee_id
        ORDER BY e.name
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getSalaryReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["DATE(CONCAT(s.year, '-', LPAD(s.month, 2, '0'), '-01')) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if (!empty($employee_id)) {
        $where_conditions[] = 's.employee_id = ?';
        $params[] = $employee_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_code,
            e.name as employee_name,
            s.basic_salary,
            s.total_working_days,
            s.present_days,
            s.calculated_salary,
            s.bonus,
            s.advance,
            s.deductions,
            s.net_salary,
            s.status
        FROM salaries s
        JOIN employees e ON s.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY e.name
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTasksReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];

    if (!empty($employee_id)) {
        $where_conditions[] = 't.assigned_to_employee_id = ?';
        $params[] = $employee_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tasks,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
            SUM(CASE WHEN t.expense_required = 1 THEN COALESCE(t.budget_amount, 0) ELSE 0 END) as total_budget,
            COUNT(DISTINCT t.site_id) as sites_worked,
            GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as sites_list,
            GROUP_CONCAT(DISTINCT t.task_identifier SEPARATOR ', ') as task_identifiers
        FROM tasks t
        JOIN employees e ON t.assigned_to_employee_id = e.id
        LEFT JOIN sites s ON t.site_id = s.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY t.assigned_to_employee_id, e.employee_code, e.name
        ORDER BY e.name
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getExpensesReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["ex.request_date BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];

    if (!empty($employee_id)) {
        $where_conditions[] = 'ex.employee_id = ?';
        $params[] = $employee_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_expenses,
            SUM(CASE WHEN ex.status = 'approved' THEN 1 ELSE 0 END) as approved_expenses,
            SUM(CASE WHEN ex.status = 'pending' THEN 1 ELSE 0 END) as pending_expenses,
            SUM(CASE WHEN ex.status = 'rejected' THEN 1 ELSE 0 END) as rejected_expenses,
            SUM(ex.amount) as total_amount,
            SUM(CASE WHEN ex.status = 'approved' THEN ex.amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN ex.status = 'pending' THEN ex.amount ELSE 0 END) as pending_amount,
            GROUP_CONCAT(DISTINCT ec.name SEPARATOR ', ') as categories
        FROM expenses ex
        JOIN employees e ON ex.employee_id = e.id
        LEFT JOIN expense_categories ec ON ex.category_id = ec.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY ex.employee_id
        ORDER BY e.name
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getLeaveReport($pdo, $start_date, $end_date, $employee_id) {
    $where_conditions = ["(lr.start_date BETWEEN ? AND ? OR lr.end_date BETWEEN ? AND ?)"];
    $params = [$start_date, $end_date, $start_date, $end_date];

    if (!empty($employee_id)) {
        $where_conditions[] = 'lr.employee_id = ?';
        $params[] = $employee_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_requests,
            SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(CASE WHEN lr.status = 'approved' THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END) as total_leave_days,
            GROUP_CONCAT(DISTINCT lr.leave_type SEPARATOR ', ') as leave_types
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY lr.employee_id
        ORDER BY e.name
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
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
                
                <!-- Report Filters -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3>Generate Reports</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="report_type">Report Type</label>
                                    <select id="report_type" name="report_type" class="form-control">
                                        <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                                        <option value="tasks" <?php echo $report_type === 'tasks' ? 'selected' : ''; ?>>Tasks Report</option>
                                        <option value="leave" <?php echo $report_type === 'leave' ? 'selected' : ''; ?>>Leave Report</option>
                                        <option value="salary" <?php echo $report_type === 'salary' ? 'selected' : ''; ?>>Salary Report</option>
                                        <option value="petty_cash" <?php echo $report_type === 'petty_cash' ? 'selected' : ''; ?>>Expense Report</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control"
                                           value="<?php echo htmlspecialchars($start_date); ?>" required>
                                </div>

                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control"
                                           value="<?php echo htmlspecialchars($end_date); ?>" required>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="employee_id">Employee (Optional)</label>
                                    <select id="employee_id" name="employee_id" class="form-control">
                                        <option value="">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" 
                                                <?php echo $employee_id == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-chart-bar"></i> Generate Report
                                    </button>
                                    <button type="button" class="btn" style="background: #28a745; color: white; margin-left: 0.5rem;" onclick="exportReport()">
                                        <i class="fas fa-download"></i> Export CSV
                                    </button>
                                </div>
                            </div>

                            <!-- Quick Date Range Buttons -->
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                <label style="font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; display: block;">Quick Date Ranges:</label>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                    <button type="button" class="btn" style="background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="setDateRange(7)">
                                        Last 7 days
                                    </button>
                                    <button type="button" class="btn" style="background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="setDateRange(30)">
                                        Last 30 days
                                    </button>
                                    <button type="button" class="btn" style="background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="setCurrentMonth()">
                                        This Month
                                    </button>
                                    <button type="button" class="btn" style="background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="setLastMonth()">
                                        Last Month
                                    </button>
                                    <button type="button" class="btn" style="background: #f3f4f6; color: #374151; padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="setDateRange(90)">
                                        Last 3 months
                                    </button>
                                </div>
                        </form>
                    </div>
                </div>
                
                <!-- Report Results -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo ucfirst(str_replace('_', ' ', $report_type)); ?> Report - <?php echo date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($report_data)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-chart-line fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Data Found</h3>
                                <p>No data available for the selected criteria.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <?php if ($report_type === 'attendance'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Total Days</th>
                                                <th>Present Days</th>
                                                <th>Absent Days</th>
                                                <th>Approved</th>
                                                <th>Pending</th>
                                                <th>Rejected</th>
                                                <th>Total Hours</th>
                                            <?php elseif ($report_type === 'petty_cash'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Total Requests</th>
                                                <th>Approved</th>
                                                <th>Pending</th>
                                                <th>Rejected</th>
                                                <th>Total Amount</th>
                                                <th>Approved Amount</th>
                                                <th>Pending Amount</th>
                                            <?php elseif ($report_type === 'salary'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Basic Salary</th>
                                                <th>Working Days</th>
                                                <th>Present Days</th>
                                                <th>Calculated Salary</th>
                                                <th>Bonus</th>
                                                <th>Advance</th>
                                                <th>Deductions</th>
                                                <th>Net Salary</th>
                                                <th>Status</th>
                                            <?php elseif ($report_type === 'tasks'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Total Tasks</th>
                                                <th>Completed</th>
                                                <th>Active</th>
                                                <th>In Progress</th>
                                                <th>Cancelled</th>
                                                <th>Total Budget</th>
                                                <th>Sites Worked</th>
                                                <th>Sites List</th>
                                                <th>Task IDs</th>
                                            <?php elseif ($report_type === 'expenses'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Total Expenses</th>
                                                <th>Approved</th>
                                                <th>Pending</th>
                                                <th>Rejected</th>
                                                <th>Total Amount</th>
                                                <th>Approved Amount</th>
                                                <th>Pending Amount</th>
                                                <th>Categories</th>
                                            <?php elseif ($report_type === 'leave'): ?>
                                                <th>Employee Code</th>
                                                <th>Employee Name</th>
                                                <th>Total Requests</th>
                                                <th>Approved</th>
                                                <th>Pending</th>
                                                <th>Rejected</th>
                                                <th>Leave Days Taken</th>
                                                <th>Leave Types</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type === 'attendance'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo $row['total_days']; ?></td>
                                                <td><span class="badge badge-success"><?php echo $row['present_days']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['absent_days']; ?></span></td>
                                                <td><span class="badge badge-success"><?php echo $row['approved_days']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['pending_days']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['rejected_days']; ?></span></td>
                                                <td><?php echo number_format($row['total_hours'] ?: 0, 1); ?>h</td>
                                            <?php elseif ($report_type === 'petty_cash'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo $row['total_requests']; ?></td>
                                                <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['pending_requests']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['rejected_requests']; ?></span></td>
                                                <td><?php echo formatCurrency($row['total_amount']); ?></td>
                                                <td><?php echo formatCurrency($row['approved_amount']); ?></td>
                                                <td><?php echo formatCurrency($row['pending_amount']); ?></td>
                                            <?php elseif ($report_type === 'salary'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo formatCurrency($row['basic_salary']); ?></td>
                                                <td><?php echo $row['total_working_days']; ?></td>
                                                <td><?php echo $row['present_days']; ?></td>
                                                <td><?php echo formatCurrency($row['calculated_salary']); ?></td>
                                                <td><?php echo formatCurrency($row['bonus']); ?></td>
                                                <td><?php echo formatCurrency($row['advance']); ?></td>
                                                <td><?php echo formatCurrency($row['deductions']); ?></td>
                                                <td><strong><?php echo formatCurrency($row['net_salary']); ?></strong></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $row['status'] === 'paid' ? 'success' : 
                                                            ($row['status'] === 'processed' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                            <?php elseif ($report_type === 'tasks'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo $row['total_tasks']; ?></td>
                                                <td><span class="badge badge-success"><?php echo $row['completed_tasks']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['active_tasks']; ?></span></td>
                                                <td><span class="badge badge-info"><?php echo $row['in_progress_tasks']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['cancelled_tasks']; ?></span></td>
                                                <td><?php echo formatCurrency($row['total_budget'] ?: 0); ?></td>
                                                <td><?php echo $row['sites_worked']; ?></td>
                                                <td><small><?php echo htmlspecialchars($row['sites_list'] ?: 'N/A'); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($row['task_identifiers'] ?: 'N/A'); ?></small></td>
                                            <?php elseif ($report_type === 'expenses'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo $row['total_expenses']; ?></td>
                                                <td><span class="badge badge-success"><?php echo $row['approved_expenses']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['pending_expenses']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['rejected_expenses']; ?></span></td>
                                                <td><?php echo formatCurrency($row['total_amount']); ?></td>
                                                <td><?php echo formatCurrency($row['approved_amount']); ?></td>
                                                <td><?php echo formatCurrency($row['pending_amount']); ?></td>
                                                <td><small><?php echo htmlspecialchars($row['categories'] ?: 'N/A'); ?></small></td>
                                            <?php elseif ($report_type === 'leave'): ?>
                                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                                <td><?php echo $row['total_requests']; ?></td>
                                                <td><span class="badge badge-success"><?php echo $row['approved_requests']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['pending_requests']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['rejected_requests']; ?></span></td>
                                                <td><?php echo $row['total_leave_days']; ?> days</td>
                                                <td><small><?php echo htmlspecialchars($row['leave_types'] ?: 'N/A'); ?></small></td>
                                            <?php endif; ?>
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

    <script>
        function exportReport() {
            const reportType = '<?php echo $report_type; ?>';
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            const employeeId = '<?php echo $employee_id; ?>';

            if (!document.getElementById('reportTable')) {
                alert('No data to export. Please generate a report first.');
                return;
            }

            // Build export URL
            const exportUrl = 'export_csv.php?' +
                'report_type=' + encodeURIComponent(reportType) +
                '&start_date=' + encodeURIComponent(startDate) +
                '&end_date=' + encodeURIComponent(endDate) +
                (employeeId ? '&employee_id=' + encodeURIComponent(employeeId) : '');

            // Open export URL in new window to trigger download
            window.open(exportUrl, '_blank');
        }

        // Date validation
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('end_date');

            if (startDate) {
                endDateInput.min = startDate;
                if (endDateInput.value && endDateInput.value < startDate) {
                    endDateInput.value = startDate;
                }
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            const endDate = this.value;
            const startDateInput = document.getElementById('start_date');

            if (endDate) {
                startDateInput.max = endDate;
                if (startDateInput.value && startDateInput.value > endDate) {
                    startDateInput.value = endDate;
                }
            }
        });

        // Quick date range buttons
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);

            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }

        function setCurrentMonth() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('start_date').value = start.toISOString().split('T')[0];
            document.getElementById('end_date').value = end.toISOString().split('T')[0];
        }

        function setLastMonth() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);

            document.getElementById('start_date').value = start.toISOString().split('T')[0];
            document.getElementById('end_date').value = end.toISOString().split('T')[0];
        }
    </script>

<?php include '../../components/footer.php'; ?>