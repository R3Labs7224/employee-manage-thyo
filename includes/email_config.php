<?php
// Email Configuration
// Update these settings according to your cPanel email configuration

// SMTP Configuration (recommended for cPanel)
define('EMAIL_USE_SMTP', true); // Set to true to use SMTP, false to use PHP mail()

// SMTP Settings (only used if EMAIL_USE_SMTP is true)
define('SMTP_HOST', 'mail.maagroup.in');  // Your cPanel mail server
define('SMTP_PORT', 587);                     // Usually 587 for TLS or 465 for SSL
define('SMTP_USERNAME', 'support@maagroup.in'); // Your cPanel email
define('SMTP_PASSWORD', 'Thyo@#$123');    // Your cPanel email password
define('SMTP_ENCRYPTION', 'tls');             // 'tls' or 'ssl'

// From Email Settings
define('FROM_EMAIL', 'support@maagroup.in');  // Replace with your domain
define('FROM_NAME', 'Employee Management System');

// Email Function
function sendResetEmail($to, $subject, $message) {
    if (EMAIL_USE_SMTP) {
        return sendSMTPEmail($to, $subject, $message);
    } else {
        // Use PHP's built-in mail() function
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        return mail($to, $subject, $message, $headers);
    }
}

function sendSMTPEmail($to, $subject, $message) {
    $smtp_host = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $smtp_username = SMTP_USERNAME;
    $smtp_password = SMTP_PASSWORD;
    $from_email = FROM_EMAIL;
    $from_name = FROM_NAME;

    $debug_log = [];

    try {
        $debug_log[] = "Attempting to connect to $smtp_host:$smtp_port";

        // Connect to SMTP server
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            $error_msg = "SMTP Connection failed: $errstr ($errno)";
            error_log($error_msg);
            error_log("Debug log: " . implode(" | ", $debug_log));
            return false;
        }
        $debug_log[] = "Connected successfully";

        // Read server response
        $response = fgets($socket, 515);
        $debug_log[] = "Server greeting: " . trim($response);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP Error - Bad greeting: $response");
            error_log("Debug log: " . implode(" | ", $debug_log));
            fclose($socket);
            return false;
        }

        // Send EHLO
        $server_name = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        fputs($socket, "EHLO $server_name\r\n");
        // Read multiline response
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // If line starts with code and space (not hyphen), it's the last line
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        $debug_log[] = "EHLO response: " . trim($response);

        // Start TLS
        if (SMTP_ENCRYPTION == 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            $debug_log[] = "STARTTLS response: " . trim($response);

            // Accept both 220 (ready) and 250 (ok) responses for STARTTLS
            $response_code = substr($response, 0, 3);
            if ($response_code != '220' && $response_code != '250') {
                error_log("SMTP STARTTLS failed: $response");
                error_log("Debug log: " . implode(" | ", $debug_log));
                fclose($socket);
                return false;
            }

            $crypto_result = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto_result) {
                error_log("SMTP TLS encryption failed");
                error_log("Debug log: " . implode(" | ", $debug_log));
                fclose($socket);
                return false;
            }
            $debug_log[] = "TLS encryption enabled";

            fputs($socket, "EHLO $server_name\r\n");
            // Read multiline response
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (preg_match('/^\d{3} /', $line)) {
                    break;
                }
            }
            $debug_log[] = "EHLO after TLS: " . trim($response);
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "AUTH LOGIN response: " . trim($response);

        fputs($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "Username response: " . trim($response);

        fputs($socket, base64_encode($smtp_password) . "\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "Password response: " . trim($response);

        if (substr($response, 0, 3) != '235') {
            error_log("SMTP Authentication failed: $response");
            error_log("Debug log: " . implode(" | ", $debug_log));
            fclose($socket);
            return false;
        }
        $debug_log[] = "Authentication successful";

        // Send email
        fputs($socket, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "MAIL FROM response: " . trim($response);

        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "RCPT TO response: " . trim($response);

        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        $debug_log[] = "DATA response: " . trim($response);

        // Email headers and body
        $email_content = "From: $from_name <$from_email>\r\n";
        $email_content .= "To: <$to>\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $message . "\r\n";
        $email_content .= ".\r\n";

        fputs($socket, $email_content);
        $response = fgets($socket, 515);
        $debug_log[] = "Send response: " . trim($response);

        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        $success = substr($response, 0, 3) == '250';
        if ($success) {
            error_log("Email sent successfully. Debug log: " . implode(" | ", $debug_log));
        } else {
            error_log("Email send failed. Debug log: " . implode(" | ", $debug_log));
        }

        return $success;
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        error_log("Debug log: " . implode(" | ", $debug_log));
        return false;
    }
}

// Test email function
function testEmailConfiguration() {
    $test_email = FROM_EMAIL;
    $subject = "Email Configuration Test";
    $message = "This is a test email to verify email configuration is working.";

    return sendResetEmail($test_email, $subject, $message);
}
?>