<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'Dashboard';
$stats = getStats($pdo);

// Get recent attendance
$recentAttendance = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, e.name as employee_name, s.name as site_name 
        FROM attendance a 
        JOIN employees e ON a.employee_id = e.id 
        JOIN sites s ON a.site_id = s.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $recentAttendance = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error silently for now
}

// Get pending petty cash requests
$pendingRequests = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, e.name as employee_name 
        FROM petty_cash_requests p 
        JOIN employees e ON p.employee_id = e.id 
        WHERE p.status = 'pending' 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $pendingRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error silently for now
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/logo.png">
    <link rel="shortcut icon" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'components/header.php'; ?>
            
            <div class="content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['total_employees']; ?></h3>
                            <p>Total Employees</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['today_attendance']; ?></h3>
                            <p>Today's Attendance</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $stats['active_tasks']; ?></h3>
                            <p>Active Tasks</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <!-- Recent Attendance -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Attendance</h3>
                            <a href="pages/attendance/index.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentAttendance)): ?>
                                <p style="text-align: center; color: #666; padding: 2rem;">No attendance records found</p>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAttendance as $attendance): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attendance['employee_name']); ?></td>
                                            <td><?php echo formatDate($attendance['date'], 'M d, Y'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $attendance['status'] === 'approved' ? 'success' : 
                                                        ($attendance['status'] === 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($attendance['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pending Petty Cash Requests -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Pending Requests</h3>
                            <a href="pages/petty_cash/index.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendingRequests)): ?>
                                <p style="text-align: center; color: #666; padding: 2rem;">No pending requests</p>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingRequests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                            <td><?php echo formatCurrency($request['amount']); ?></td>
                                            <td><?php echo formatDate($request['request_date'], 'M d, Y'); ?></td>
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

<?php include 'components/footer.php'; ?>
