<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Tasks Management';
$message = '';

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';
$site_filter = $_GET['site'] ?? '';

// Handle task actions (complete/cancel/delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'bulk_delete' && hasPermission('superadmin')) {
        // Handle bulk delete
        $selected_tasks = $_POST['selected_tasks'] ?? [];
        if (!empty($selected_tasks)) {
            try {
                // Delete selected tasks and their associated files
                $placeholders = str_repeat('?,', count($selected_tasks) - 1) . '?';
                
                // Get task images before deletion
                $stmt = $pdo->prepare("SELECT task_image, completion_image FROM tasks WHERE id IN ($placeholders)");
                $stmt->execute($selected_tasks);
                $task_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Delete tasks from database
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id IN ($placeholders)");
                $stmt->execute($selected_tasks);
                
                // Delete associated files
                foreach ($task_files as $file_info) {
                    if ($file_info['task_image']) {
                        $file_path = '../../assets/images/uploads/tasks/' . $file_info['task_image'];
                        if (file_exists($file_path)) unlink($file_path);
                    }
                    if ($file_info['completion_image']) {
                        $file_path = '../../assets/images/uploads/tasks/' . $file_info['completion_image'];
                        if (file_exists($file_path)) unlink($file_path);
                    }
                }
                
                $count = count($selected_tasks);
                $message = '<div class="alert alert-success">' . $count . ' task(s) deleted successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error deleting tasks.</div>';
            }
        }
    } else {
        // Handle individual actions
        $task_id = (int)$_POST['task_id'];
        
        if (in_array($action, ['complete', 'cancel']) && hasPermission('supervisor')) {
            try {
                $new_status = $action === 'complete' ? 'completed' : 'cancelled';
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET status = ?, end_time = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $task_id]);
                $message = '<div class="alert alert-success">Task ' . $new_status . ' successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error updating task status.</div>';
            }
        } elseif ($action === 'delete' && hasPermission('superadmin')) {
            try {
                // Get task files before deletion
                $stmt = $pdo->prepare("SELECT task_image, completion_image FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);
                $task_files = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete task from database
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);
                
                // Delete associated files
                if ($task_files) {
                    if ($task_files['task_image']) {
                        $file_path = '../../assets/images/uploads/tasks/' . $task_files['task_image'];
                        if (file_exists($file_path)) unlink($file_path);
                    }
                    if ($task_files['completion_image']) {
                        $file_path = '../../assets/images/uploads/tasks/' . $task_files['completion_image'];
                        if (file_exists($file_path)) unlink($file_path);
                    }
                }
                
                $message = '<div class="alert alert-success">Task deleted successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error deleting task.</div>';
            }
        }
    }
}

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($date_filter)) {
    $where_conditions[] = 'DATE(t.created_at) = ?';
    $params[] = $date_filter;
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'e.id = ?';
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = 't.status = ?';
    $params[] = $status_filter;
}

if (!empty($site_filter)) {
    $where_conditions[] = 't.site_id = ?';
    $params[] = $site_filter;
}

try {
    // Get tasks with employee and site info (including admin-created tasks)
    $sql = "
        SELECT t.*,
               CASE
                   WHEN t.admin_created = 1 THEN 'Admin Assigned'
                   ELSE COALESCE(e.name, 'Unknown Employee')
               END as employee_name,
               CASE
                   WHEN t.admin_created = 1 THEN 'ADMIN'
                   ELSE COALESCE(e.employee_code, 'N/A')
               END as employee_code,
               COALESCE(s.name, 'No Site') as site_name,
               CASE
                   WHEN t.admin_created = 1 THEN NULL
                   ELSE TIMESTAMPDIFF(MINUTE, t.start_time, COALESCE(t.end_time, NOW()))
               END as duration_minutes,
               CASE
                   WHEN t.admin_created = 1 THEN 'admin'
                   ELSE 'field'
               END as task_type,
               CASE
                   WHEN t.admin_created = 1 THEN
                       (SELECT COUNT(*) FROM task_assignments ta WHERE ta.task_id = t.id)
                   ELSE 1
               END as assignment_count,
               CASE
                   WHEN t.admin_created = 1 THEN
                       (SELECT COUNT(*) FROM task_assignments ta WHERE ta.task_id = t.id AND ta.status = 'completed')
                   ELSE (CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END)
               END as completed_count,
               ab.username as assigned_by_username
        FROM tasks t
        LEFT JOIN employees e ON t.employee_id = e.id
        LEFT JOIN sites s ON t.site_id = s.id
        LEFT JOIN users ab ON t.assigned_by = ab.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY t.created_at DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for filter
    $stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sites for filter
    $stmt = $pdo->query("SELECT id, name FROM sites WHERE status = 'active' ORDER BY name");
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = '<div class="alert alert-error">Error fetching tasks data.</div>';
    $tasks = [];
    $employees = [];
    $sites = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/logo.png">
    <link rel="shortcut icon" href="../../assets/images/logo.png">
    <link rel="apple-touch-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="wrapper">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                
                
                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                            <div>
                                <label for="date">Date:</label>
                                <input type="date" id="date" name="date" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            
                            <div>
                                <label for="employee">Employee:</label>
                                <select id="employee" name="employee" class="form-control">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" 
                                                <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="status">Status:</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="site">Site:</label>
                                <select id="site" name="site" class="form-control">
                                    <option value="">All Sites</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['id']; ?>" 
                                                <?php echo $site_filter == $site['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($site['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Tasks Overview</h3>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <span class="badge badge-primary">Total: <?php echo count($tasks); ?></span>
                                <span class="badge badge-warning">Active: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'active')); ?></span>
                                <span class="badge badge-success">Completed: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'completed')); ?></span>
                                <span class="badge badge-danger">Cancelled: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'cancelled')); ?></span>
                            </div>
                            <?php if (hasAdminPermission('tasks.manage')): ?>
                            <a href="assign.php" class="btn btn-primary" style="padding: 0.5rem 1rem; text-decoration: none;">
                                <i class="fas fa-plus"></i> Assign Task
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-tasks fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Tasks Found</h3>
                                <p>No tasks match the selected filters.</p>
                            </div>
                        <?php else: ?>
                            
                            <?php if (hasPermission('superadmin')): ?>
                            <!-- Bulk Actions -->
                            <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; align-items: center;">
                                <button type="button" id="bulkDeleteBtn" onclick="confirmBulkDelete()" 
                                        class="btn" style="background: #dc3545; color: white; padding: 0.5rem 1rem; display: none;">
                                    <i class="fas fa-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                                </button>
                                <button type="button" onclick="clearSelection()" 
                                        class="btn" style="background: #6c757d; color: white; padding: 0.5rem 1rem; display: none;" id="clearBtn">
                                    <i class="fas fa-times"></i> Clear Selection
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <?php if (hasPermission('superadmin')): ?>
                                            <th style="width: 40px; text-align: center;">
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" 
                                                       style="transform: scale(1.2);">
                                            </th>
                                            <?php endif; ?>
                                            <th>Employee/Assigned</th>
                                            <th>Task</th>
                                            <th>Site</th>
                                            <th>Created/Start Time</th>
                                            <th>Duration/Assignments</th>
                                            <th>Status</th>
                                            <th>Start Location</th>
                                            <th>End Location</th>
                                            <th>Start Image</th>
                                            <th>Complete Image</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <?php if (hasPermission('superadmin')): ?>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="selected_tasks[]" value="<?php echo $task['id']; ?>" 
                                                       class="task-checkbox" onchange="updateDeleteButton()"
                                                       style="transform: scale(1.2);">
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <strong><?php echo htmlspecialchars($task['employee_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($task['employee_name']); ?></small>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <?php if ($task['assigned_by_username']): ?>
                                                        <br><small style="color: #666;">By: <?php echo htmlspecialchars($task['assigned_by_username']); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px;">
                                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                                    <?php if ($task['description']): ?>
                                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?><?php echo strlen($task['description']) > 50 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($task['task_type'] === 'admin' && $task['priority']): ?>
                                                        <br><small style="color: #666;">Priority: <?php echo ucfirst($task['priority']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($task['task_type'] === 'admin' && $task['due_date']): ?>
                                                        <br><small style="color: #666;">Due: <?php echo formatDate($task['due_date'], 'M d, Y'); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['site_name']); ?></td>
                                            <td>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <?php echo formatDate($task['created_at'], 'M d, Y'); ?><br>
                                                    <small><?php echo formatDate($task['created_at'], 'g:i A'); ?></small>
                                                <?php else: ?>
                                                    <?php echo formatDate($task['start_time'], 'M d, Y'); ?><br>
                                                    <small><?php echo formatDate($task['start_time'], 'g:i A'); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <strong>
                                                        <a href="#" onclick="showAssignments(<?php echo $task['id']; ?>); return false;"
                                                           style="color: #007bff; text-decoration: none; cursor: pointer;">
                                                            <?php echo $task['assignment_count']; ?> assigned
                                                        </a>
                                                    </strong><br>
                                                    <small><?php echo $task['completed_count']; ?> completed</small>
                                                <?php else: ?>
                                                    <?php
                                                    $hours = floor($task['duration_minutes'] / 60);
                                                    $minutes = $task['duration_minutes'] % 60;
                                                    echo $hours > 0 ? $hours . 'h ' : '';
                                                    echo $minutes . 'm';
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $task['status'] === 'completed' ? 'success' : 
                                                        ($task['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                            </td>
                                            
                                            <!-- START LOCATION COLUMN -->
                                            <td>
                                                <?php if ($task['latitude'] && $task['longitude']): ?>
                                                    <a href="#" 
                                                       onclick="showLocationModal(<?php echo $task['latitude']; ?>, <?php echo $task['longitude']; ?>, '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo formatDate($task['start_time'], 'M d, Y g:i A'); ?>')" 
                                                       class="btn" style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-map-marker-alt"></i> View on Map
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #666; font-size: 0.8rem;">No location data</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- END LOCATION COLUMN -->
                                            <td>
                                                <?php if ($task['completion_latitude'] && $task['completion_longitude']): ?>
                                                    <a href="#" 
                                                       onclick="showLocationModal(<?php echo $task['completion_latitude']; ?>, <?php echo $task['completion_longitude']; ?>, '<?php echo htmlspecialchars($task['title']); ?> - End', '<?php echo $task['end_time'] ? formatDate($task['end_time'], 'M d, Y g:i A') : 'Task completed'; ?>')" 
                                                       class="btn" style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-map-marker-alt"></i> View on Map
                                                    </a>
                                                <?php else: ?>
                                                    <?php if ($task['status'] === 'completed'): ?>
                                                        <span style="color: #666; font-size: 0.8rem;">No location data</span>
                                                    <?php else: ?>
                                                        <span style="color: #666; font-size: 0.8rem;">Task not completed</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- START IMAGE COLUMN -->
                                            <td>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <span style="color: #666; font-size: 0.8rem;">Admin Task</span>
                                                <?php else: ?>
                                                    <?php if ($task['task_image']): ?>
                                                        <a href="../../assets/images/uploads/tasks/<?php echo $task['task_image']; ?>"
                                                           target="_blank" class="btn" style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                            <i class="fas fa-image"></i> Start
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="color: #666; font-size: 0.8rem;">No start image</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>

                                            <!-- COMPLETE IMAGE COLUMN -->
                                            <td>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <span style="color: #666; font-size: 0.8rem;">Admin Task</span>
                                                <?php else: ?>
                                                    <?php if ($task['completion_image']): ?>
                                                        <a href="../../assets/images/uploads/tasks/<?php echo $task['completion_image']; ?>"
                                                           target="_blank" class="btn" style="background: #007bff; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                            <i class="fas fa-image"></i> Complete
                                                        </a>
                                                    <?php else: ?>
                                                        <?php if ($task['status'] === 'completed'): ?>
                                                            <span style="color: #666; font-size: 0.8rem;">No complete image</span>
                                                        <?php else: ?>
                                                            <span style="color: #666; font-size: 0.8rem;">Task not completed</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <?php if ($task['task_type'] === 'admin'): ?>
                                                    <!-- Admin Task Actions -->
                                                    <?php if (hasPermission('superadmin')): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn"
                                                                style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                onclick="return confirm('Are you sure you want to delete this admin task? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                        <span style="color: #666; font-size: 0.8rem;">Admin Task</span>
                                                    <?php endif; ?>
                                                <?php elseif ($task['status'] === 'active' && hasPermission('supervisor')): ?>
                                                    <div style="display: flex; gap: 0.25rem;">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn" 
                                                                    style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                    onclick="return confirm('Mark this task as completed?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <button type="submit" class="btn" 
                                                                    style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                    onclick="return confirm('Cancel this task?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- DELETE BUTTON FOR ACTIVE TASKS (SUPERADMIN ONLY) -->
                                                        <?php if (hasPermission('superadmin')): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn" 
                                                                    style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                    onclick="return confirm('Are you sure you want to delete this task? This action cannot be undone.')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- For non-active tasks, show only delete option for superadmin -->
                                                    <?php if (hasPermission('superadmin')): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn" 
                                                                style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                onclick="return confirm('Are you sure you want to delete this task? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                        <span style="color: #666; font-size: 0.8rem;">No actions available</span>
                                                    <?php endif; ?>
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

    <!-- Location Modal -->
    <div id="locationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 id="modalTitle">Task Location</h3>
                <button onclick="closeLocationModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="mapContainer" style="height: 400px; border: 1px solid #ddd; border-radius: 8px;"></div>
        </div>
    </div>

    <!-- Assignments Modal -->
    <div id="assignmentsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 id="assignmentsModalTitle">Task Assignments</h3>
                <button onclick="closeAssignmentsModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="assignmentsContent" style="max-height: 400px; overflow-y: auto;">
                Loading...
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
    let map;
    
    function showLocationModal(lat, lng, title, time) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('locationModal').style.display = 'block';
        
        setTimeout(() => {
            if (map) {
                map.remove();
            }
            
            map = L.map('mapContainer').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            L.marker([lat, lng])
                .addTo(map)
                .bindPopup(`<b>${title}</b><br>${time}`)
                .openPopup();
        }, 100);
    }
    
    function closeLocationModal() {
        document.getElementById('locationModal').style.display = 'none';
        if (map) {
            map.remove();
            map = null;
        }
    }

    // Checkbox functionality for bulk delete
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.task-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        
        updateDeleteButton();
    }

    function updateDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.task-checkbox:checked');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const clearBtn = document.getElementById('clearBtn');
        const selectedCount = document.getElementById('selectedCount');
        
        const count = checkedBoxes.length;
        
        if (count > 0) {
            bulkDeleteBtn.style.display = 'inline-block';
            clearBtn.style.display = 'inline-block';
            selectedCount.textContent = count;
        } else {
            bulkDeleteBtn.style.display = 'none';
            clearBtn.style.display = 'none';
        }
        
        // Update select all checkbox
        const allCheckboxes = document.querySelectorAll('.task-checkbox');
        const selectAll = document.getElementById('selectAll');
        
        if (allCheckboxes.length > 0) {
            if (count === 0) {
                selectAll.indeterminate = false;
                selectAll.checked = false;
            } else if (count === allCheckboxes.length) {
                selectAll.indeterminate = false;
                selectAll.checked = true;
            } else {
                selectAll.indeterminate = true;
                selectAll.checked = false;
            }
        }
    }

    function clearSelection() {
        const checkboxes = document.querySelectorAll('.task-checkbox');
        const selectAll = document.getElementById('selectAll');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAll.checked = false;
        selectAll.indeterminate = false;
        
        updateDeleteButton();
    }

    function confirmBulkDelete() {
        const checkedBoxes = document.querySelectorAll('.task-checkbox:checked');
        const count = checkedBoxes.length;
        
        if (count === 0) {
            alert('Please select tasks to delete.');
            return;
        }
        
        const message = `Are you sure you want to delete ${count} task(s)? This action cannot be undone.`;
        
        if (confirm(message)) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_delete';
            form.appendChild(actionInput);
            
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_tasks[]';
                hiddenInput.value = checkbox.value;
                form.appendChild(hiddenInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateDeleteButton();
    });

    // Close modal when clicking outside
    document.getElementById('locationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLocationModal();
        }
    });

    document.getElementById('assignmentsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignmentsModal();
        }
    });

    // Show task assignments
    function showAssignments(taskId) {
        document.getElementById('assignmentsModal').style.display = 'block';
        document.getElementById('assignmentsContent').innerHTML = 'Loading...';

        // Fetch task assignments via AJAX
        fetch('get_task_assignments.php?task_id=' + taskId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<h4 style="margin-bottom: 1rem;">Task: ' + data.task.title + '</h4>';

                    if (data.assignments.length > 0) {
                        html += '<table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">';
                        html += '<thead><tr style="border-bottom: 2px solid #dee2e6; background: #f8f9fa;"><th style="padding: 0.75rem; text-align: left;">Employee</th><th style="padding: 0.75rem; text-align: left;">Status</th><th style="padding: 0.75rem; text-align: left;">Progress</th></tr></thead>';
                        html += '<tbody>';

                        data.assignments.forEach(assignment => {
                            const statusColor = assignment.status === 'completed' ? '#28a745' :
                                              assignment.status === 'in_progress' ? '#ffc107' :
                                              assignment.status === 'cancelled' ? '#dc3545' : '#6c757d';

                            html += `
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 0.75rem;">
                                        <div>
                                            <strong>${assignment.display_name}</strong>
                                            <br><small style="color: #666;">${assignment.username}</small>
                                            ${assignment.employee_code !== 'N/A' ? '<br><small style="color: #666;">' + assignment.employee_code + '</small>' : ''}
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <span style="background: ${statusColor}; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500;">
                                            ${assignment.status.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <div style="font-size: 0.8rem; color: #666;">
                                            ${assignment.started_at ? 'Started: ' + new Date(assignment.started_at).toLocaleDateString() : 'Not started'}
                                            ${assignment.completed_at ? '<br>Completed: ' + new Date(assignment.completed_at).toLocaleDateString() : ''}
                                            ${assignment.notes ? '<br><em>' + assignment.notes.substring(0, 30) + (assignment.notes.length > 30 ? '...' : '') + '</em>' : ''}
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        html += '</tbody></table>';
                    } else {
                        html += '<p style="color: #666; text-align: center; padding: 2rem;">No assignments found for this task.</p>';
                    }

                    document.getElementById('assignmentsContent').innerHTML = html;
                } else {
                    document.getElementById('assignmentsContent').innerHTML = '<p style="color: #dc3545; text-align: center; padding: 2rem;">Error loading assignments: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('assignmentsContent').innerHTML = '<p style="color: #dc3545; text-align: center; padding: 2rem;">Error loading assignments. Please try again.</p>';
            });
    }

    function closeAssignmentsModal() {
        document.getElementById('assignmentsModal').style.display = 'none';
    }
    </script>
</body>
</html>