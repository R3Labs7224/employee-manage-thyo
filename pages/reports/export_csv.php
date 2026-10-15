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

// Get parameters
$report_type = $_GET['report_type'] ?? 'attendance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$employee_id = $_GET['employee_id'] ?? '';

// Copy the report functions here to avoid including the entire index.php
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

// Get the report data
$report_data = [];
try {
    switch ($report_type) {
        case 'attendance':
            $report_data = getAttendanceReport($pdo, $start_date, $end_date, $employee_id);
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
        case 'salary':
            $report_data = getSalaryReport($pdo, $start_date, $end_date, $employee_id);
            break;
        case 'petty_cash':
            $report_data = getPettyCashReport($pdo, $start_date, $end_date, $employee_id);
            break;
    }
} catch (Exception $e) {
    die('Error generating report: ' . $e->getMessage());
}

// Set CSV headers
$display_type = $report_type === 'petty_cash' ? 'expense' : $report_type;
$filename = $display_type . '_report_' . $start_date . '_to_' . $end_date . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

// Open output stream
$output = fopen('php://output', 'w');

// Generate CSV based on report type
if (!empty($report_data)) {
    switch ($report_type) {
        case 'attendance':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Total Days', 'Present Days', 'Absent Days', 'Approved', 'Pending', 'Rejected', 'Total Hours']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['total_days'],
                    $row['present_days'],
                    $row['absent_days'],
                    $row['approved_days'],
                    $row['pending_days'],
                    $row['rejected_days'],
                    number_format($row['total_hours'] ?: 0, 1)
                ]);
            }
            break;

        case 'tasks':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Total Tasks', 'Completed', 'Active', 'In Progress', 'Cancelled', 'Total Budget', 'Sites Worked', 'Sites List', 'Task IDs']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['total_tasks'],
                    $row['completed_tasks'],
                    $row['active_tasks'],
                    $row['in_progress_tasks'],
                    $row['cancelled_tasks'],
                    $row['total_budget'] ?: 0,
                    $row['sites_worked'],
                    $row['sites_list'] ?: 'N/A',
                    $row['task_identifiers'] ?: 'N/A'
                ]);
            }
            break;

        case 'expenses':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Total Expenses', 'Approved', 'Pending', 'Rejected', 'Total Amount', 'Approved Amount', 'Pending Amount', 'Categories']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['total_expenses'],
                    $row['approved_expenses'],
                    $row['pending_expenses'],
                    $row['rejected_expenses'],
                    $row['total_amount'],
                    $row['approved_amount'],
                    $row['pending_amount'],
                    $row['categories'] ?: 'N/A'
                ]);
            }
            break;

        case 'leave':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Total Requests', 'Approved', 'Pending', 'Rejected', 'Leave Days Taken', 'Leave Types']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['total_requests'],
                    $row['approved_requests'],
                    $row['pending_requests'],
                    $row['rejected_requests'],
                    $row['total_leave_days'],
                    $row['leave_types'] ?: 'N/A'
                ]);
            }
            break;

        case 'salary':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Basic Salary', 'Working Days', 'Present Days', 'Calculated Salary', 'Bonus', 'Advance', 'Deductions', 'Net Salary', 'Status']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['basic_salary'],
                    $row['total_working_days'],
                    $row['present_days'],
                    $row['calculated_salary'],
                    $row['bonus'],
                    $row['advance'],
                    $row['deductions'],
                    $row['net_salary'],
                    $row['status']
                ]);
            }
            break;

        case 'petty_cash':
            fputcsv($output, ['Employee Code', 'Employee Name', 'Total Requests', 'Approved', 'Pending', 'Rejected', 'Total Amount', 'Approved Amount', 'Pending Amount']);
            foreach ($report_data as $row) {
                fputcsv($output, [
                    $row['employee_code'],
                    $row['employee_name'],
                    $row['total_requests'],
                    $row['approved_requests'],
                    $row['pending_requests'],
                    $row['rejected_requests'],
                    $row['total_amount'],
                    $row['approved_amount'],
                    $row['pending_amount']
                ]);
            }
            break;
    }
} else {
    fputcsv($output, ['No data found for the selected criteria']);
}

fclose($output);
exit;
?>