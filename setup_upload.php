<?php
// debug_edit.php - Debug script to identify edit employee issues

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Edit Employee Issues</h2>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    require_once 'config/database.php';
    echo "✅ Database connection successful<br>";
    
    // Test if employees table exists and has the new columns
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<strong>Employees table columns:</strong><br>";
    $new_columns = [
        'contact_number', 'uan_number', 'esic_number', 'aadhar_number', 'pan_number',
        'designation', 'reporting_manager_id', 'emergency_contact_number', 'date_of_birth',
        'qualification', 'total_experience', 'nationality', 'religion', 'marital_status',
        'gender', 'blood_group', 'photograph', 'current_address', 'permanent_address',
        'last_working_day', 'reason_of_separation', 'created_at', 'updated_at'
    ];
    
    $missing_columns = [];
    foreach ($new_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✅ $col<br>";
        } else {
            echo "❌ $col (MISSING)<br>";
            $missing_columns[] = $col;
        }
    }
    
    if (!empty($missing_columns)) {
        echo "<p><strong>⚠️ Missing columns detected!</strong> You need to run the database migration first.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test if we can get employee data
echo "<h3>2. Test Employee Data Retrieval</h3>";
try {
    // Get first employee for testing
    $stmt = $pdo->query("SELECT * FROM employees LIMIT 1");
    $test_employee = $stmt->fetch();
    
    if ($test_employee) {
        echo "✅ Found test employee: " . htmlspecialchars($test_employee['name']) . " (ID: {$test_employee['id']})<br>";
        echo "<a href='debug_edit.php?test_id={$test_employee['id']}'>Test Edit with this employee</a><br>";
        
        // Test the edit query
        if (isset($_GET['test_id'])) {
            $test_id = (int)$_GET['test_id'];
            echo "<h4>Testing edit query for employee ID: $test_id</h4>";
            
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active'");
            $stmt->execute([$test_id]);
            $employee = $stmt->fetch();
            
            if ($employee) {
                echo "✅ Employee data retrieved successfully<br>";
                echo "<strong>Employee details:</strong><br>";
                echo "Name: " . htmlspecialchars($employee['name']) . "<br>";
                echo "Employee Code: " . htmlspecialchars($employee['employee_code']) . "<br>";
                echo "Contact: " . htmlspecialchars($employee['contact_number'] ?? $employee['phone'] ?? 'N/A') . "<br>";
                
                // Test if new fields exist
                $new_field_test = [
                    'uan_number' => $employee['uan_number'] ?? 'Column missing',
                    'designation' => $employee['designation'] ?? 'Column missing',
                    'gender' => $employee['gender'] ?? 'Column missing'
                ];
                
                foreach ($new_field_test as $field => $value) {
                    echo "$field: " . htmlspecialchars($value) . "<br>";
                }
            } else {
                echo "❌ Could not retrieve employee data<br>";
            }
        }
        
    } else {
        echo "❌ No employees found in database<br>";
    }
} catch (Exception $e) {
    echo "❌ Error testing employee data: " . $e->getMessage() . "<br>";
}

// Test includes and session
echo "<h3>3. Test Required Files</h3>";
$required_files = [
    'config/database.php',
    'includes/session.php',
    'includes/functions.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// Test session and permissions
echo "<h3>4. Test Session and Permissions</h3>";
try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        echo "✅ User session active (ID: {$_SESSION['user_id']})<br>";
        
        if (function_exists('hasPermission')) {
            if (hasPermission('superadmin')) {
                echo "✅ User has superadmin permission<br>";
            } else {
                echo "⚠️ User does not have superadmin permission<br>";
            }
        } else {
            echo "⚠️ hasPermission function not available<br>";
        }
    } else {
        echo "❌ No user session found - user might not be logged in<br>";
    }
} catch (Exception $e) {
    echo "❌ Session error: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Server Error Log</h3>";
echo "<p>Check your server's error log for specific PHP errors. Common locations:</p>";
echo "<ul>";
echo "<li>/var/log/apache2/error.log</li>";
echo "<li>/var/log/nginx/error.log</li>";
echo "<li>PHP error_log in your hosting control panel</li>";
echo "</ul>";

echo "<h3>6. Quick Fixes</h3>";
echo "<p>If you see missing columns above, run this SQL to add them:</p>";
echo "<textarea style='width:100%; height:200px; font-family:monospace; font-size:12px;'>";
echo "-- Add missing columns (run only the ones that are missing)
ALTER TABLE employees ADD COLUMN contact_number VARCHAR(15) NULL;
ALTER TABLE employees ADD COLUMN uan_number VARCHAR(12) NULL;
ALTER TABLE employees ADD COLUMN esic_number VARCHAR(17) NULL;
ALTER TABLE employees ADD COLUMN aadhar_number VARCHAR(12) NULL;
ALTER TABLE employees ADD COLUMN pan_number VARCHAR(10) NULL;
ALTER TABLE employees ADD COLUMN designation VARCHAR(100) NULL;
ALTER TABLE employees ADD COLUMN reporting_manager_id INT NULL;
ALTER TABLE employees ADD COLUMN emergency_contact_number VARCHAR(15) NULL;
ALTER TABLE employees ADD COLUMN date_of_birth DATE NULL;
ALTER TABLE employees ADD COLUMN qualification VARCHAR(200) NULL;
ALTER TABLE employees ADD COLUMN total_experience DECIMAL(4,2) NULL;
ALTER TABLE employees ADD COLUMN nationality VARCHAR(50) DEFAULT 'Indian';
ALTER TABLE employees ADD COLUMN religion VARCHAR(50) NULL;
ALTER TABLE employees ADD COLUMN marital_status ENUM('single', 'married', 'divorced', 'widowed') NULL;
ALTER TABLE employees ADD COLUMN gender ENUM('male', 'female', 'other') NULL;
ALTER TABLE employees ADD COLUMN blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL;
ALTER TABLE employees ADD COLUMN photograph VARCHAR(255) NULL;
ALTER TABLE employees ADD COLUMN current_address TEXT NULL;
ALTER TABLE employees ADD COLUMN permanent_address TEXT NULL;
ALTER TABLE employees ADD COLUMN last_working_day DATE NULL;
ALTER TABLE employees ADD COLUMN reason_of_separation TEXT NULL;
ALTER TABLE employees ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE employees ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- If you still have 'phone' column, rename it to 'contact_number'
-- ALTER TABLE employees CHANGE COLUMN phone contact_number VARCHAR(15) NOT NULL;";
echo "</textarea>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
h4 { color: #666; }
</style>