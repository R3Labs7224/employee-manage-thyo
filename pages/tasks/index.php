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

// Handle task actions (complete/cancel)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $task_id = (int)$_POST['task_id'];
    $action = $_POST['action'];
    
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
                        <form method="GET" action="index.php">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                                <div>
                                    <label>Date:</label>
                                    <input type="date" name="date" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                
                                <div>
                                    <label>Employee:</label>
                                    <select name="employee" class="form-control">
                                        <option value="">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>" 
                                                    <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label>Site:</label>
                                    <select name="site" class="form-control">
                                        <option value="">All Sites</option>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?php echo $site['id']; ?>" 
                                                    <?php echo $site_filter == $site['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($site['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label>Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="index.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tasks List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Tasks Overview</h3>
                        <div>
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
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
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
                                                        ($task['status'] === 'cancelled' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($task['status']); ?>
                                                </span>
                                                <?php if ($task['end_time']): ?>
                                                    <br><small style="color: #666;"><?php echo formatDate($task['end_time'], 'g:i A'); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- START LOCATION COLUMN -->
                                            <td>
                                                <?php if ($task['latitude'] && $task['longitude']): ?>
                                                    <a href="#" onclick="showLocationModal('start', <?php echo $task['latitude']; ?>, <?php echo $task['longitude']; ?>, '<?php echo htmlspecialchars($task['employee_name']); ?>', '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo formatDate($task['start_time'], 'M d, Y g:i A'); ?>')" 
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
                                                    <a href="#" onclick="showLocationModal('end', <?php echo $task['completion_latitude']; ?>, <?php echo $task['completion_longitude']; ?>, '<?php echo htmlspecialchars($task['employee_name']); ?>', '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo $task['end_time'] ? formatDate($task['end_time'], 'M d, Y g:i A') : 'Task completed'; ?>')" 
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
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #666; font-size: 0.8rem;">-</span>
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
    <div id="locationModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="modalTitle">Task Location on Map</h3>
                <span class="close" onclick="closeLocationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalInfo" style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <strong id="employeeName"></strong><br>
                    <span id="taskTitle"></span><br>
                    <span id="locationType"></span>: <span id="locationTime"></span>
                </div>
                <div id="map" style="height: 400px; width: 100%; border-radius: 4px;"></div>
            </div>
        </div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        let map = null;
        let marker = null;

        function showLocationModal(type, latitude, longitude, employeeName, taskTitle, locationTime) {
            // Set modal content
            document.getElementById('employeeName').textContent = employeeName;
            document.getElementById('taskTitle').textContent = 'Task: ' + taskTitle;
            document.getElementById('locationType').textContent = type === 'start' ? 'Start Location' : 'End Location';
            document.getElementById('locationTime').textContent = locationTime;
            document.getElementById('modalTitle').textContent = (type === 'start' ? 'Task Start' : 'Task End') + ' Location';
            
            // Show modal
            document.getElementById('locationModal').style.display = 'block';
            
            // Initialize map after modal is visible
            setTimeout(() => {
                if (map) {
                    map.remove();
                }
                
                // Create map
                map = L.map('map').setView([latitude, longitude], 15);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Add marker
                const iconColor = type === 'start' ? 'green' : 'red';
                const customIcon = L.divIcon({
                    html: `<i class="fas fa-map-marker-alt" style="color: ${iconColor}; font-size: 24px;"></i>`,
                    iconSize: [24, 24],
                    iconAnchor: [12, 24],
                    className: 'custom-marker'
                });
                
                marker = L.marker([latitude, longitude], { icon: customIcon }).addTo(map);
                
                // Add popup
                const popupContent = `
                    <div style="text-align: center;">
                        <strong>${employeeName}</strong><br>
                        <strong>${taskTitle}</strong><br>
                        ${type === 'start' ? 'Task Started' : 'Task Completed'}<br>
                        ${locationTime}<br>
                        <small>Lat: ${latitude.toFixed(6)}, Lng: ${longitude.toFixed(6)}</small>
                    </div>
                `;
                marker.bindPopup(popupContent).openPopup();
                
                // Invalidate size to ensure proper rendering
                map.invalidateSize();
            }, 100);
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
            if (map) {
                map.remove();
                map = null;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('locationModal');
            if (event.target === modal) {
                closeLocationModal();
            }
        }
    </script>

    <?php include '../../components/footer.php'; ?>
</body>
</html>
