<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission
if (!hasPermission('superadmin')) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Edit Employee';
$message = '';
$errors = [];

// Get employee ID
$employee_id = $_GET['id'] ?? 0;
if (!is_numeric($employee_id)) {
    header('Location: index.php');
    exit;
}

// Get employee data
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active'");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: index.php?error=Employee not found');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php?error=Database error');
    exit;
}

// Get departments, shifts, and sites for dropdowns
try {
    $departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
    $shifts = $pdo->query("SELECT * FROM shifts WHERE status = 'active' ORDER BY name")->fetchAll();
    $sites = $pdo->query("SELECT * FROM sites WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $departments = $shifts = $sites = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $employee_code = sanitize($_POST['employee_code']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $shift_id = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : null;
    $site_id = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
    $basic_salary = (float)$_POST['basic_salary'];
    $daily_wage = (float)$_POST['daily_wage'];
    $epf_number = sanitize($_POST['epf_number']);
    $joining_date = $_POST['joining_date'];
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($employee_code)) $errors[] = 'Employee code is required';
    if (empty($joining_date)) $errors[] = 'Joining date is required';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if employee code already exists (excluding current employee)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ? AND id != ?");
            $stmt->execute([$employee_code, $employee_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Employee code already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred';
        }
    }
    
    // Check if email already exists (excluding current employee and if email is provided)
    if (empty($errors) && !empty($email)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
            $stmt->execute([$email, $employee_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred';
        }
    }
    
    if (empty($errors)) {
        try {
            // Prepare update query
            if (!empty($password)) {
                // Update with new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE employees SET 
                        employee_code = ?, name = ?, email = ?, phone = ?, 
                        department_id = ?, shift_id = ?, site_id = ?,
                        basic_salary = ?, daily_wage = ?, epf_number = ?, 
                        joining_date = ?, password = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $employee_code, $name, $email, $phone, $department_id, 
                    $shift_id, $site_id, $basic_salary, $daily_wage, 
                    $epf_number, $joining_date, $hashedPassword, $employee_id
                ]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("
                    UPDATE employees SET 
                        employee_code = ?, name = ?, email = ?, phone = ?, 
                        department_id = ?, shift_id = ?, site_id = ?,
                        basic_salary = ?, daily_wage = ?, epf_number = ?, 
                        joining_date = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $employee_code, $name, $email, $phone, $department_id, 
                    $shift_id, $site_id, $basic_salary, $daily_wage, 
                    $epf_number, $joining_date, $employee_id
                ]);
            }
            
            header('Location: index.php?success=Employee updated successfully');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Error updating employee: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
    }
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
                
                <div class="card">
                    <div class="card-header">
                        <h3>Edit Employee: <?php echo htmlspecialchars($employee['name']); ?></h3>
                        <a href="index.php" class="btn" style="background: #6c757d; color: white;">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" data-validate>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label for="employee_code">Employee Code *</label>
                                    <input type="text" id="employee_code" name="employee_code" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['employee_code'] ?? $employee['employee_code']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="name">Full Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? $employee['name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" class="form-control"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? $employee['email']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone *</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $employee['phone']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="department_id">Department</label>
                                    <select id="department_id" name="department_id" class="form-control">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo (($_POST['department_id'] ?? $employee['department_id']) == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="shift_id">Shift</label>
                                    <select id="shift_id" name="shift_id" class="form-control">
                                        <option value="">Select Shift</option>
                                        <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['id']; ?>"
                                                <?php echo (($_POST['shift_id'] ?? $employee['shift_id']) == $shift['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($shift['name']); ?> 
                                            (<?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_id">Site</label>
                                    <select id="site_id" name="site_id" class="form-control">
                                        <option value="">Select Site</option>
                                        <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['id']; ?>"
                                                <?php echo (($_POST['site_id'] ?? $employee['site_id']) == $site['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($site['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="basic_salary">Basic Salary (₹)</label>
                                    <input type="number" id="basic_salary" name="basic_salary" class="form-control" step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($_POST['basic_salary'] ?? $employee['basic_salary']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="daily_wage">Daily Wage (₹)</label>
                                    <input type="number" id="daily_wage" name="daily_wage" class="form-control" step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($_POST['daily_wage'] ?? $employee['daily_wage']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="epf_number">EPF Number</label>
                                    <input type="text" id="epf_number" name="epf_number" class="form-control"
                                           value="<?php echo htmlspecialchars($_POST['epf_number'] ?? $employee['epf_number']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="joining_date">Joining Date *</label>
                                    <input type="date" id="joining_date" name="joining_date" class="form-control" required
                                           value="<?php echo htmlspecialchars($_POST['joining_date'] ?? $employee['joining_date']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">New Password (Leave blank to keep current)</label>
                                    <input type="password" id="password" name="password" class="form-control"
                                           placeholder="Enter new password or leave blank">
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; text-align: right;">
                                <a href="index.php" class="btn" style="background: #6c757d; color: white; margin-right: 1rem;">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../../components/footer.php'; ?>