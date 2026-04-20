<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Models/User.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function login() {
        $data = getJsonInput();
        
        $mobile = $data['mobile'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($mobile) || empty($password)) {
            error('Mobile and password are required');
        }
        
        $user = $this->userModel->findByMobile($mobile);
        if (!$user || !verifyPassword($password, $user['password'])) {
            error('Invalid mobile or password');
        }
        
        if ($user['status'] === 'suspended') {
            error('Account is suspended');
        }
        
        $token = generateToken();
        $config = getConfig();
        $expiry = time() + $config['jwt']['expiry'];
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO api_tokens (user_id, token, expires_at)
            VALUES (?, ?, FROM_UNIXTIME(?))
        ");
        $stmt->execute([$user['id'], $token, $expiry]);
        
        $userData = $this->userModel->findById($user['id']);
        unset($userData['password'], $userData['withdrawal_pin']);
        
        response([
            'token' => $token,
            'user' => $userData
        ]);
    }

    public function register() {
        $data = getJsonInput();
        
        $mobile = $data['mobile'] ?? '';
        $password = $data['password'] ?? '';
        $withdrawalPin = $data['withdrawal_pin'] ?? '';
        $referrerCode = $data['referrer_code'] ?? '';
        
        if (empty($mobile) || empty($password) || empty($withdrawalPin)) {
            error('Mobile, password, and withdrawal pin are required');
        }
        
        if (strlen($mobile) < 10) {
            error('Invalid mobile number');
        }
        
        if (strlen($password) < 6) {
            error('Password must be at least 6 characters');
        }
        
        if (!empty($referrerCode)) {
            $referrer = $this->userModel->findByReferralCode($referrerCode);
            if (!$referrer) {
                error('Invalid referral code');
            }
        }
        
        $existing = $this->userModel->findByMobile($mobile);
        if ($existing) {
            error('Mobile number already registered');
        }
        
        $userId = $this->userModel->create([
            'mobile' => $mobile,
            'password' => hashPassword($password),
            'withdrawal_pin' => $withdrawalPin,
            'referrer_code' => $referrerCode
        ]);
        
        $token = generateToken();
        $config = getConfig();
        $expiry = time() + $config['jwt']['expiry'];
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO api_tokens (user_id, token, expires_at)
            VALUES (?, ?, FROM_UNIXTIME(?))
        ");
        $stmt->execute([$userId, $token, $expiry]);
        
        $userData = $this->userModel->findById($userId);
        unset($userData['password'], $userData['withdrawal_pin']);
        
        response([
            'token' => $token,
            'user' => $userData
        ], 'Registration successful');
    }

    public function logout() {
        $user = authenticate();
        
        $db = getDb();
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        $token = substr($authHeader, 7);
        
        $stmt = $db->prepare("DELETE FROM api_tokens WHERE token = ? AND user_id = ?");
        $stmt->execute([$token, $user['id']]);
        
        response(null, 'Logged out successfully');
    }
}