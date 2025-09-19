<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Core includes
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Authentication
requireLogin();
if (!hasPermission('superadmin')) {
    header('Location: ../../index.php');
    exit;
}

// Page setup
$pageTitle = 'Edit Salary';
$message = '';
$salary = null;

// Get salary ID
$salary_id = (int)($_GET['id'] ?? 0);
if (!$salary_id) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_POST) {
    try {
        $stmt = $pdo->prepare("
            UPDATE salaries SET 
                basic_salary = ?, hra = ?, other_allowances = ?, gross_salary = ?, 
                special_allowance = ?, total_salary = ?, epf_employee = ?, esi_employee = ?,
                epf_employer = ?, esi_employer = ?, professional_tax = ?, tds = ?,
                gratuity = ?, ghi = ?, variable_bonus = ?, bonus = ?, advance = ?, 
                deductions = ?, net_salary = ?
            WHERE id = ?
        ");
        
        $net_salary = (float)$_POST['total_salary'] - (float)$_POST['epf_employee'] - (float)$_POST['esi_employee'] - 
                     (float)$_POST['professional_tax'] - (float)$_POST['tds'] - (float)$_POST['gratuity'] - 
                     (float)$_POST['ghi'] - (float)$_POST['advance'] + (float)$_POST['bonus'] + (float)$_POST['variable_bonus'];
        
        $total_deductions = (float)$_POST['epf_employee'] + (float)$_POST['esi_employee'] + 
                           (float)$_POST['professional_tax'] + (float)$_POST['tds'] + 
                           (float)$_POST['gratuity'] + (float)$_POST['ghi'] + (float)$_POST['advance'];
        
        $stmt->execute([
            $_POST['basic_salary'], $_POST['hra'], $_POST['other_allowances'], $_POST['gross_salary'],
            $_POST['special_allowance'], $_POST['total_salary'], $_POST['epf_employee'], $_POST['esi_employee'],
            $_POST['epf_employer'], $_POST['esi_employer'], $_POST['professional_tax'], $_POST['tds'],
            $_POST['gratuity'], $_POST['ghi'], $_POST['variable_bonus'], $_POST['bonus'], $_POST['advance'],
            $total_deductions, $net_salary, $salary_id
        ]);
        
        $message = "Salary updated successfully!";
        
    } catch (Exception $e) {
        $message = "Error updating salary: " . $e->getMessage();
    }
}

// Get salary data
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               e.name as employee_name,
               e.employee_code,
               COALESCE(d.name, 'No Department') as department_name
        FROM salaries s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE s.id = ?
    ");
    $stmt->execute([$salary_id]);
    $salary = $stmt->fetch();
    
    if (!$salary) {
        header('Location: index.php');
        exit;
    }
    
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
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
        }
        ?>
        
        <div class="main-content">
            <!-- Include Header -->
            <?php 
            if (file_exists('../../components/header.php')) {
                include '../../components/header.php'; 
            }
            ?>
            
            <div class="content">
                <!-- Message -->
                <?php if ($message): ?>
                    <div style="background: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border: 1px solid #d6e9c6; border-radius: 4px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Back Button -->
                <div style="margin: 20px;">
                    <a href="index.php" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                        <i class="fas fa-arrow-left"></i> Back to Salary List
                    </a>
                </div>
                
                <!-- Main Content -->
                <div style="margin: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <div style="padding: 20px; border-bottom: 1px solid #eee;">
                        <h3 style="margin: 0; color: #333;">
                            <i class="fas fa-edit"></i> Edit Salary - <?php echo htmlspecialchars($salary['employee_name']); ?>
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                            Employee Code: <?php echo htmlspecialchars($salary['employee_code']); ?> | 
                            Department: <?php echo htmlspecialchars($salary['department_name']); ?> |
                            Period: <?php echo date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])); ?>
                        </p>
                    </div>
                    
                    <!-- Form Content -->
                    <div style="padding: 20px;">
                        <form method="POST" style="max-width: 1200px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                
                                <!-- Salary Components -->
                                <div>
                                    <h4 style="color: var(--primary-red); margin-bottom: 20px; border-bottom: 2px solid var(--primary-red); padding-bottom: 10px;">
                                        <i class="fas fa-money-bill-wave"></i> Salary Components
                                    </h4>
                                    
                                    <div style="display: grid; gap: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Basic Salary</label>
                                            <input type="number" name="basic_salary" step="0.01" 
                                                   value="<?php echo $salary['basic_salary']; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">HRA</label>
                                            <input type="number" name="hra" step="0.01" 
                                                   value="<?php echo $salary['hra'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Other Allowances</label>
                                            <input type="number" name="other_allowances" step="0.01" 
                                                   value="<?php echo $salary['other_allowances'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Gross Salary</label>
                                            <input type="number" name="gross_salary" step="0.01" 
                                                   value="<?php echo $salary['gross_salary'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Special Allowance</label>
                                            <input type="number" name="special_allowance" step="0.01" 
                                                   value="<?php echo $salary['special_allowance'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: var(--primary-red);">Total Salary</label>
                                            <input type="number" name="total_salary" step="0.01" 
                                                   value="<?php echo $salary['total_salary'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 2px solid var(--primary-red); border-radius: 4px; background: #f8f9fa;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bonus</label>
                                            <input type="number" name="bonus" step="0.01" 
                                                   value="<?php echo $salary['bonus'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Variable Bonus</label>
                                            <input type="number" name="variable_bonus" step="0.01" 
                                                   value="<?php echo $salary['variable_bonus'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Deductions -->
                                <div>
                                    <h4 style="color: #dc3545; margin-bottom: 20px; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">
                                        <i class="fas fa-minus-circle"></i> Deductions
                                    </h4>
                                    
                                    <div style="display: grid; gap: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">EPF (Employee)</label>
                                            <input type="number" name="epf_employee" step="0.01" 
                                                   value="<?php echo $salary['epf_employee'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">ESI (Employee)</label>
                                            <input type="number" name="esi_employee" step="0.01" 
                                                   value="<?php echo $salary['esi_employee'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">EPF (Employer)</label>
                                            <input type="number" name="epf_employer" step="0.01" 
                                                   value="<?php echo $salary['epf_employer'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">ESI (Employer)</label>
                                            <input type="number" name="esi_employer" step="0.01" 
                                                   value="<?php echo $salary['esi_employer'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Professional Tax</label>
                                            <input type="number" name="professional_tax" step="0.01" 
                                                   value="<?php echo $salary['professional_tax'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">TDS</label>
                                            <input type="number" name="tds" step="0.01" 
                                                   value="<?php echo $salary['tds'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Gratuity</label>
                                            <input type="number" name="gratuity" step="0.01" 
                                                   value="<?php echo $salary['gratuity'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">GHI</label>
                                            <input type="number" name="ghi" step="0.01" 
                                                   value="<?php echo $salary['ghi'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Advance</label>
                                            <input type="number" name="advance" step="0.01" 
                                                   value="<?php echo $salary['advance'] ?? 0; ?>"
                                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #eee;">
                                <button type="submit" style="padding: 12px 30px; background: var(--primary-red); color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">
                                    <i class="fas fa-save"></i> Update Salary
                                </button>
                            </div>
                        </form>
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