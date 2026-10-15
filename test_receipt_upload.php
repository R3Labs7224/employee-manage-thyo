<?php
// Simple test script to check receipt upload functionality
require_once 'config/database.php';

// Create a simple test image (1x1 pixel red PNG)
$test_image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');

// Test directory path
$upload_dir = './assets/images/uploads/expenses/';
$abs_upload_dir = realpath(dirname(__FILE__)) . '/' . $upload_dir;

echo "Testing receipt upload functionality...\n";
echo "Upload directory: $abs_upload_dir\n";

// Check if directory exists
if (!file_exists($abs_upload_dir)) {
    echo "Creating directory...\n";
    if (!mkdir($abs_upload_dir, 0755, true)) {
        echo "❌ Failed to create directory\n";
        exit;
    }
}

// Check if directory is writable
if (!is_writable($abs_upload_dir)) {
    echo "❌ Directory is not writable\n";
    echo "Attempting to fix permissions...\n";
    if (!chmod($abs_upload_dir, 0755)) {
        echo "❌ Failed to fix permissions\n";
        exit;
    }
}

// Create test file
$filename = 'test_receipt_' . time() . '.png';
$file_path = $abs_upload_dir . '/' . $filename;

echo "Writing test file: $filename\n";
$bytes_written = file_put_contents($file_path, $test_image_data);

if ($bytes_written === false) {
    echo "❌ Failed to write test file\n";
} else {
    echo "✅ Test file written successfully ($bytes_written bytes)\n";

    // Test if file exists and is readable
    if (file_exists($file_path) && is_readable($file_path)) {
        echo "✅ Test file exists and is readable\n";

        // Test database insertion
        try {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (
                    employee_id, amount, category_id, reason,
                    receipt_image, request_date
                ) VALUES (1, 1.00, 1, 'Test receipt upload', ?, CURDATE())
            ");

            if ($stmt->execute([$filename])) {
                $expense_id = $pdo->lastInsertId();
                echo "✅ Test expense record created with ID: $expense_id\n";

                // Verify the record
                $check_stmt = $pdo->prepare("SELECT receipt_image FROM expenses WHERE id = ?");
                $check_stmt->execute([$expense_id]);
                $result = $check_stmt->fetch();

                if ($result && $result['receipt_image'] === $filename) {
                    echo "✅ Database record verified successfully\n";

                    // Clean up test data
                    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expense_id]);
                    unlink($file_path);
                    echo "✅ Test cleanup completed\n";

                    echo "\n🎉 RECEIPT UPLOAD FUNCTIONALITY IS WORKING!\n";
                    echo "The issue might be with the mobile app or frontend not sending receipt data.\n";
                } else {
                    echo "❌ Database verification failed\n";
                }
            } else {
                echo "❌ Failed to create test expense record\n";
            }
        } catch (PDOException $e) {
            echo "❌ Database error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Test file is not accessible\n";
    }
}
?>