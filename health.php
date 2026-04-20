<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

header('Content-Type: application/json');

try {
    $db = getDb();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $users = $stmt->fetch();
    
    $stmt = $db->query("SELECT token FROM api_tokens LIMIT 1");
    $token = $stmt->fetch();
    
    echo json_encode([
        'status' => 'ok',
        'database' => 'connected',
        'users_count' => (int)$users['cnt'],
        'has_token' => (bool)$token
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}