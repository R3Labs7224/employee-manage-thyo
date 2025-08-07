<?php
$user = getUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Determine the base path based on current location
$basePath = '';
if ($currentDir === 'employees' || $currentDir === 'attendance' || $currentDir === 'petty_cash' || $currentDir === 'reports' || $currentDir === 'tasks' || $currentDir === 'salary') {
    $basePath = '../../';
} elseif ($currentDir === 'settings') {
    $basePath = '../../';
} else {
    $basePath = '';
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-users"></i> EMS</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo $basePath; ?>index.php" class="<?php echo $currentPage === 'index.php' && $currentDir !== 'employees' && $currentDir !== 'attendance' && $currentDir !== 'petty_cash' && $currentDir !== 'reports' && $currentDir !== 'settings' && $currentDir !== 'tasks' && $currentDir !== 'salary' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        
        <?php if ($user['role'] === 'superadmin'): ?>
        <li>
            <a href="<?php echo $basePath; ?>pages/employees/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'employees') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Employees
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="<?php echo $basePath; ?>pages/attendance/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'attendance') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
        </li>
        
        <li>
            <a href="<?php echo $basePath; ?>pages/tasks/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'tasks') !== false ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i> Tasks
            </a>
        </li>
        
        <li>
            <a href="<?php echo $basePath; ?>pages/petty_cash/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'petty_cash') !== false ? 'active' : ''; ?>">
                <i class="fas fa-money-bill"></i> Petty Cash
            </a>
        </li>
        
        <?php if ($user['role'] === 'superadmin'): ?>
        <li>
            <a href="<?php echo $basePath; ?>pages/salary/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'salary') !== false ? 'active' : ''; ?>">
                <i class="fas fa-money-check-alt"></i> Salary
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="<?php echo $basePath; ?>pages/reports/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </li>
        
        <?php if ($user['role'] === 'superadmin'): ?>
        <li>
            <a href="<?php echo $basePath; ?>pages/settings/departments.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'settings') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>