<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUser() {
    if (!isLoggedIn()) return null;
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function hasPermission($role) {
    $user = getUser();
    if (!$user) return false;
    
    if ($role === 'supervisor') {
        return in_array($user['role'], ['supervisor', 'superadmin']);
    }
    
    if ($role === 'superadmin') {
        return $user['role'] === 'superadmin';
    }
    
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>