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
    // Get tasks with employee and site info
    $sql = "
        SELECT t.*, 
               e.name as employee_name,
               e.employee_code,
               s.name as site_name,
               TIMESTAMPDIFF(MINUTE, t.start_time, COALESCE(t.end_time, NOW())) as duration_minutes
        FROM tasks t
        JOIN employees e ON t.employee_id = e.id
        JOIN sites s ON t.site_id = s.id
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
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="badge badge-primary">Total: <?php echo count($tasks); ?></span>
                            <span class="badge badge-warning">Active: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'active')); ?></span>
                            <span class="badge badge-success">Completed: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'completed')); ?></span>
                            <span class="badge badge-danger">Cancelled: <?php echo count(array_filter($tasks, fn($t) => $t['status'] === 'cancelled')); ?></span>
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
                                            <th>Employee</th>
                                            <th>Task</th>
                                            <th>Site</th>
                                            <th>Start Time</th>
                                            <th>Duration</th>
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
                                            </td>
                                            <td>
                                                <div style="max-width: 200px;">
                                                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                                    <?php if ($task['description']): ?>
                                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?><?php echo strlen($task['description']) > 50 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['site_name']); ?></td>
                                            <td>
                                                <?php echo formatDate($task['start_time'], 'M d, Y'); ?><br>
                                                <small><?php echo formatDate($task['start_time'], 'g:i A'); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $hours = floor($task['duration_minutes'] / 60);
                                                $minutes = $task['duration_minutes'] % 60;
                                                echo $hours > 0 ? $hours . 'h ' : '';
                                                echo $minutes . 'm';
                                                ?>
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
                                                <?php if ($task['task_image']): ?>
                                                    <a href="../../assets/images/uploads/tasks/<?php echo $task['task_image']; ?>" 
                                                       target="_blank" class="btn" style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                        <i class="fas fa-image"></i> Start
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #666; font-size: 0.8rem;">No start image</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- COMPLETE IMAGE COLUMN -->
                                            <td>
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
                                            </td>
                                            
                                            <td>
                                                <?php if ($task['status'] === 'active' && hasPermission('supervisor')): ?>
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
    </script>
</body>
</html>