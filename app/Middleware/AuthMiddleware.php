<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['Authorization'] ?? null;
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        error('Unauthorized', 401);
    }
    
    $token = substr($authHeader, 7);
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT u.* FROM api_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ? AND (t.expires_at IS NULL OR t.expires_at > NOW())
        AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error('Invalid or expired token', 401);
    }
    
    return $user;
}

function requireLevel($minLevel) {
    $user = authenticate();
    if ($user['level'] < $minLevel) {
        error('Access denied. Required level: ' . $minLevel, 403);
    }
    return $user;
}