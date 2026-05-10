<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Helpers.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User(getDb());
    }

    private function checkMaintenance($blockLogin = false) {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM maintenance_settings WHERE active = 1 LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();

        if (!$settings || $settings['mode'] === 'off') {
            return false;
        }

        $isInWindow = true;

        if ($settings['mode'] === 'temporary') {
            $now = date('Y-m-d H:i:s');
            $start = $settings['start_time'];
            $end = $settings['end_time'];

            if ($start && $end) {
                $isInWindow = ($now >= $start && $now <= $end);
            } elseif ($start && !$end) {
                $isInWindow = ($now >= $start);
            } elseif (!$start && $end) {
                $isInWindow = ($now <= $end);
            } else {
                $isInWindow = true;
            }
        }

        if (!$isInWindow) {
            return false;
        }

        if ($blockLogin && $settings['allow_login'] == 0) {
            return [
                'maintenance' => true,
                'mode' => $settings['mode'],
                'title' => $settings['title'] ?: 'Under Maintenance',
                'message' => $settings['message'] ?: 'Login is temporarily disabled. Please try again later.',
            ];
        }

        return [
            'maintenance' => true,
            'mode' => $settings['mode'],
            'title' => $settings['title'] ?: 'Under Maintenance',
            'message' => $settings['message'] ?: 'We are performing scheduled maintenance. Please try again later.',
            'sub_message' => $settings['sub_message'],
            'allow_login' => (bool)$settings['allow_login'],
        ];
    }

    public function login() {
        $maintenance = $this->checkMaintenance(true);
        if ($maintenance) {
            error($maintenance['message'], 503, ['maintenance' => true, 'mode' => $maintenance['mode']]);
        }

        $data = getJsonInput();

        $mobile = $data['mobile'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($mobile) || empty($password)) {
            error('Mobile and password are required');
        }

        $user = $this->userModel->findByMobile($mobile);

        if (!$user || !verifyPassword($password, $user['password'])) {
            error('Invalid credentials');
        }

        if ($user['status'] === 'suspended') {
            error('Account is suspended');
        }

        $token = generateToken();
        $config = getConfig();
        $expiresAt = date('Y-m-d H:i:s', time() + $config['jwt']['expiry']);

        $this->userModel->createToken($user['id'], $token, $expiresAt);

        response([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'mobile' => $user['mobile'],
                'balance' => floatval($user['balance'] ?? 0),
                'status' => $user['status'],
            ]
        ], 'Login successful');
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
        
        if (strlen($withdrawalPin) < 4 || !ctype_digit($withdrawalPin)) {
            error('Withdrawal pin must be a 4-digit number');
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
        $expiresAt = date('Y-m-d H:i:s', time() + $config['jwt']['expiry']);
        
        $this->userModel->createToken($userId, $token, $expiresAt);
        
        if (!empty($referrerCode)) {
            $referrer = $this->userModel->findByReferralCode($referrerCode);
            if ($referrer) {
                $this->userModel->update($referrer['id'], [
                    'balance' => floatval($referrer['balance'] ?? 0) + 10
                ]);
            }
        }
        
        response([
            'token' => $token,
            'user' => [
                'id' => $userId,
                'mobile' => $mobile,
                'balance' => 0,
                'status' => 'active',
            ]
        ], 'Registration successful');
    }
    
    public function logout() {
        $user = authenticate();
        
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM api_tokens WHERE token = ? AND user_id = ?");
        $stmt->execute([$token, $user['id']]);
        
        response(null, 'Logged out successfully');
    }
}
