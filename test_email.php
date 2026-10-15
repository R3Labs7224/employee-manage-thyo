<?php
require_once 'includes/email_config.php';

echo "<h2>Testing Email Configuration</h2>";
echo "<p>SMTP Host: " . SMTP_HOST . "</p>";
echo "<p>SMTP Port: " . SMTP_PORT . "</p>";
echo "<p>SMTP Username: " . SMTP_USERNAME . "</p>";
echo "<p>From Email: " . FROM_EMAIL . "</p>";
echo "<p>Encryption: " . SMTP_ENCRYPTION . "</p>";
echo "<hr>";

$to = "rabiranjan7224@gmail.com";
$subject = "Test Email - Employee Management System";
$message = "This is a test email sent from the Employee Management System.

If you receive this email, the SMTP configuration is working correctly.

Configuration:
- SMTP Server: " . SMTP_HOST . ":" . SMTP_PORT . "
- From: " . FROM_EMAIL . "

Test sent at: " . date('Y-m-d H:i:s') . "

Best regards,
Employee Management System";

echo "<h3>Sending test email to: $to</h3>";
echo "<p>Please wait...</p>";
flush();

$result = sendResetEmail($to, $subject, $message);

if ($result) {
    echo "<div style='color: green; padding: 10px; background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✓ SUCCESS!</strong><br>";
    echo "Email sent successfully to $to<br>";
    echo "Please check the inbox (and spam folder) of rabiranjan7224@gmail.com";
    echo "</div>";
} else {
    echo "<div style='color: red; padding: 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✗ FAILED!</strong><br>";
    echo "Failed to send email. Check error logs for details.<br>";
    echo "Common issues:<br>";
    echo "- SMTP credentials incorrect<br>";
    echo "- SMTP server blocking connection<br>";
    echo "- Port blocked by firewall<br>";
    echo "- SSL/TLS configuration issue";
    echo "</div>";

    // Show PHP error log path
    echo "<p><strong>Check error logs at:</strong> " . ini_get('error_log') . "</p>";
}

echo "<hr>";
echo "<a href='forgot_password.php'>← Back to Forgot Password</a>";
?>
