<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Reports & Analytics';
$message = '';

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'attendance';
$month = $_GET['month'] ?? date('Y-m');
$employee_id = $_GET['employee_id'] ?? '';

try {
    // Get employees for dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();
    
    $report_data = [];
    
    switch ($report_type) {
        case 'attendance':
            $report_data = getAttendanceReport($pdo, $month, $employee_id);
            break;
        case 'petty_cash':
            $report_data = getPettyCashReport($pdo, $month, $employee_id);
            break;
        case 'salary':
            $report_data = getSalaryReport($pdo, $month, $employee_id);
            break;
        case 'tasks':
            $report_data = getTasksReport($pdo, $month, $employee_id);
            break;
    }
    
} catch (PDOException $e) {
    $employees = [];
    $report_data = [];
    $message = '<div class="alert alert-error">Error generating report.</div>';
}

function getAttendanceReport($pdo, $month, $employee_id) {
    $where_conditions = ["DATE_FORMAT(a.date, '%Y-%m') = ?"];
    $params = [$month];
    
    if (!empty($employee_id)) {
        $where_conditions[] = 'a.employee_id = ?';
        $params[] = $employee_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_days,
            SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_days,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_days,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_days,
            AVG(a.working_hours) as avg_hours,
            SUM(a.working_hours) as total_hours
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY a.employee_id
        ORDER BY e.name
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPettyCashReport($pdo, $month, $employee_id) {
    $where_conditions = ["DATE_FORMAT(p.request_date, '%Y-%m') = ?"];
    $params = [$month];
    
    if (!empty($employee_id)) {
        $where_conditions[] = 'p.employee_id = ?';
        $params[] = $employee_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_requests,
            SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(p.amount) as total_amount,
            SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_amount
        FROM petty_cash_requests p
        JOIN employees e ON p.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY p.employee_id
        ORDER BY e.name
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getSalaryReport($pdo, $month, $employee_id) {
    $year_month = explode('-', $month);
    $where_conditions = ["s.year = ? AND s.month = ?"];
    $params = [$year_month[0], (int)$year_month[1]];
    
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

function getTasksReport($pdo, $month, $employee_id) {
    $where_conditions = ["DATE_FORMAT(t.created_at, '%Y-%m') = ?"];
    $params = [$month];
    
    if (!empty($employee_id)) {
        $where_conditions[] = 't.employee_id = ?';
        $params[] = $employee_id;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_code,
            e.name as employee_name,
            COUNT(*) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tasks,
            SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
            s.name as primary_site
        FROM tasks t
        JOIN employees e ON t.employee_id = e.id
        LEFT JOIN sites s ON t.site_id = s.id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY t.employee_id
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
                                        <option value="petty_cash" <?php echo $report_type === 'petty_cash' ? 'selected' : ''; ?>>Petty Cash Report</option>
                                        <option value="salary" <?php echo $report_type === 'salary' ? 'selected' : ''; ?>>Salary Report</option>
                                        <option value="tasks" <?php echo $report_type === 'tasks' ? 'selected' : ''; ?>>Tasks Report</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="month">Month</label>
                                    <input type="month" id="month" name="month" class="form-control" 
                                           value="<?php echo htmlspecialchars($month); ?>">
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
                        </form>
                    </div>
                </div>
                
                <!-- Report Results -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo ucfirst(str_replace('_', ' ', $report_type)); ?> Report - <?php echo date('F Y', strtotime($month . '-01')); ?></h3>
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
                                                <th>Approved Days</th>
                                                <th>Pending Days</th>
                                                <th>Rejected Days</th>
                                                <th>Avg Hours/Day</th>
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
                                                <th>Cancelled</th>
                                                <th>Primary Site</th>
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
                                                <td><span class="badge badge-success"><?php echo $row['approved_days']; ?></span></td>
                                                <td><span class="badge badge-warning"><?php echo $row['pending_days']; ?></span></td>
                                                <td><span class="badge badge-danger"><?php echo $row['rejected_days']; ?></span></td>
                                                <td><?php echo number_format($row['avg_hours'] ?: 0, 1); ?>h</td>
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
                                                <td><span class="badge badge-danger"><?php echo $row['cancelled_tasks']; ?></span></td>
                                                <td><?php echo htmlspecialchars($row['primary_site'] ?: 'N/A'); ?></td>
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
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            let csv = '';
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].querySelectorAll('th, td');
                const rowData = [];
                
                for (let j = 0; j < cells.length; j++) {
                    let cellText = cells[j].textContent.trim();
                    // Remove badge styling, keep just the text
                    cellText = cellText.replace(/\s+/g, ' ');
                    rowData.push('"' + cellText.replace(/"/g, '""') + '"');
                }
                
                csv += rowData.join(',') + '\n';
            }
            
            // Create download link
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = '<?php echo $report_type; ?>_report_<?php echo $month; ?>.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
    </script>

<?php include '../../components/footer.php'; ?>