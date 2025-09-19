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

$pageTitle = 'Sites Management';
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_site'])) {
        $name = sanitize($_POST['name']);
        $address = sanitize($_POST['address']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sites (name, address, latitude, longitude) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $address, $latitude, $longitude]);
                $message = '<div class="alert alert-success">Site added successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error adding site.</div>';
            }
        } else {
            $message = '<div class="alert alert-error">Site name is required.</div>';
        }
    }
    
    if (isset($_POST['edit_site'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $address = sanitize($_POST['address']);
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE sites SET name = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?");
                $stmt->execute([$name, $address, $latitude, $longitude, $id]);
                $message = '<div class="alert alert-success">Site updated successfully!</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-error">Error updating site.</div>';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE sites SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = '<div class="alert alert-success">Site deactivated successfully!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Error deactivating site.</div>';
    }
}

// Get all sites
try {
    $stmt = $pdo->query("
        SELECT s.*, 
               COUNT(e.id) as employee_count
        FROM sites s
        LEFT JOIN employees e ON s.id = e.site_id AND e.status = 'active'
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY s.name
    ");
    $sites = $stmt->fetchAll();
} catch (PDOException $e) {
    $sites = [];
    $message = '<div class="alert alert-error">Error fetching sites.</div>';
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
                    <a href="sites.php" class="btn btn-primary" style="margin-left: 0.5rem;">
                        <i class="fas fa-map-marker-alt"></i> Sites
                    </a>
                    <a href="shifts.php" class="btn" style="background: #6c757d; color: white; margin-left: 0.5rem;">
                        <i class="fas fa-clock"></i> Shifts
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                    <!-- Add Site Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Add Site</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="name">Site Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="latitude">Latitude</label>
                                    <input type="number" id="latitude" name="latitude" class="form-control" step="any" placeholder="e.g., 28.6139391">
                                </div>
                                
                                <div class="form-group">
                                    <label for="longitude">Longitude</label>
                                    <input type="number" id="longitude" name="longitude" class="form-control" step="any" placeholder="e.g., 77.2090212">
                                </div>
                                
                                <button type="submit" name="add_site" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Site
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Sites List -->
                    <div class="card">
                        <div class="card-header">
                            <h3>All Sites</h3>
                            <span class="badge badge-success"><?php echo count($sites); ?> Active</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sites)): ?>
                                <div style="text-align: center; padding: 2rem; color: #666;">
                                    <i class="fas fa-map-marker-alt fa-2x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No sites found. Create your first site.</p>
                                </div>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Coordinates</th>
                                            <th>Employees</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sites as $site): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($site['name']); ?></strong></td>
                                            <td>
                                                <div style="max-width: 200px; word-wrap: break-word;">
                                                    <?php echo htmlspecialchars($site['address'] ?: 'No address'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($site['latitude'] && $site['longitude']): ?>
                                                    <small>
                                                        Lat: <?php echo number_format($site['latitude'], 6); ?><br>
                                                        Lng: <?php echo number_format($site['longitude'], 6); ?>
                                                    </small>
                                                    <br>
                                                    <a href="https://maps.google.com/?q=<?php echo $site['latitude']; ?>,<?php echo $site['longitude']; ?>" 
                                                       target="_blank" style="font-size: 0.8rem; color: #007bff;">
                                                        <i class="fas fa-map"></i> View Map
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #666;">No coordinates</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $site['employee_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $site['employee_count']; ?> employees
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($site['created_at'], 'M d, Y'); ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <button type="button" class="btn" style="background: #f39c12; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                            onclick="editSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>', '<?php echo htmlspecialchars($site['address']); ?>', <?php echo $site['latitude'] ?: 'null'; ?>, <?php echo $site['longitude'] ?: 'null'; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($site['employee_count'] == 0): ?>
                                                    <a href="?delete=<?php echo $site['id']; ?>" 
                                                       class="btn" style="background: #e74c3c; color: white; padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                       onclick="return confirm('Are you sure you want to delete this site?')">
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
            <h3 style="margin-bottom: 1rem;">Edit Site</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-group">
                    <label for="edit_name">Site Name *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <textarea id="edit_address" name="address" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_latitude">Latitude</label>
                    <input type="number" id="edit_latitude" name="latitude" class="form-control" step="any" placeholder="e.g., 28.6139391">
                </div>
                
                <div class="form-group">
                    <label for="edit_longitude">Longitude</label>
                    <input type="number" id="edit_longitude" name="longitude" class="form-control" step="any" placeholder="e.g., 77.2090212">
                </div>
                
                <div style="text-align: right; gap: 1rem; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_site" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Site
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editSite(id, name, address, latitude, longitude) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_latitude').value = latitude || '';
            document.getElementById('edit_longitude').value = longitude || '';
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

</body>
</html>