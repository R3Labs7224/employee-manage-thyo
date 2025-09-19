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

$pageTitle = 'System Settings';
$message = '';

// Get statistics for settings overview
try {
    // Get departments count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE status = 'active'");
    $departmentsCount = $stmt->fetch()['count'];
    
    // Get sites count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sites WHERE status = 'active'");
    $sitesCount = $stmt->fetch()['count'];
    
    // Get shifts count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM shifts WHERE status = 'active'");
    $shiftsCount = $stmt->fetch()['count'];
    
    // Get employees assigned to departments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE department_id IS NOT NULL AND status = 'active'");
    $employeesWithDept = $stmt->fetch()['count'];
    
    // Get employees assigned to sites
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE site_id IS NOT NULL AND status = 'active'");
    $employeesWithSite = $stmt->fetch()['count'];
    
    // Get employees assigned to shifts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE shift_id IS NOT NULL AND status = 'active'");
    $employeesWithShift = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $departmentsCount = $sitesCount = $shiftsCount = 0;
    $employeesWithDept = $employeesWithSite = $employeesWithShift = 0;
    $message = '<div class="alert alert-error">Error fetching settings data.</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease;
        }

        .overview-card:hover {
            transform: translateY(-2px);
        }

        .overview-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--orange-600);
        }

        .overview-card h4 {
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .overview-card .count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--orange-600);
            margin-bottom: 0.5rem;
        }

        .overview-card .description {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .settings-card:hover {
            transform: translateY(-4px);
        }

        .settings-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .settings-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--orange-400), var(--orange-600));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .settings-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .settings-card h3 {
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .settings-description {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .settings-stats {
            padding: 1.5rem;
            background: var(--gray-50);
        }

        .stat-row {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .stat-row:last-child {
            margin-bottom: 0;
        }

        .stat-label {
            font-weight: 500;
            color: var(--gray-700);
        }

        .stat-value {
            font-weight: 700;
            color: var(--orange-600);
        }

        .settings-actions {
            padding: 1.5rem;
            text-align: center;
        }

        .settings-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--orange-400), var(--orange-600));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .settings-btn:hover {
            background: linear-gradient(135deg, var(--orange-600), var(--orange-400));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
        }

        .quick-setup {
            background: linear-gradient(135deg, var(--orange-400) 0%, var(--orange-600) 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .setup-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .setup-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .setup-step.complete {
            background: rgba(16, 185, 129, 0.2);
        }

        .setup-step.pending {
            background: rgba(245, 158, 11, 0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <h1>System Settings</h1>
                    <p>Configure your employee management system</p>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Quick Setup Progress -->
                <div class="quick-setup">
                    <h3 style="margin-bottom: 1rem;">Quick Setup Progress</h3>
                    <div class="setup-progress">
                        <div class="setup-step <?php echo $departmentsCount > 0 ? 'complete' : 'pending'; ?>">
                            <i class="fas fa-building"></i>
                            <span>Departments: <?php echo $departmentsCount; ?></span>
                        </div>
                        <div class="setup-step <?php echo $sitesCount > 0 ? 'complete' : 'pending'; ?>">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Sites: <?php echo $sitesCount; ?></span>
                        </div>
                        <div class="setup-step <?php echo $shiftsCount > 0 ? 'complete' : 'pending'; ?>">
                            <i class="fas fa-clock"></i>
                            <span>Shifts: <?php echo $shiftsCount; ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem;">Recommendations</h4>
                        <div style="display: grid; gap: 0.75rem;">
                            <?php if ($departmentsCount == 0): ?>
                            <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.2); border-radius: 8px;">
                                <strong>Setup Departments:</strong> Create departments to organize your employees effectively.
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($sitesCount == 0): ?>
                            <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.2); border-radius: 8px;">
                                <strong>Add Work Sites:</strong> Configure work locations for accurate attendance tracking.
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($shiftsCount == 0): ?>
                            <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.2); border-radius: 8px;">
                                <strong>Define Shifts:</strong> Set up work schedules for better time management.
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($departmentsCount > 0 && $sitesCount > 0 && $shiftsCount > 0): ?>
                            <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.2); border-radius: 8px;">
                                <strong>System Ready:</strong> All basic configurations are complete. You can now add employees and start managing your workforce.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Overview Cards -->
                <div class="settings-overview">
                    <div class="overview-card">
                        <i class="fas fa-building"></i>
                        <h4>Departments</h4>
                        <div class="count"><?php echo $departmentsCount; ?></div>
                        <p class="description">Active departments</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Work Sites</h4>
                        <div class="count"><?php echo $sitesCount; ?></div>
                        <p class="description">Active work sites</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-clock"></i>
                        <h4>Work Shifts</h4>
                        <div class="count"><?php echo $shiftsCount; ?></div>
                        <p class="description">Active work shifts</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-users"></i>
                        <h4>Configured Employees</h4>
                        <div class="count"><?php echo max($employeesWithDept, $employeesWithSite, $employeesWithShift); ?></div>
                        <p class="description">Employees with assignments</p>
                    </div>
                </div>
                
                <!-- Settings Management Cards -->
                <div class="settings-grid">
                    <!-- Departments Management -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <h3>Departments Management</h3>
                            <p class="settings-description">
                                Organize your workforce by creating and managing departments. 
                                Assign employees to specific departments for better organization and reporting.
                            </p>
                        </div>
                        
                        <div class="settings-stats">
                            <div class="stat-row">
                                <span class="stat-label">Active Departments:</span>
                                <span class="stat-value"><?php echo $departmentsCount; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Employees Assigned:</span>
                                <span class="stat-value"><?php echo $employeesWithDept; ?></span>
                            </div>
                        </div>
                        
                        <div class="settings-actions">
                            <a href="departments.php" class="settings-btn">
                                <i class="fas fa-cog"></i>
                                Manage Departments
                            </a>
                        </div>
                    </div>
                    
                    <!-- Sites Management -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon" style="background: linear-gradient(135deg, var(--orange-400), var(--orange-600));">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h3>Work Sites Management</h3>
                            <p class="settings-description">
                                Configure work locations and sites where employees will check in. 
                                Set GPS coordinates for accurate location tracking and attendance verification.
                            </p>
                        </div>
                        
                        <div class="settings-stats">
                            <div class="stat-row">
                                <span class="stat-label">Active Work Sites:</span>
                                <span class="stat-value"><?php echo $sitesCount; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Employees Assigned:</span>
                                <span class="stat-value"><?php echo $employeesWithSite; ?></span>
                            </div>
                        </div>
                        
                        <div class="settings-actions">
                            <a href="sites.php" class="settings-btn">
                                <i class="fas fa-map-marker-alt"></i>
                                Manage Work Sites
                            </a>
                        </div>
                    </div>
                    
                    <!-- Shifts Management -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon" style="background: linear-gradient(135deg, var(--orange-400), var(--orange-600));">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3>Work Shifts Management</h3>
                            <p class="settings-description">
                                Define work schedules and shift timings for your employees. 
                                Create flexible shift patterns to match your business operations.
                            </p>
                        </div>
                        
                        <div class="settings-stats">
                            <div class="stat-row">
                                <span class="stat-label">Active Shifts:</span>
                                <span class="stat-value"><?php echo $shiftsCount; ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Employees Assigned:</span>
                                <span class="stat-value"><?php echo $employeesWithShift; ?></span>
                            </div>
                        </div>
                        
                        <div class="settings-actions">
                            <a href="shifts.php" class="settings-btn">
                                <i class="fas fa-clock"></i>
                                Manage Shifts
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Panel -->
                <div style="margin-top: 2rem; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 1.5rem; color: var(--gray-900);">Quick Actions</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        
                        <a href="departments.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, var(--orange-400), var(--orange-600)); display: flex; align-items: center;">
                            <i class="fas fa-building"></i>
                            <div style="margin-left: 1rem; text-align: left;">
                                <div style="font-weight: 700; font-size: 1.1rem;">Create Department</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Organize your workforce</div>
                            </div>
                        </a>
                        
                        <a href="sites.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, var(--orange-400), var(--orange-600)); display: flex; align-items: center;">
                            <i class="fas fa-map-marker-alt"></i>
                            <div style="margin-left: 1rem; text-align: left;">
                                <div style="font-weight: 700; font-size: 1.1rem;">Create Work Site</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Configure work locations</div>
                            </div>
                        </a>
                        
                        <a href="shifts.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, var(--orange-400), var(--orange-600)); display: flex; align-items: center;">
                            <i class="fas fa-clock"></i>
                            <div style="margin-left: 1rem; text-align: left;">
                                <div style="font-weight: 700; font-size: 1.1rem;">Create Shift</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Define work schedules</div>
                            </div>
                        </a>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>