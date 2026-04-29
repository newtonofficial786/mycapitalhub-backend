<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$logFile = __DIR__ . '/test.log';

try {
    $testFile = fopen($logFile, 'w');
    fwrite($testFile, "Starting test\n");
    fclose($testFile);
    
    require_once __DIR__ . '/bootstrap.php';
    fwrite($logFile, "After bootstrap\n");
    
    require_once __DIR__ . '/config/Database.php';
    fwrite($logFile, "After Database\n");
    
    require_once __DIR__ . '/app/Helpers.php';
    fwrite($logFile, "After Helpers\n");
    
    $db = getDb();
    fwrite($logFile, "Got DB\n");
    
    $stmt = $db->prepare("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $banners = $stmt->fetchAll();
    fwrite($logFile, "Got banners: " . count($banners) . "\n");
    
    fwrite($logFile, "Success\n");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $banners]);
    
} catch (Throwable $e) {
    $msg = $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents($logFile, "Error: " . $msg, FILE_APPEND);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}