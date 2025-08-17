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
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/logo.png">
    <link rel="shortcut icon" href="../../assets/images/logo.png">
    <link rel="apple-touch-icon" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-xl);
            box-shadow: 0 8px 32px rgba(79, 70, 229, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .settings-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.15);
        }
        
        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-indigo), var(--accent-amber));
        }
        
        .settings-card-header {
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(245, 158, 11, 0.05));
        }
        
        .settings-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--primary-indigo), var(--accent-amber));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--white);
            animation: float 3s ease-in-out infinite;
        }
        
        .settings-card h3 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-900);
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .settings-description {
            color: var(--gray-600);
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .settings-stats {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .stat-row:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--primary-indigo);
            font-size: 1.1rem;
        }
        
        .settings-actions {
            padding: 1.5rem 2rem;
            background: rgba(249, 250, 251, 0.8);
            border-top: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .settings-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-indigo), var(--primary-indigo-dark));
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all var(--transition-normal);
        }
        
        .settings-btn:hover {
            background: linear-gradient(135deg, var(--primary-indigo-light), var(--primary-indigo));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }
        
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .overview-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.1);
            transition: transform 0.3s ease;
        }
        
        .overview-card:hover {
            transform: translateY(-4px);
        }
        
        .overview-card i {
            font-size: 2.5rem;
            color: var(--primary-indigo);
            margin-bottom: 1rem;
        }
        
        .overview-card h4 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .overview-card .count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-amber);
            margin-bottom: 0.5rem;
        }
        
        .overview-card .description {
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../components/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../components/header.php'; ?>
            
            <div class="content">
                <?php echo $message; ?>
                
                <!-- Settings Overview -->
                <div class="overview-grid">
                    <div class="overview-card">
                        <i class="fas fa-building"></i>
                        <h4>Departments</h4>
                        <div class="count"><?php echo $departmentsCount; ?></div>
                        <p class="description">Active departments</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Sites</h4>
                        <div class="count"><?php echo $sitesCount; ?></div>
                        <p class="description">Active work sites</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-clock"></i>
                        <h4>Shifts</h4>
                        <div class="count"><?php echo $shiftsCount; ?></div>
                        <p class="description">Active work shifts</p>
                    </div>
                    
                    <div class="overview-card">
                        <i class="fas fa-users"></i>
                        <h4>Assigned Employees</h4>
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
                            <h3>Departments</h3>
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
                            <div class="settings-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h3>Work Sites</h3>
                            <p class="settings-description">
                                Configure work locations and sites where employees will check in. 
                                Set GPS coordinates for accurate location tracking and attendance verification.
                            </p>
                        </div>
                        
                        <div class="settings-stats">
                            <div class="stat-row">
                                <span class="stat-label">Active Sites:</span>
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
                                Manage Sites
                            </a>
                        </div>
                    </div>
                    
                    <!-- Shifts Management -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3>Work Shifts</h3>
                            <p class="settings-description">
                                Define work schedules and shift timings for different employee groups. 
                                Configure start and end times to manage attendance and payroll calculations.
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
                
                <!-- Quick Settings Access -->
                <div class="card">
                    <div class="card-header">
                        <h3>Quick Settings Access</h3>
                        <span class="badge badge-success">System Configuration</span>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <a href="departments.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, #4f46e5, #3730a3);">
                                <i class="fas fa-building"></i>
                                <div style="margin-left: 1rem; text-align: left;">
                                    <div style="font-weight: 700; font-size: 1.1rem;">Departments</div>
                                    <div style="font-size: 0.9rem; opacity: 0.9;">Manage organizational structure</div>
                                </div>
                            </a>
                            
                            <a href="sites.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, #059669, #047857);">
                                <i class="fas fa-map-marker-alt"></i>
                                <div style="margin-left: 1rem; text-align: left;">
                                    <div style="font-weight: 700; font-size: 1.1rem;">Work Sites</div>
                                    <div style="font-size: 0.9rem; opacity: 0.9;">Configure work locations</div>
                                </div>
                            </a>
                            
                            <a href="shifts.php" class="btn btn-primary" style="padding: 1.5rem; text-decoration: none; background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fas fa-clock"></i>
                                <div style="margin-left: 1rem; text-align: left;">
                                    <div style="font-weight: 700; font-size: 1.1rem;">Work Shifts</div>
                                    <div style="font-size: 0.9rem; opacity: 0.9;">Define working hours</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>System Information</h3>
                        <i class="fas fa-info-circle" style="color: var(--primary-indigo);"></i>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div>
                                <h4 style="color: var(--gray-900); margin-bottom: 1rem; font-size: 1.1rem;">Configuration Status</h4>
                                <div style="space-y: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(79, 70, 229, 0.05); border-radius: var(--radius); margin-bottom: 0.5rem;">
                                        <span>Departments Setup:</span>
                                        <span class="badge badge-<?php echo $departmentsCount > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $departmentsCount > 0 ? 'Complete' : 'Pending'; ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(16, 185, 129, 0.05); border-radius: var(--radius); margin-bottom: 0.5rem;">
                                        <span>Sites Configuration:</span>
                                        <span class="badge badge-<?php echo $sitesCount > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $sitesCount > 0 ? 'Complete' : 'Pending'; ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(245, 158, 11, 0.05); border-radius: var(--radius);">
                                        <span>Shifts Setup:</span>
                                        <span class="badge badge-<?php echo $shiftsCount > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $shiftsCount > 0 ? 'Complete' : 'Pending'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 style="color: var(--gray-900); margin-bottom: 1rem; font-size: 1.1rem;">Recommendations</h4>
                                <div style="space-y: 0.75rem;">
                                    <?php if ($departmentsCount == 0): ?>
                                    <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning); border-radius: var(--radius); margin-bottom: 0.75rem;">
                                        <strong>Setup Departments:</strong> Create departments to organize your employees effectively.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sitesCount == 0): ?>
                                    <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning); border-radius: var(--radius); margin-bottom: 0.75rem;">
                                        <strong>Add Work Sites:</strong> Configure work locations for accurate attendance tracking.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($shiftsCount == 0): ?>
                                    <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning); border-radius: var(--radius); margin-bottom: 0.75rem;">
                                        <strong>Define Shifts:</strong> Set up work schedules for better time management.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($departmentsCount > 0 && $sitesCount > 0 && $shiftsCount > 0): ?>
                                    <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); border-radius: var(--radius);">
                                        <strong>System Ready:</strong> All basic configurations are complete. You can now add employees and start managing your workforce.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animate stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statValues = document.querySelectorAll('.stat-value, .count');
            
            statValues.forEach(stat => {
                const finalValue = parseInt(stat.textContent) || 0;
                if (finalValue > 0) {
                    animateNumber(stat, finalValue);
                }
            });
            
            // Add entrance animations
            const cards = document.querySelectorAll('.settings-card, .overview-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        function animateNumber(element, finalValue) {
            const duration = 1500;
            const steps = 30;
            const increment = finalValue / steps;
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    current = finalValue;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, duration / steps);
        }
    </script>

<?php include '../../components/footer.php'; ?>
