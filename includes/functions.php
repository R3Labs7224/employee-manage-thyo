<?php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function uploadFile($file, $directory, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    if (!in_array($extension, $allowedTypes)) {
        return false;
    }
    
    $fileName = uniqid() . '.' . $extension;
    $uploadPath = $directory . $fileName;
    
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $fileName;
    }
    
    return false;
}

function getStats($pdo) {
    $stats = [];
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    $stats['total_employees'] = $stmt->fetch()['total'];
    
    // Today's attendance
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE()");
    $stats['today_attendance'] = $stmt->fetch()['total'];
    
    // Pending petty cash requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM petty_cash_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['total'];
    
    // Active tasks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status = 'active'");
    $stats['active_tasks'] = $stmt->fetch()['total'];
    
    return $stats;
}
?>