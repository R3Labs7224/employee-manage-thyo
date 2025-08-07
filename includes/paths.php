<?php
// Path helper functions

function getBasePath() {
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    
    // If we're in a subdirectory, go up two levels
    if (in_array($currentDir, ['employees', 'attendance', 'petty_cash', 'reports', 'settings'])) {
        return '../../';
    }
    
    // If we're in root directory
    return '';
}

function getAbsolutePath($relativePath = '') {
    $basePath = getBasePath();
    return $basePath . $relativePath;
}

// Define common paths
define('ROOT_PATH', getBasePath());
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('PAGES_PATH', ROOT_PATH . 'pages/');
define('API_PATH', ROOT_PATH . 'api/');
define('UPLOADS_PATH', ASSETS_PATH . 'images/uploads/');
?>