<?php

require_once __DIR__ . '/AuthMiddleware.php';

if (!function_exists('authenticateAdmin')) {
    function authenticateAdmin() {
        $user = authenticate();
        
        if (empty($user['is_admin'])) {
            error('Admin access required', 403);
        }
        
        return $user;
    }
}
