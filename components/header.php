<?php
$user = getUser();
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Determine the base path for logout
$basePath = '';
if ($currentDir === 'employees' || $currentDir === 'attendance' || $currentDir === 'petty_cash' || $currentDir === 'reports' || $currentDir === 'leave' || $currentDir === 'salary' || $currentDir === 'tasks') {
    $basePath = '../../';
} elseif ($currentDir === 'settings') {
    $basePath = '../../';
} else {
    $basePath = '';
}
?>
<div class="header">
    <div class="header-left">
        <button id="sidebarToggle" class="sidebar-toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-title">
            <h1><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h1>
        </div>
    </div>
    <div class="header-actions">
        <div class="user-info">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <span class="badge badge-<?php echo $user['role'] === 'superadmin' ? 'success' : 'warning'; ?>">
                <?php echo ucfirst($user['role']); ?>
            </span>
        </div>
        <a href="<?php echo $basePath; ?>logout.php" class="btn btn-primary" style="background: linear-gradient(135deg, var(--orange-400), var(--orange-600)); border: none;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>