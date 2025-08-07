<?php
// Error handling functions

function show404($message = 'Page not found') {
    http_response_code(404);
    
    // Determine the correct path to 404.php based on current location
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    $basePath = '';
    
    if (in_array($currentDir, ['employees', 'attendance', 'petty_cash', 'reports', 'settings'])) {
        $basePath = '../../';
    }
    
    // Check if we can include the custom 404 page
    $errorPage = $basePath . '404.php';
    if (file_exists($errorPage)) {
        include $errorPage;
    } else {
        // Fallback to basic 404
        echo "<!DOCTYPE html><html><head><title>404 - Page Not Found</title></head><body>";
        echo "<h1>404 - Page Not Found</h1>";
        echo "<p>" . htmlspecialchars($message) . "</p>";
        echo "<a href='" . $basePath . "index.php'>Go Home</a>";
        echo "</body></html>";
    }
    exit;
}

function show500($message = 'Internal server error') {
    http_response_code(500);
    
    // Determine the correct path to 500.php based on current location
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    $basePath = '';
    
    if (in_array($currentDir, ['employees', 'attendance', 'petty_cash', 'reports', 'settings'])) {
        $basePath = '../../';
    }
    
    // Log the error (in production, you'd want to log to a file)
    error_log("500 Error: " . $message . " - " . $_SERVER['REQUEST_URI']);
    
    // Check if we can include the custom 500 page
    $errorPage = $basePath . '500.php';
    if (file_exists($errorPage)) {
        include $errorPage;
    } else {
        // Fallback to basic 500
        echo "<!DOCTYPE html><html><head><title>500 - Server Error</title></head><body>";
        echo "<h1>500 - Internal Server Error</h1>";
        echo "<p>Something went wrong. Please try again later.</p>";
        echo "<a href='" . $basePath . "index.php'>Go Home</a>";
        echo "</body></html>";
    }
    exit;
}

// Set custom error and exception handlers
set_error_handler(function($severity, $message, $file, $line) {
    // Don't handle errors that are not in error_reporting
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Log the error
    error_log("PHP Error: $message in $file on line $line");
    
    // For fatal errors, show 500 page
    if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        show500("A system error occurred");
    }
    
    return false;
});

set_exception_handler(function($exception) {
    // Log the exception
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Show 500 error page
    show500("An unexpected error occurred");
});

// Function to check if a file/page exists and show 404 if not
function requirePageOrShow404($condition, $message = 'The requested page was not found') {
    if (!$condition) {
        show404($message);
    }
}

// Function to handle database errors gracefully
function handleDatabaseError($e, $userMessage = 'A database error occurred') {
    // Log the actual error for developers
    error_log("Database Error: " . $e->getMessage());
    
    // Show user-friendly 500 error
    show500($userMessage);
}
?>