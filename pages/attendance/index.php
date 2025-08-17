<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

$pageTitle = 'Attendance Management';
$message = '';

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $attendance_id = (int)$_POST['attendance_id'];
    $action = $_POST['action'];
    $user = getUser();
    
    if (in_array($action, ['approve', 'reject'])) {
        try {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, approved_by = ?, approval_date = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $user['id'], $attendance_id]);
            $message = '<div class="alert alert-success">Attendance ' . $status . ' successfully!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Error updating attendance status.</div>';
        }
    }
}

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($date_filter)) {
    $where_conditions[] = 'a.date = ?';
    $params[] = $date_filter;
}

if (!empty($employee_filter)) {
    $where_conditions[] = 'e.id = ?';
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = 'a.status = ?';
    $params[] = $status_filter;
}

try {
    // Get attendance records
    $sql = "
        SELECT a.*, 
               e.name as employee_name,
               e.employee_code,
               s.name as site_name,
               u.username as approved_by_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        JOIN sites s ON a.site_id = s.id
        LEFT JOIN users u ON a.approved_by = u.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY a.date DESC, a.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Get employees for filter dropdown
    $employees_stmt = $pdo->query("SELECT id, name, employee_code FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $employees_stmt->fetchAll();
    
} catch (PDOException $e) {
    $attendance_records = [];
    $employees = [];
    $message = '<div class="alert alert-error">Error fetching attendance records.</div>';
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
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <?php echo $message; ?>
                
                <!-- Filters -->
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <form method="GET" action="">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="date">Date</label>
                                    <input type="date" id="date" name="date" class="form-control" 
                                           value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
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
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                
                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header">
                        <h3>Attendance Records</h3>
                        <div>
                            <span class="badge badge-warning">Pending: <?php echo count(array_filter($attendance_records, fn($r) => $r['status'] === 'pending')); ?></span>
                            <span class="badge badge-success">Approved: <?php echo count(array_filter($attendance_records, fn($r) => $r['status'] === 'approved')); ?></span>
                            <span class="badge badge-danger">Rejected: <?php echo count(array_filter($attendance_records, fn($r) => $r['status'] === 'rejected')); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_records)): ?>
                            <div style="text-align: center; padding: 3rem; color: #666;">
                                <i class="fas fa-calendar-times fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <h3>No Attendance Records Found</h3>
                                <p>No attendance records match the selected filters.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Date</th>
                                            <th>Site</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Hours</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($record['employee_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($record['employee_name']); ?></small>
                                            </td>
                                            <td><?php echo formatDate($record['date'], 'M d, Y'); ?></td>
                                            <td><?php echo htmlspecialchars($record['site_name']); ?></td>
                                            <td>
                                                <?php if ($record['check_in_time']): ?>
                                                    <?php echo formatDate($record['check_in_time'], 'g:i A'); ?>
                                                    <?php if ($record['check_in_selfie']): ?>
                                                        <br><a href="../../assets/images/uploads/attendance/<?php echo $record['check_in_selfie']; ?>" 
                                                               target="_blank" style="font-size: 0.8rem; color: #007bff;">
                                                            <i class="fas fa-camera"></i> View Selfie
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($record['check_in_latitude'] && $record['check_in_longitude']): ?>
                                                        <br><a href="#" onclick="showLocationModal('checkin', <?php echo $record['check_in_latitude']; ?>, <?php echo $record['check_in_longitude']; ?>, '<?php echo htmlspecialchars($record['employee_name']); ?>', '<?php echo formatDate($record['check_in_time'], 'M d, Y g:i A'); ?>')" 
                                                               style="font-size: 0.8rem; color: #28a745;">
                                                            <i class="fas fa-map-marker-alt"></i> View Location on Map
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #666;">Not checked in</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['check_out_time']): ?>
                                                    <?php echo formatDate($record['check_out_time'], 'g:i A'); ?>
                                                    <?php if ($record['check_out_selfie']): ?>
                                                        <br><a href="../../assets/images/uploads/attendance/<?php echo $record['check_out_selfie']; ?>" 
                                                               target="_blank" style="font-size: 0.8rem; color: #007bff;">
                                                            <i class="fas fa-camera"></i> View Selfie
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($record['check_out_latitude'] && $record['check_out_longitude']): ?>
                                                        <br><a href="#" onclick="showLocationModal('checkout', <?php echo $record['check_out_latitude']; ?>, <?php echo $record['check_out_longitude']; ?>, '<?php echo htmlspecialchars($record['employee_name']); ?>', '<?php echo formatDate($record['check_out_time'], 'M d, Y g:i A'); ?>')" 
                                                               style="font-size: 0.8rem; color: #28a745;">
                                                            <i class="fas fa-map-marker-alt"></i> View Location on Map
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: #666;">Not checked out</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['working_hours'] > 0): ?>
                                                    <?php echo number_format($record['working_hours'], 2); ?> hrs
                                                <?php else: ?>
                                                    <span style="color: #666;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $record['status'] === 'approved' ? 'success' : 
                                                        ($record['status'] === 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                                <?php if ($record['approved_by_name']): ?>
                                                    <br><small style="color: #666;">by <?php echo htmlspecialchars($record['approved_by_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['status'] === 'pending' && hasPermission('supervisor')): ?>
                                                    <div style="display: flex; gap: 0.25rem;">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn" 
                                                                    style="background: #28a745; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                    onclick="return confirm('Approve this attendance?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn" 
                                                                    style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                    onclick="return confirm('Reject this attendance?')">
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
                <h3 id="modalTitle">Location on Map</h3>
                <span class="close" onclick="closeLocationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalInfo" style="margin-bottom: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
                    <strong id="employeeName"></strong><br>
                    <span id="actionType"></span>: <span id="actionTime"></span>
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

        function showLocationModal(type, latitude, longitude, employeeName, actionTime) {
            // Set modal content
            document.getElementById('employeeName').textContent = employeeName;
            document.getElementById('actionType').textContent = type === 'checkin' ? 'Check In' : 'Check Out';
            document.getElementById('actionTime').textContent = actionTime;
            document.getElementById('modalTitle').textContent = (type === 'checkin' ? 'Check In' : 'Check Out') + ' Location';
            
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
                const iconColor = type === 'checkin' ? 'green' : 'red';
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
                        ${type === 'checkin' ? 'Check In' : 'Check Out'}<br>
                        ${actionTime}<br>
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
