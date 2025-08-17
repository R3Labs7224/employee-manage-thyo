<?php
// setup_directories.php - Run this once to create all required upload directories
// Place this file in your project root and run it via web browser or command line

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Setting up upload directories...</h2>\n";

// Define all required directories
$directories = [
    'assets/images/uploads/tasks/',
    'assets/images/uploads/attendance/',
    'assets/images/uploads/petty_cash/',
    'logs/'
];

$base_path = dirname(__FILE__);
echo "<p>Base path: $base_path</p>\n";

foreach ($directories as $dir) {
    $full_path = $base_path . '/' . $dir;
    echo "<h3>Processing: $dir</h3>\n";
    echo "<p>Full path: $full_path</p>\n";
    
    try {
        // Create directory if it doesn't exist
        if (!file_exists($full_path)) {
            if (mkdir($full_path, 0755, true)) {
                echo "<p style='color: green;'>✅ Directory created successfully</p>\n";
            } else {
                echo "<p style='color: red;'>❌ Failed to create directory</p>\n";
                continue;
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Directory already exists</p>\n";
        }
        
        // Set permissions
        if (chmod($full_path, 0755)) {
            echo "<p style='color: green;'>✅ Permissions set to 755</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Warning: Could not set permissions</p>\n";
        }
        
        // Check if writable
        if (is_writable($full_path)) {
            echo "<p style='color: green;'>✅ Directory is writable</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Directory is NOT writable</p>\n";
        }
        
        // Create a test file to verify write access
        $test_file = $full_path . 'test_write.txt';
        if (file_put_contents($test_file, 'test') !== false) {
            unlink($test_file); // Clean up test file
            echo "<p style='color: green;'>✅ Write test successful</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Write test failed</p>\n";
        }
        
        echo "<hr>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
        echo "<hr>\n";
    }
}

echo "<h2>Setup Complete!</h2>\n";
echo "<p>If you see any red ❌ errors above, you may need to:</p>\n";
echo "<ul>\n";
echo "<li>Check your web server has write permissions to these directories</li>\n";
echo "<li>Manually create the directories and set permissions via FTP/cPanel</li>\n";
echo "<li>Contact your hosting provider for assistance</li>\n";
echo "</ul>\n";

// Also create an index.php file in each upload directory for security
foreach ($directories as $dir) {
    if (strpos($dir, 'uploads/') !== false) {
        $index_file = $base_path . '/' . $dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php header("HTTP/1.0 403 Forbidden"); exit; ?>');
            echo "<p>Created security index.php in $dir</p>\n";
        }
    }
}

?>