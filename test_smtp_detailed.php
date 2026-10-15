<?php
// Detailed SMTP Test Script
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>SMTP Test - Detailed</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h2 { color: #333; }
        .info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0; }
        .success { background: #d1fae5; padding: 10px; border-left: 4px solid #10b981; margin: 10px 0; }
        .error { background: #fee2e2; padding: 10px; border-left: 4px solid #ef4444; margin: 10px 0; }
        .warning { background: #fef3c7; padding: 10px; border-left: 4px solid #f59e0b; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; }
        .step { margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .step-title { font-weight: bold; color: #555; margin-bottom: 5px; }
    </style>
</head>
<body>
<div class='container'>
<h2>📧 SMTP Email Test - Detailed Analysis</h2>";

// Configuration
$smtp_host = 'mail.maagroup.in';
$smtp_port = 587;
$smtp_username = 'support@maagroup.in';
$smtp_password = 'Thyo@#$123';
$from_email = 'support@maagroup.in';
$from_name = 'Employee Management System';
$to_email = 'rabiranjan7224@gmail.com';

echo "<div class='info'>";
echo "<strong>Configuration:</strong><br>";
echo "SMTP Host: $smtp_host<br>";
echo "SMTP Port: $smtp_port<br>";
echo "Username: $smtp_username<br>";
echo "From: $from_email<br>";
echo "To: $to_email<br>";
echo "Encryption: TLS<br>";
echo "</div>";

// Step 1: Test DNS resolution
echo "<div class='step'>";
echo "<div class='step-title'>Step 1: DNS Resolution</div>";
$ip = gethostbyname($smtp_host);
if ($ip === $smtp_host) {
    echo "<div class='error'>❌ Failed to resolve DNS for $smtp_host</div>";
    echo "</div></div></body></html>";
    exit;
} else {
    echo "<div class='success'>✓ DNS resolved: $smtp_host → $ip</div>";
}
echo "</div>";

// Step 2: Test connection
echo "<div class='step'>";
echo "<div class='step-title'>Step 2: TCP Connection</div>";
$socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
if (!$socket) {
    echo "<div class='error'>❌ Connection failed: $errstr ($errno)</div>";
    echo "</div></div></body></html>";
    exit;
} else {
    echo "<div class='success'>✓ Connected to $smtp_host:$smtp_port</div>";
}
echo "</div>";

// Step 3: Server greeting
echo "<div class='step'>";
echo "<div class='step-title'>Step 3: Server Greeting</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";
if (substr($response, 0, 3) != '220') {
    echo "<div class='error'>❌ Bad greeting (expected 220)</div>";
    fclose($socket);
    echo "</div></div></body></html>";
    exit;
}
echo "<div class='success'>✓ Valid greeting received</div>";
echo "</div>";

// Step 4: EHLO
echo "<div class='step'>";
echo "<div class='step-title'>Step 4: EHLO Command</div>";
fputs($socket, "EHLO localhost\r\n");
echo "<div class='code'>C: EHLO localhost</div>";
$response = '';
while ($line = fgets($socket, 515)) {
    $response .= $line;
    if (substr($line, 3, 1) == ' ') break;
}
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";
echo "<div class='success'>✓ EHLO accepted</div>";
echo "</div>";

// Step 5: STARTTLS
echo "<div class='step'>";
echo "<div class='step-title'>Step 5: STARTTLS</div>";
fputs($socket, "STARTTLS\r\n");
echo "<div class='code'>C: STARTTLS</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";
if (substr($response, 0, 3) != '220') {
    echo "<div class='error'>❌ STARTTLS failed (expected 220)</div>";
    fclose($socket);
    echo "</div></div></body></html>";
    exit;
}
echo "<div class='success'>✓ STARTTLS ready</div>";

// Enable TLS
$crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
if (!$crypto) {
    echo "<div class='error'>❌ TLS encryption failed</div>";
    fclose($socket);
    echo "</div></div></body></html>";
    exit;
}
echo "<div class='success'>✓ TLS encryption enabled</div>";
echo "</div>";

// Step 6: EHLO after TLS
echo "<div class='step'>";
echo "<div class='step-title'>Step 6: EHLO after TLS</div>";
fputs($socket, "EHLO localhost\r\n");
echo "<div class='code'>C: EHLO localhost</div>";
$response = '';
while ($line = fgets($socket, 515)) {
    $response .= $line;
    if (substr($line, 3, 1) == ' ') break;
}
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";
echo "<div class='success'>✓ EHLO after TLS accepted</div>";
echo "</div>";

// Step 7: Authentication
echo "<div class='step'>";
echo "<div class='step-title'>Step 7: Authentication</div>";
fputs($socket, "AUTH LOGIN\r\n");
echo "<div class='code'>C: AUTH LOGIN</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

fputs($socket, base64_encode($smtp_username) . "\r\n");
echo "<div class='code'>C: " . base64_encode($smtp_username) . " (base64 encoded username)</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

fputs($socket, base64_encode($smtp_password) . "\r\n");
echo "<div class='code'>C: ******** (base64 encoded password)</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

if (substr($response, 0, 3) != '235') {
    echo "<div class='error'>❌ Authentication failed</div>";
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    echo "</div></div></body></html>";
    exit;
}
echo "<div class='success'>✓ Authentication successful</div>";
echo "</div>";

// Step 8: Send email
echo "<div class='step'>";
echo "<div class='step-title'>Step 8: Sending Email</div>";

// MAIL FROM
fputs($socket, "MAIL FROM: <$from_email>\r\n");
echo "<div class='code'>C: MAIL FROM: &lt;$from_email&gt;</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

// RCPT TO
fputs($socket, "RCPT TO: <$to_email>\r\n");
echo "<div class='code'>C: RCPT TO: &lt;$to_email&gt;</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

// DATA
fputs($socket, "DATA\r\n");
echo "<div class='code'>C: DATA</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

// Email content
$email_body = "From: $from_name <$from_email>\r\n";
$email_body .= "To: <$to_email>\r\n";
$email_body .= "Subject: Test Email - SMTP Configuration Test\r\n";
$email_body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$email_body .= "\r\n";
$email_body .= "This is a test email from Employee Management System.\r\n\r\n";
$email_body .= "If you receive this email, the SMTP configuration is working correctly!\r\n\r\n";
$email_body .= "Configuration Details:\r\n";
$email_body .= "- SMTP Server: $smtp_host:$smtp_port\r\n";
$email_body .= "- From: $from_email\r\n";
$email_body .= "- Sent at: " . date('Y-m-d H:i:s') . "\r\n\r\n";
$email_body .= "Best regards,\r\n";
$email_body .= "Employee Management System\r\n";
$email_body .= ".\r\n";

fputs($socket, $email_body);
echo "<div class='code'>C: [Email content sent]</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";

if (substr($response, 0, 3) == '250') {
    echo "<div class='success'>✓ Email sent successfully!</div>";
} else {
    echo "<div class='error'>❌ Email sending failed</div>";
}
echo "</div>";

// Step 9: Disconnect
echo "<div class='step'>";
echo "<div class='step-title'>Step 9: Disconnect</div>";
fputs($socket, "QUIT\r\n");
echo "<div class='code'>C: QUIT</div>";
$response = fgets($socket, 515);
echo "<div class='code'>S: " . htmlspecialchars(trim($response)) . "</div>";
fclose($socket);
echo "<div class='success'>✓ Connection closed</div>";
echo "</div>";

echo "<div class='success' style='margin-top: 20px; font-size: 1.1em;'>";
echo "<strong>🎉 Test Complete!</strong><br>";
echo "Email has been sent to $to_email<br>";
echo "Please check the inbox (and spam folder) for the test email.";
echo "</div>";

echo "<p><a href='forgot_password.php'>← Back to Forgot Password</a></p>";

echo "</div></body></html>";
?>
