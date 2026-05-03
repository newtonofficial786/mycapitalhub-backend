<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';

if (!function_exists('authenticate')) {
    function authenticate() {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback for environments without getallheaders()
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $key = str_replace('_', '-', strtolower(substr($name, 5)));
                    $headers[$key] = $value;
                }
            }
        }
        
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
        
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        }
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            error('Unauthorized - No token provided', 401);
        }
        
        $token = substr($authHeader, 7);
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT u.* FROM api_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ? AND (t.expires_at IS NULL OR t.expires_at > NOW())
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error('Invalid or expired token', 401);
        }
        
        if ($user['status'] !== 'active') {
            error('Account suspended', 403);
        }
        
        return $user;
    }
}

if (!function_exists('requireLevel')) {
    function requireLevel($minLevel) {
        $user = authenticate();
        if ($user['level'] < $minLevel) {
            error('Access denied. Required level: ' . $minLevel, 403);
        }
        return $user;
    }
}

if (!function_exists('getBearerToken')) {
    function getBearerToken() {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $key = str_replace('_', '-', strtolower(substr($name, 5)));
                    $headers[$key] = $value;
                }
            }
        }
        
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
        
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        }
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        
        return substr($authHeader, 7);
    }
}