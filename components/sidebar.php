<?php
// Get current page to highlight active menu item
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = dirname($_SERVER['REQUEST_URI']);

// Function to check if menu item is active
function isActiveMenu($page, $currentPage, $currentDir = '') {
    if ($page === $currentPage) return true;
    
    // Check for directory-based matching
    if (strpos($currentDir, "/{$page}/") !== false) return true;
    
    return false;
}

// Determine the correct base path based on current location
$currentDirName = basename(dirname($_SERVER['PHP_SELF']));
$basePath = '';

if (in_array($currentDirName, ['employees', 'attendance', 'petty_cash', 'reports', 'settings', 'tasks', 'salary'])) {
    $basePath = '../../';
} else {
    $basePath = '';
}
?>

<!-- Enhanced Sidebar -->
<aside class="sidebar glass-card" id="sidebar">
    <!-- Sidebar Header with Enhanced Design -->
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon">
                <img src="<?php echo $basePath; ?>assets/images/logo.png" alt="Maa Group Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <h3 class="logo-text">
                <span class="logo-main">Maa Group</span>

            </h3>
        </div>
        
    </div>

    <!-- Enhanced Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu stagger-animation">
            <!-- Dashboard -->
            <li class="menu-item">
                <a href="<?php echo $basePath; ?>index.php" class="menu-link <?php echo isActiveMenu('index', $currentPage) ? 'active' : ''; ?>" data-tooltip="Dashboard Overview">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-tachometer-alt menu-icon"></i>
                    </div>
                    <span class="menu-text">Dashboard</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <!-- Employees Section -->
            <li class="menu-section">
                <h4 class="section-title">
                    <i class="fas fa-users"></i>
                    <span>Employee Management</span>
                </h4>
            </li>
            
            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/employees/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/employees/') !== false) ? 'active' : ''; ?>" data-tooltip="Manage Employees">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-users menu-icon"></i>
                    </div>
                    <span class="menu-text">Employees</span>
                    <div class="menu-badge">
                        <span class="badge-count" id="employeeCount">--</span>
                    </div>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/attendance/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/attendance/') !== false) ? 'active' : ''; ?>" data-tooltip="Attendance Management">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-clock menu-icon"></i>
                    </div>
                    <span class="menu-text">Attendance</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <!-- Financial Section -->
            <li class="menu-section">
                <h4 class="section-title">
                    <i class="fas fa-coins"></i>
                    <span>Financial Management</span>
                </h4>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/petty_cash/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/petty_cash/') !== false) ? 'active' : ''; ?>" data-tooltip="Petty Cash Requests">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-wallet menu-icon"></i>
                    </div>
                    <span class="menu-text">Petty Cash</span>
                    <div class="menu-badge pending">
                        <span class="badge-count" id="pettyRequestCount">--</span>
                    </div>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/salary/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/salary/') !== false) ? 'active' : ''; ?>" data-tooltip="Salary Management">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-money-bill-wave menu-icon"></i>
                    </div>
                    <span class="menu-text">Salary</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <!-- Operations Section -->
            <li class="menu-section">
                <h4 class="section-title">
                    <i class="fas fa-cogs"></i>
                    <span>Operations</span>
                </h4>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/tasks/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/tasks/') !== false) ? 'active' : ''; ?>" data-tooltip="Task Management">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-tasks menu-icon"></i>
                    </div>
                    <span class="menu-text">Tasks</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/reports/index.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/reports/') !== false) ? 'active' : ''; ?>" data-tooltip="Reports & Analytics">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-chart-bar menu-icon"></i>
                    </div>
                    <span class="menu-text">Reports</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>
            <li class="menu-section">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Leave Management</span>
                </div>
            </li>
            
            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/leave/index.php" class="menu-link <?php 
                    echo (strpos($_SERVER['REQUEST_URI'], '/leave_requests/') !== false) ? 
                    'active' : ''; ?>" data-tooltip="Leave Requests">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-calendar-check menu-icon"></i>
                    </div>
                    <span class="menu-text">Leave Requests</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>


            <!-- Settings Section -->
            <li class="menu-section">
                <h4 class="section-title">
                    <i class="fas fa-cog"></i>
                    <span>System</span>
                </h4>
            </li>

            <li class="menu-item">
                <a href="<?php echo $basePath; ?>pages/settings/departments.php" class="menu-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/settings/') !== false) ? 'active' : ''; ?>" data-tooltip="System Settings">
                    <div class="menu-icon-wrapper">
                        <i class="fas fa-cog menu-icon"></i>
                    </div>
                    <span class="menu-text">Settings</span>
                    <div class="menu-indicator"></div>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer with Enhanced Info -->
    <div class="sidebar-footer">
        <div class="system-info">
            <div class="system-status">
                <div class="status-indicator online"></div>
                <span class="status-text">System Online</span>
            </div>
            <div class="system-stats">
                <div class="stat-item">
                    <i class="fas fa-server"></i>
                    <span>Server: OK</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-database"></i>
                    <span>DB: Connected</span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="quick-action-btn" data-tooltip="Refresh Data" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="quick-action-btn" data-tooltip="Help & Support" onclick="showHelp()">
                <i class="fas fa-question-circle"></i>
            </button>
            <button class="quick-action-btn" data-tooltip="Toggle Sidebar" onclick="toggleSidebar()">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
</aside>

<!-- Enhanced Sidebar Styles -->
<style>
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, var(--gray-900) 0%, var(--gray-800) 100%);
        color: var(--white);
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        box-shadow: var(--shadow-xl);
        z-index: 100;
        transition: transform var(--transition-normal);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-header {
        padding: 2rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, var(--primary-indigo), var(--primary-indigo-dark));
        position: relative;
        overflow: hidden;
    }
    
    .sidebar-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,') no-repeat center/cover;
        opacity: 0.1;
    }
    
    .logo-container {
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
        z-index: 1;
    }
    
    .logo-icon {
        width: 45px;
        height: 45px;
        background: white;
        backdrop-filter: blur(10px);
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--accent-amber);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        animation: float 3s ease-in-out infinite;
        padding: 5px;
    }
    
    .logo-text {
        margin: 0;
        display: flex;
        flex-direction: column;
        line-height: 1;
    }
    
    .logo-main {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--white);
    }
    
    .logo-sub {
        font-size: 0.75rem;
        font-weight: 400;
        color: var(--accent-amber);
        letter-spacing: 0.1em;
    }
    
    .sidebar-version {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
    
    .version-badge {
        background: var(--accent-amber);
        color: var(--gray-900);
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius);
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .sidebar-nav {
        padding: 1rem 0;
        flex: 1;
    }
    
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .menu-section {
        margin: 1.5rem 0 0.5rem 0;
    }
    
    .section-title {
        padding: 0.5rem 1.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--gray-400);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    
    .section-title i {
        font-size: 0.875rem;
        opacity: 0.7;
    }
    
    .menu-item {
        margin: 0.25rem 0;
        position: relative;
    }
    
    .menu-link {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: var(--gray-300);
        text-decoration: none;
        transition: all var(--transition-normal);
        border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
        margin-right: 1rem;
        position: relative;
        overflow: hidden;
        border-left: 4px solid transparent;
    }
    
    .menu-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(245, 158, 11, 0.1), transparent);
        transition: left 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    .menu-link:hover::before {
        left: 100%;
    }
    
    .menu-link:hover,
    .menu-link.active {
        background: rgba(79, 70, 229, 0.1);
        color: var(--white);
        transform: translateX(8px);
        border-left-color: var(--accent-amber);
    }
    
    .menu-link.active {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.2), rgba(245, 158, 11, 0.1));
        color: var(--accent-amber);
        border-left-color: var(--accent-amber);
    }
    
    .menu-icon-wrapper {
        margin-right: 1rem;
        width: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .menu-icon {
        font-size: 1.1rem;
        transition: all var(--transition-normal);
    }
    
    .menu-link:hover .menu-icon {
        animation: iconBounce 0.6s ease;
        color: var(--accent-amber);
    }
    
    .menu-text {
        flex: 1;
        font-weight: 500;
    }
    
    .menu-badge {
        margin-left: auto;
        margin-right: 0.5rem;
    }
    
    .badge-count {
        background: var(--gray-600);
        color: var(--white);
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius);
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
        display: inline-block;
    }
    
    .menu-badge.pending .badge-count {
        background: var(--warning);
        color: var(--gray-900);
        animation: pulse 2s infinite;
    }
    
    .menu-indicator {
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 0;
        background: var(--accent-amber);
        border-radius: 2px 0 0 2px;
        transition: height var(--transition-normal);
    }
    
    .menu-link.active .menu-indicator {
        height: 70%;
    }
    
    .sidebar-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 1.5rem;
        margin-top: auto;
    }
    
    .system-info {
        margin-bottom: 1rem;
    }
    
    .system-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success);
        animation: pulse 2s infinite;
    }
    
    .status-indicator.online {
        background: var(--success);
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
    }
    
    .status-text {
        font-size: 0.875rem;
        color: var(--gray-300);
    }
    
    .system-stats {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        }
        
    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        color: var(--gray-400);
    }
    
    .stat-item i {
        width: 12px;
        text-align: center;
    }
    
    .quick-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: space-between;
    }
    
    .quick-action-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: var(--gray-300);
        border-radius: var(--radius);
        padding: 0.5rem;
        cursor: pointer;
        transition: all var(--transition-normal);
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .quick-action-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: var(--white);
        transform: translateY(-1px);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 99;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .sidebar.show + .sidebar-overlay {
            display: block;
        }
    }
    
    @media (max-width: 640px) {
        .sidebar {
            width: 100%;
        }
        
        .menu-text {
            font-size: 0.95rem;
        }
        
        .sidebar-header {
            padding: 1.5rem;
        }
        
        .logo-main {
            font-size: 1.25rem;
        }
    }
    
    /* Custom scrollbar for sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: var(--gray-800);
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: var(--primary-indigo);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--primary-indigo-light);
    }
</style>

<script>
    // Enhanced sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize sidebar
        initializeSidebarEnhancements();
        
        // Load dynamic counts
        loadMenuCounts();
        
        // Add click handlers for overlay
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
        
        // Enhanced menu interactions
        const menuLinks = document.querySelectorAll('.menu-link');
        menuLinks.forEach(link => {
            // Add ripple effect on click
            link.addEventListener('click', function(e) {
                createMenuRipple(e, this);
            });
            
            // Enhanced hover effects
            link.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.2)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.boxShadow = '';
            });
        });
    });
    
    function initializeSidebarEnhancements() {
        // Add stagger animation to menu items without hiding them initially
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.05}s`;
            // Remove the opacity: 0 so items are visible from start
            item.style.animation = 'slideInLeft 0.6s ease forwards';
        });
    }
    
    function loadMenuCounts() {
        // Create a simple API endpoint to get stats or use dummy data for now
        try {
            // You can replace this with actual API call to get counts
            updateMenuCount('employeeCount', 0);
            updateMenuCount('pettyRequestCount', 0);
        } catch (error) {
            console.log('Could not load menu counts:', error);
        }
    }
    
    function updateMenuCount(elementId, count) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = count;
            if (count > 0) {
                element.style.animation = 'countUp 0.5s ease';
            }
        }
    }
    
    function createMenuRipple(event, element) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(245, 158, 11, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: menuRipple 0.6s linear;
            pointer-events: none;
            z-index: 0;
        `;
        
        element.style.position = 'relative';
        element.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    }
    
    function refreshData() {
        // Refresh data functionality
        loadMenuCounts();
        showToast('Data refreshed successfully!', 'success');
    }
    
    function showHelp() {
        // Show help modal or navigate to help page
        console.log('Show help');
    }
    
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }
    
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.remove('show');
        
        // Update mobile toggle icon
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (mobileToggle) {
            mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    }
    
    function showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--${type === 'success' ? 'success' : 'info'});
            color: var(--white);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Add dynamic CSS animations
    const dynamicStyles = `
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
        
        @keyframes menuRipple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        @keyframes countUp {
            from { transform: scale(0.8); }
            to { transform: scale(1); }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = dynamicStyles;
    document.head.appendChild(styleSheet);
</script>
