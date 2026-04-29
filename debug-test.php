<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

$logFile = __DIR__ . '/test.log';
file_put_contents($logFile, "Start: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

try {
    require_once __DIR__ . '/bootstrap.php';
    file_put_contents($logFile, "After bootstrap\n", FILE_APPEND);
    
    require_once __DIR__ . '/config/Database.php';
    file_put_contents($logFile, "After Database.php\n", FILE_APPEND);
    
    require_once __DIR__ . '/app/Helpers.php';
    file_put_contents($logFile, "After Helpers.php\n", FILE_APPEND);
    
    $db = getDb();
    file_put_contents($logFile, "After getDb()\n", FILE_APPEND);
    
    $stmt = $db->prepare("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $banners = $stmt->fetchAll();
    file_put_contents($logFile, "After query: " . count($banners) . "\n", FILE_APPEND);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $banners]);
    
} catch (Throwable $e) {
    $msg = $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
    file_put_contents($logFile, "Error: " . $msg . "\n", FILE_APPEND);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $msg]);
}