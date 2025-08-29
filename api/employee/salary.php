<?php
require_once '../../config/database.php';
require_once '../common/response.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $limit = min(max((int)($_GET['limit'] ?? 12), 1), 100); // Between 1 and 100
    
    try {
        // Build the salary slips query - FIXED: Specify table aliases for ambiguous columns
        $sql = "
            SELECT s.*, 
                   e.name as employee_name,
                   e.employee_code,
                   e.basic_salary as current_basic_salary,
                   COALESCE(d.name, 'No Department') as department_name
            FROM salaries s
            JOIN employees e ON s.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE s.employee_id = ?
        ";
        
        $params = [$employee['id']];
        
        if ($year > 0) {
            $sql .= " AND s.year = ?";
            $params[] = $year;
        }
        
        if ($month > 0) {
            $sql .= " AND s.month = ?";
            $params[] = $month;
        }
        
        $sql .= " ORDER BY s.year DESC, s.month DESC LIMIT " . $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $salary_slips = $stmt->fetchAll();
        
        // Get current month attendance summary
        $current_month = date('m');
        $current_year = date('Y');
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_days,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_days,
                COALESCE(0, 0) as total_hours
            FROM attendance 
            WHERE employee_id = ? 
            AND MONTH(date) = ? 
            AND YEAR(date) = ?
        ");
        $stmt->execute([$employee['id'], $current_month, $current_year]);
        $current_month_attendance = $stmt->fetch() ?: [
            'total_days' => 0, 'approved_days' => 0, 'pending_days' => 0, 'total_hours' => 0
        ];
        
        // Get employee basic info
        $stmt = $pdo->prepare("
            SELECT e.basic_salary, e.daily_wage, e.name, e.employee_code, e.epf_number,
                   COALESCE(d.name, 'No Department') as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.id = ?
        ");
        $stmt->execute([$employee['id']]);
        $employee_info = $stmt->fetch();
        
        // Calculate estimated current month salary based on attendance
        $days_in_month = date('t');
        $current_day = date('d');
        $attendance_rate = $current_month_attendance['approved_days'] / $current_day;
        
        if ($employee_info['daily_wage'] > 0) {
            $estimated_salary = $current_month_attendance['approved_days'] * $employee_info['daily_wage'];
        } else {
            $estimated_salary = ($employee_info['basic_salary'] / $days_in_month) * $current_month_attendance['approved_days'];
        }
        
        // Get yearly summary
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(net_salary), 0) as total_earned,
                COALESCE(SUM(deductions), 0) as total_deductions,
                COALESCE(SUM(bonus), 0) as total_bonus,
                COUNT(*) as total_months,
                COALESCE(AVG(net_salary), 0) as avg_monthly_salary
            FROM salaries 
            WHERE employee_id = ? AND year = ?
        ");
        $stmt->execute([$employee['id'], $year]);
        $yearly_summary = $stmt->fetch() ?: [
            'total_earned' => 0, 'total_deductions' => 0, 'total_bonus' => 0, 
            'total_months' => 0, 'avg_monthly_salary' => 0
        ];
        
        // Format salary slips data
        $formatted_slips = [];
        foreach ($salary_slips as $slip) {
            $formatted_slips[] = [
                'id' => (int)$slip['id'],
                'month' => (int)$slip['month'],
                'year' => (int)$slip['year'],
                'basic_salary' => (float)($slip['basic_salary'] ?? 0),
                'total_working_days' => (int)($slip['total_working_days'] ?? 0),
                'present_days' => (int)($slip['present_days'] ?? 0),
                'calculated_salary' => (float)($slip['calculated_salary'] ?? 0),
                'gross_salary' => (float)($slip['calculated_salary'] ?? 0), // For compatibility
                'total_hours' => (float)($slip['present_days'] ?? 0) * 8, // Assume 8 hours per day
                'bonus' => (float)($slip['bonus'] ?? 0),
                'advance' => (float)($slip['advance'] ?? 0),
                'deductions' => (float)($slip['deductions'] ?? 0),
                'net_salary' => (float)$slip['net_salary'],
                'status' => $slip['status'] ?? 'draft',
                'generated_date' => $slip['generated_date'],
                'created_at' => $slip['created_at'],
                'employee_name' => $slip['employee_name'],
                'employee_code' => $slip['employee_code'],
                'current_basic_salary' => (float)$slip['current_basic_salary'],
                'department_name' => $slip['department_name']
            ];
        }
        
        sendSuccess('Salary data retrieved successfully', [
            'salary_slips' => $formatted_slips,
            'current_month_attendance' => [
                'total_days' => (int)$current_month_attendance['total_days'],
                'approved_days' => (int)$current_month_attendance['approved_days'],
                'pending_days' => (int)$current_month_attendance['pending_days'],
                'total_hours' => (float)$current_month_attendance['total_hours']
            ],
            'employee_info' => [
                'basic_salary' => (float)$employee_info['basic_salary'],
                'daily_wage' => (float)$employee_info['daily_wage'],
                'name' => $employee_info['name'],
                'employee_code' => $employee_info['employee_code'],
                'department_name' => $employee_info['department_name'],
                'epf_number' => $employee_info['epf_number']
            ],
            'estimated_current_salary' => round($estimated_salary, 2),
            'yearly_summary' => [
                'total_earned' => (float)$yearly_summary['total_earned'],
                'total_deductions' => (float)$yearly_summary['total_deductions'],
                'total_bonus' => (float)$yearly_summary['total_bonus'],
                'total_months' => (int)$yearly_summary['total_months'],
                'avg_monthly_salary' => (float)$yearly_summary['avg_monthly_salary']
            ],
            'year' => $year,
            'month' => $month
        ]);
        
    } catch (PDOException $e) {
        // Log the actual error for debugging
        error_log("Salary API PDO Error: " . $e->getMessage());
        error_log("Query: " . ($sql ?? 'Unknown'));
        error_log("Query params: " . print_r($params ?? [], true));
        sendError('Database error occurred: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log("General Salary API Error: " . $e->getMessage());
        sendError('An error occurred while fetching salary data', 500);
    }
}
?>