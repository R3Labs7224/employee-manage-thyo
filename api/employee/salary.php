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
        getSalarySlips($pdo, $employee);
        break;
    default:
        sendError('Method not allowed', 405);
}

function getSalarySlips($pdo, $employee) {
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 12);
    
    try {
        // Build query based on filters
        $where_conditions = ['s.employee_id = ?'];
        $params = [$employee['id']];
        
        if ($year) {
            $where_conditions[] = 's.year = ?';
            $params[] = $year;
        }
        
        if ($month) {
            $where_conditions[] = 's.month = ?';
            $params[] = $month;
        }
        
        // Get salary slips
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   e.name as employee_name,
                   e.employee_code,
                   e.basic_salary as current_basic_salary,
                   d.name as department_name
            FROM salaries s
            JOIN employees e ON s.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY s.year DESC, s.month DESC
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        $salary_slips = $stmt->fetchAll();
        
        // Get current month attendance summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_days,
                SUM(working_hours) as total_hours
            FROM attendance 
            WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
        ");
        
        $stmt->execute([$employee['id']]);
        $current_month_attendance = $stmt->fetch();
        
        // Calculate estimated current month salary
        $estimated_salary = 0;
        if ($current_month_attendance['approved_days'] > 0) {
            $days_in_month = date('t');
            if ($employee['daily_wage'] > 0) {
                $estimated_salary = $current_month_attendance['approved_days'] * $employee['daily_wage'];
            } else {
                $estimated_salary = ($employee['basic_salary'] / $days_in_month) * $current_month_attendance['approved_days'];
            }
        }
        
        // Get yearly summary
        $stmt = $pdo->prepare("
            SELECT 
                SUM(net_salary) as total_earned,
                COUNT(*) as months_paid,
                AVG(net_salary) as avg_monthly_salary
            FROM salaries 
            WHERE employee_id = ? AND year = ?
        ");
        
        $stmt->execute([$employee['id'], date('Y')]);
        $yearly_summary = $stmt->fetch();
        
        sendSuccess('Salary slips retrieved successfully', [
            'salary_slips' => $salary_slips,
            'current_month_attendance' => $current_month_attendance,
            'estimated_current_salary' => round($estimated_salary, 2),
            'yearly_summary' => $yearly_summary,
            'employee_info' => [
                'basic_salary' => $employee['basic_salary'],
                'daily_wage' => $employee['daily_wage'],
                'epf_number' => $employee['epf_number']
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error occurred', 500);
    }
}
?>