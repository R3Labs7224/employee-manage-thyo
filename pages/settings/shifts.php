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

$pageTitle = 'Shifts Management';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_shift'])) {
        $name = sanitize($_POST['name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        if (!empty($name) && !empty($start_time) && !empty($end_time)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO shifts (name, start_time, end_time) VALUES (?, ?, ?)");
                $stmt->execute([$name, $start_time, $end_time]);
                $message = '<div class="alert alert-success">Shift added successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error adding shift.</div>';
            }
        } else {
            $message = '<div class="alert alert-error">All fields are required.</div>';
        }
    }
    
    if (isset($_POST['edit_shift'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        if (!empty($name) && !empty($start_time) && !empty($end_time)) {
            try {
                $stmt = $pdo->prepare("UPDATE shifts SET name = ?, start_time = ?, end_time = ? WHERE id = ?");
                $stmt->execute([$name, $start_time, $end_time, $id]);
                $message = '<div class="alert alert-success">Shift updated successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error updating shift.</div>';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE shifts SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="alert alert-success">Shift deactivated successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deactivating shift.</div>';
    }
}

// Get all shifts
try {
    $stmt = $pdo->query("
        SELECT s.*, 
               COUNT(e.id) as employee_count
        FROM shifts s
        LEFT JOIN employees e ON s.id = e.shift_id AND e.status = 'active'
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY s.start_time
    ");
    $shifts = $stmt->fetchAll();
} catch (PDOException $e) {
    $shifts = [];
    $message = '<div class="alert alert-error">Error fetching shifts.</div>';
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
                <div style="margin-bottom: 1rem;">
                    <a href="departments.php" class="btn" style="background: #6c757d; color: white;">
                        <i class="fas fa-building"></i> Departments
                    </a>
                    <a href="sites.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                        <i class="fas fa-map-marker-alt"></i> Sites
                    </a>
                    <a href="shifts.php" class="btn btn-primary" style="margin-left: 0.5rem;">
                        <i class="fas fa-clock"></i> Shifts
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                    <!-- Add Shift Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Add Shift</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="name">Shift Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" required placeholder="e.g., Morning Shift">
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_time">Start Time *</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_time">End Time *</label>
                                    <input type="time" id="end_time" name="end_time" class="form-control" required>
                                </div>
                                
                                <button type="submit" name="add_shift" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Shift
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Shifts List -->
                    <div class="card">
                        <div class="card-header">
                            <h3>All Shifts</h3>
                            <span class="badge badge-success"><?php echo count($shifts); ?> Active</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($shifts)): ?>
                                <div style="text-align: center; padding: 2rem; color: #666;">
                                    <i class="fas fa-clock fa-2x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No shifts found. Create your first shift.</p>
                                </div>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Shift Name</th>
                                            <th>Start Time</th>
                                            <th>End Time</th>
                                            <th>Duration</th>
                                            <th>Employees</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shifts as $shift): ?>
                                        <?php
                                            $start = new DateTime($shift['start_time']);
                                            $end = new DateTime($shift['end_time']);
                                            
                                            // Handle overnight shifts
                                            if ($end < $start) {
                                                $end->add(new DateInterval('P1D'));
                                            }
                                            
                                            $duration = $start->diff($end);
                                            $duration_text = $duration->h . 'h ' . $duration->i . 'm';
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($shift['name']); ?></strong></td>
                                            <td><?php echo date('g:i A', strtotime($shift['start_time'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($shift['end_time'])); ?></td>
                                            <td><?php echo $duration_text; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $shift['employee_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $shift['employee_count']; ?> employees
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($shift['created_at'], 'M d, Y'); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <button type="button" class="btn" style="background: #f39c12; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="editShift(<?php echo $shift['id']; ?>, '<?php echo htmlspecialchars($shift['name']); ?>', '<?php echo $shift['start_time']; ?>', '<?php echo $shift['end_time']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($shift['employee_count'] == 0): ?>
                                                    <a href="?delete=<?php echo $shift['id']; ?>" 
                                                       class="btn" style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                       onclick="return confirm('Are you sure you want to delete this shift?')">
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
            <h3 style="margin-bottom: 1rem;">Edit Shift</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label for="edit_name">Shift Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_start_time">Start Time *</label>
                    <input type="time" id="edit_start_time" name="start_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_end_time">End Time *</label>
                    <input type="time" id="edit_end_time" name="end_time" class="form-control" required>
                </div>
                
                <div style="text-align: right; gap: 1rem; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_shift" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editShift(id, name, startTime, endTime) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
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