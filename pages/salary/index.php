<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission (only superadmin can access salary info)
if (!hasPermission('superadmin')) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Salary Management';
$message = '';

// Get filter parameters
$month_filter = $_GET['month'] ?? date('Y-m');
$employee_filter = $_GET['employee'] ?? '';

// Handle salary generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_salary'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $bonus = (float)$_POST['bonus'];
    $advance = (float)$_POST['advance'];
    $deductions = (float)$_POST['deductions'];
    
    try {
        // Get employee details
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            // Calculate attendance-based salary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as present_days,
                    SUM(working_hours) as total_hours
                FROM attendance 
                WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND status = 'approved'
            ");
            $stmt->execute([$employee_id, $month, $year]);
            $attendance = $stmt->fetch();
            
            $present_days = $attendance['present_days'] ?: 0;
            $total_working_days = date('t', mktime(0, 0, 0, $month, 1, $year)); // Days in month
            
            // Calculate salary based on daily wage or monthly salary
            if ($employee['daily_wage'] > 0) {
                $calculated_salary = $present_days * $employee['daily_wage'];
            } else {
                $calculated_salary = ($employee['basic_salary'] / $total_working_days) * $present_days;
            }
            
            $net_salary = $calculated_salary + $bonus - $advance - $deductions;
            
            // Insert or update salary record
            $stmt = $pdo->prepare("
                INSERT INTO salaries (
                    employee_id, month, year, basic_salary, total_working_days, present_days,
                    calculated_salary, bonus, advance, deductions, net_salary, status, generated_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', CURDATE())
                ON DUPLICATE KEY UPDATE
                    basic_salary = ?, total_working_days = ?, present_days = ?,
                    calculated_salary = ?, bonus = ?, advance = ?, deductions = ?, 
                    net_salary = ?, status = 'processed', generated_date = CURDATE()
            ");
            
            $stmt->execute([
                $employee_id, $month, $year, $employee['basic_salary'], $total_working_days, $present_days,
                $calculated_salary, $bonus, $advance, $deductions, $net_salary,
                $employee['basic_salary'], $total_working_days, $present_days,
                $calculated_salary, $bonus, $advance, $deductions, $net_salary
            ]);
            
            $message = '<div class="alert alert-success">Salary generated successfully!</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error generating salary.</div>';
    }
}

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($month_filter)) {
    $year_month = explode('-', $month_filter);
    $where_conditions[] = 's.year = ? AND s.month = ?';
    $params[] = $year_month[0];
    $params[] = (int)$year_month[1];
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'e.id = ?';
    $params[] = $employee_filter;
}

try {
    // Get salary records
    $sql = "
        SELECT s.*, 
               e.name as employee_name,
               e.employee_code
        FROM salaries s
        JOIN employees e ON s.employee_id = e.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY s.year DESC, s.month DESC, e.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $salaries = $stmt->fetchAll();
    
    // Get employees for dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();
    
} catch (PDOException $e) {
    $salaries = [];
    $employees = [];
    $message = '<div class="alert alert-error">Error fetching salary records.</div>';
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
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Generate Salary Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Generate Salary</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="employee_id">Employee *</label>
                                    <select id="employee_id" name="employee_id" class="form-control" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="month">Month *</label>
                                        <select id="month" name="month" class="form-control" required>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo date('n') == $i ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="year">Year *</label>
                                        <select id="year" name="year" class="form-control" required>
                                            <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo date('Y') == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bonus">Bonus (₹)</label>
                                    <input type="number" id="bonus" name="bonus" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="advance">Advance (₹)</label>
                                    <input type="number" id="advance" name="advance" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="deductions">Deductions (₹)</label>
                                    <input type="number" id="deductions" name="deductions" class="form-control" step="0.01" min="0" value="0">
                                </div>
                                
                                <button type="submit" name="generate_salary" class="btn btn-primary">
                                    <i class="fas fa-calculator"></i> Generate Salary
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Filter Salary Records</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="form-group">
                                    <label for="month">Month/Year</label>
                                    <input type="month" id="month" name="month" class="form-control" 
                                           value="<?php echo htmlspecialchars($month_filter); ?>">
                                </div>
                                
                                <div class="form-group">
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
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="index.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Salary Records -->
                <div class="card">
                    <div class="card-header">
                        <h3>Salary Records</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($salaries)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-money-check-alt fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Salary Records Found</h3>
                                <p>Generate salary for employees using the form above.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Month/Year</th>
                                            <th>Basic Salary</th>
                                            <th>Present Days</th>
                                            <th>Calculated</th>
                                            <th>Bonus</th>
                                            <th>Advance</th>
                                            <th>Deductions</th>
                                            <th>Net Salary</th>
                                            <th>Status</th>
                                            <th>Generated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salaries as $salary): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($salary['employee_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($salary['employee_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])); ?>
                                            </td>
                                            <td><?php echo formatCurrency($salary['basic_salary']); ?></td>
                                            <td>
                                                <?php echo $salary['present_days']; ?>/<?php echo $salary['total_working_days']; ?>
                                                <br><small style="color: #666;">
                                                    <?php echo round(($salary['present_days'] / $salary['total_working_days']) * 100, 1); ?>%
                                                </small>
                                            </td>
                                            <td><?php echo formatCurrency($salary['calculated_salary']); ?></td>
                                            <td>
                                                <?php if ($salary['bonus'] > 0): ?>
                                                    <span style="color: #28a745;">+<?php echo formatCurrency($salary['bonus']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #666;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($salary['advance'] > 0): ?>
                                                    <span style="color: #dc3545;">-<?php echo formatCurrency($salary['advance']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #666;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($salary['deductions'] > 0): ?>
                                                    <span style="color: #dc3545;">-<?php echo formatCurrency($salary['deductions']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #666;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo formatCurrency($salary['net_salary']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $salary['status'] === 'paid' ? 'success' : 
                                                        ($salary['status'] === 'processed' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($salary['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($salary['generated_date']): ?>
                                                    <?php echo formatDate($salary['generated_date'], 'M d, Y'); ?>
                                                <?php else: ?>
                                                    <span style="color: #666;">-</span>
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

<?php include '../../components/footer.php'; ?>