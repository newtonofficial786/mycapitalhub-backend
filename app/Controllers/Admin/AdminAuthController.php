<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';

class AdminAuthController {
    public function login() {
        $data = getJsonInput();
        
        $mobile = $data['mobile'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($mobile) || empty($password)) {
            error('Mobile and password are required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE mobile = ? AND is_admin = 1");
        $stmt->execute([$mobile]);
        $admin = $stmt->fetch();
        
        if (!$admin || !verifyPassword($password, $admin['password'])) {
            error('Invalid credentials or not an admin');
        }
        
        $token = generateToken();
        $config = getConfig();
        $expiry = time() + $config['jwt']['expiry'];
        
        $stmt = $db->prepare("
            INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))
        ");
        $stmt->execute([$admin['id'], $token, $expiry]);
        
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        unset($admin['password'], $admin['withdrawal_pin']);
        
        response([
            'token' => $token,
            'user' => $admin
        ], 'Admin login successful');
    }
    
    public function logout() {
        $user = authenticate();
        
        $db = getDb();
        $token = getBearerToken();
        $stmt = $db->prepare("DELETE FROM api_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        response(null, 'Logged out');
    }
}
