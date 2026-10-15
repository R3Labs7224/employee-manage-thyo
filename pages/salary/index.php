<?php
// Minimal working version - pages/salary/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Step 1: Core includes
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_helpers.php';

// Step 2: Authentication
requireLogin();
if (!hasAdminPermission('salary.view')) {
    header('Location: ../../index.php?error=access_denied');
    exit;
}

// Step 3: Page setup
$pageTitle = 'Salary Management';
$message = '';

// Step 4: Get data
$salaries = [];
$employees = [];

try {
    // Query to show all employees with their latest salary records (if any)
    $stmt = $pdo->query("
        SELECT e.id as employee_id,
               e.name as employee_name,
               e.employee_code,
               e.basic_salary as employee_basic_salary,
               COALESCE(d.name, 'No Department') as department_name,
               s.id,
               s.month,
               s.year,
               COALESCE(s.basic_salary, e.basic_salary) as basic_salary,
               COALESCE(s.hra, 0) as hra,
               COALESCE(s.other_allowances, 0) as other_allowances,
               COALESCE(s.gross_salary, 0) as gross_salary,
               COALESCE(s.special_allowance, 0) as special_allowance,
               COALESCE(s.total_salary, 0) as total_salary,
               COALESCE(s.epf_employee, 0) as epf_employee,
               COALESCE(s.esi_employee, 0) as esi_employee,
               COALESCE(s.epf_employer, 0) as epf_employer,
               COALESCE(s.esi_employer, 0) as esi_employer,
               COALESCE(s.professional_tax, 0) as professional_tax,
               COALESCE(s.tds, 0) as tds,
               COALESCE(s.gratuity, 0) as gratuity,
               COALESCE(s.ghi, 0) as ghi,
               COALESCE(s.variable_bonus, 0) as variable_bonus,
               COALESCE(s.net_salary, e.basic_salary) as net_salary,
               COALESCE(s.status, 'No Record') as status,
               s.generated_date
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN salaries s ON e.id = s.employee_id
        WHERE e.status = 'active'
        ORDER BY e.employee_code ASC, s.year DESC, s.month DESC
        LIMIT 50
    ");
    $salaries = $stmt->fetchAll();
    
    // Get employees for filter
    $stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $stmt->fetchAll();
    
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $salaries = [];
    $employees = [];
}

// Helper function
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₹' . number_format((float)$amount, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        if (file_exists('../../components/sidebar.php')) {
            include '../../components/sidebar.php'; 
        } else {
            echo '<div style="width: 280px; background: #333; color: white; padding: 20px;">
                    <h3>Navigation</h3>
                    <p>Sidebar component missing</p>
                  </div>';
        }
        ?>
        
        <div class="main-content">
            <!-- Include Header -->
            <?php 
            if (file_exists('../../components/header.php')) {
                include '../../components/header.php'; 
            } else {
                echo '<div style="background: #f8f9fa; padding: 20px; border-bottom: 1px solid #ddd;">
                        <h1>Employee Management System</h1>
                        <p>Header component missing</p>
                      </div>';
            }
            ?>
            
            <div class="content">
                <!-- Error Message -->
                <?php if ($message): ?>
                    <div style="background: #fee; color: #c33; padding: 15px; margin: 20px; border: 1px solid #fcc; border-radius: 4px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Main Content -->
                <div style="margin: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <div style="padding: 20px; border-bottom: 1px solid #eee;">
                        <h3 style="margin: 0; color: #333;">
                            <i class="fas fa-table"></i> Salary Records
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                            Found <?php echo count($salaries); ?> records
                        </p>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 20px;">
                        <?php if (empty($salaries)): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                                <h3 style="margin: 0 0 10px 0;">No Salary Records</h3>
                                <p style="margin: 0;">No salary records found in the database.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Employee</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Basic Salary</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">HRA</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Other Allowances</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Gross Salary</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Special Allowance</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Total Salary</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">ESI (Employee)</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">ESI (Employer)</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Professional Tax</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">TDS</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Gratuity</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">GHI</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Variable Bonus</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600;">Net Salary</th>
                                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6; font-weight: 600;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salaries as $salary): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px;">
                                                <div>
                                                    <strong style="color: var(--primary-red);"><?php echo htmlspecialchars($salary['employee_code'] ?? 'N/A'); ?></strong><br>
                                                    <span style="color: #333;"><?php echo htmlspecialchars($salary['employee_name'] ?? 'Unknown'); ?></span><br>
                                                    <small style="color: #666;"><?php echo htmlspecialchars($salary['department_name'] ?? 'No Dept'); ?></small>
                                                </div>
                                            </td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['basic_salary'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['hra'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['other_allowances'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['gross_salary'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['special_allowance'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><strong style="color: var(--primary-red);"><?php echo formatCurrency($salary['total_salary'] ?? 0); ?></strong></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['esi_employee'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['esi_employer'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['professional_tax'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['tds'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['gratuity'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['ghi'] ?? 0); ?></td>
                                            <td style="padding: 12px;"><?php echo formatCurrency($salary['variable_bonus'] ?? 0); ?></td>
                                            <td style="padding: 12px;">
                                                <strong style="color: #28a745;">
                                                    <?php echo formatCurrency($salary['net_salary'] ?? 0); ?>
                                                </strong>
                                            </td>
                                            <td style="padding: 12px; text-align: center;">
                                                <a href="edit.php?employee_id=<?php echo $salary['employee_id']; ?>"
                                                   style="display: inline-block; padding: 6px 12px; background: #f97316; color: white;
                                                          text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 500;
                                                          transition: background-color 0.2s;">
                                                    <i class="fas fa-edit"></i> Edit Salary
                                                </a>
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

    <!-- Include Footer -->
    <?php 
    if (file_exists('../../components/footer.php')) {
        include '../../components/footer.php'; 
    }
    ?>
</body>
</html>