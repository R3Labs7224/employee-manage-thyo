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

$pageTitle = 'Departments Management';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_department'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $message = '<div class="alert alert-success">Department added successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error adding department.</div>';
            }
        } else {
            $message = '<div class="alert alert-error">Department name is required.</div>';
        }
    }
    
    if (isset($_POST['edit_department'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
                $message = '<div class="alert alert-success">Department updated successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error updating department.</div>';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE departments SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="alert alert-success">Department deactivated successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deactivating department.</div>';
    }
}

// Get all departments
try {
    $stmt = $pdo->query("
        SELECT d.*, 
               COUNT(e.id) as employee_count
        FROM departments d
        LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
        WHERE d.status = 'active'
        GROUP BY d.id
        ORDER BY d.name
    ");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
    $message = '<div class="alert alert-error">Error fetching departments.</div>';
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
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                    <!-- Add Department Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Add Department</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="name">Department Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" name="add_department" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Department
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Departments List -->
                    <div class="card">
                        <div class="card-header">
                            <h3>All Departments</h3>
                            <span class="badge badge-success"><?php echo count($departments); ?> Active</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($departments)): ?>
                                <div style="text-align: center; padding: 2rem; color: #666;">
                                    <i class="fas fa-building fa-2x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No departments found. Create your first department.</p>
                                </div>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Employees</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dept['description'] ?: 'No description'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $dept['employee_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $dept['employee_count']; ?> employees
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($dept['created_at'], 'M d, Y'); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <button type="button" class="btn" style="background: #f39c12; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['name']); ?>', '<?php echo htmlspecialchars($dept['description']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($dept['employee_count'] == 0): ?>
                                                    <a href="?delete=<?php echo $dept['id']; ?>" 
                                                       class="btn" style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                       onclick="return confirm('Are you sure you want to delete this department?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
            <h3 style="margin-bottom: 1rem;">Edit Department</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label for="edit_name">Department Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div style="text-align: right; gap: 1rem; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_department" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editDepartment(id, name, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>

<?php include '../../components/footer.php'; ?>