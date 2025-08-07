<?php
require_once '../../config/database.php';
require_once 'response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    // Get all active sites
    $stmt = $pdo->query("
        SELECT id, name, address, latitude, longitude
        FROM sites 
        WHERE status = 'active' 
        ORDER BY name
    ");
    
    $sites = $stmt->fetchAll();
    
    sendSuccess('Sites retrieved successfully', [
        'sites' => $sites,
        'total_count' => count($sites)
    ]);
    
} catch (PDOException $e) {
    sendError('Database error occurred', 500);
}
?>