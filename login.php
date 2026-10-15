<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];

                // Log the login activity
                try {
                    $login_stmt = $pdo->prepare(
                        "INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) VALUES (?, NOW(), ?, ?)"
                    );
                    $login_stmt->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    // Update last_login in users table
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);
                } catch (PDOException $e) {
                    // Don't fail login if logging fails
                    error_log("Login logging error: " . $e->getMessage());
                }

                header('Location: index.php');
                exit;
            } else {
                $message = '<div class="alert alert-error">Invalid username or password</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Database error occurred</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Please fill all fields</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/logo.png">
    <link rel="shortcut icon" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-users"></i> Employee Management</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php echo $message; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div style="text-align: center; margin-top: 1rem;">
                    <a href="forgot_password.php" style="color: #4f46e5; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
