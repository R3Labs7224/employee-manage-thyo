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

$pageTitle = 'Employees Management';
$message = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="alert alert-success">Employee deactivated successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deactivating employee.</div>';
    }
}

// Get all employees with their department, shift, and site info
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               d.name as department_name,
               s.name as shift_name,
               st.name as site_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN shifts s ON e.shift_id = s.id
        LEFT JOIN sites st ON e.site_id = st.id
        WHERE e.status = 'active'
        ORDER BY e.name
    ");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $employees = [];
    $message = '<div class="alert alert-error">Error fetching employees.</div>';
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
                        <h3>All Employees</h3>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Employee
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-users fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Employees Found</h3>
                                <p>Start by adding your first employee.</p>
                                <a href="add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Employee
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee Code</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Department</th>
                                            <th>Site</th>
                                            <th>Salary</th>
                                            <th>Joining Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['email'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['department_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($employee['site_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo formatCurrency($employee['basic_salary']); ?></td>
                                            <td><?php echo formatDate($employee['joining_date'], 'M d, Y'); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                                       class="btn" style="background: #f39c12; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $employee['id']; ?>" 
                                                       class="btn btn-delete" style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                       onclick="return confirm('Are you sure you want to deactivate this employee?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
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