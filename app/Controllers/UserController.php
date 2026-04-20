<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class UserController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function profile() {
        $user = authenticate();
        $userData = $this->userModel->findById($user['id']);
        unset($userData['password'], $userData['withdrawal_pin']);
        
        $teamCount = $this->userModel->getTeamCount($user['id']);
        $userData['team_count'] = $teamCount;
        
        response($userData);
    }

    public function updateProfile() {
        $user = authenticate();
        $data = getJsonInput();
        
        $db = getDb();
        $updates = [];
        $params = [];
        
        if (isset($data['withdrawal_pin'])) {
            $updates[] = "withdrawal_pin = ?";
            $params[] = $data['withdrawal_pin'];
        }
        
        if (!empty($updates)) {
            $params[] = $user['id'];
            $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
        }
        
        $userData = $this->userModel->findById($user['id']);
        unset($userData['password'], $userData['withdrawal_pin']);
        
        response($userData, 'Profile updated');
    }

    public function changePassword() {
        $user = authenticate();
        $data = getJsonInput();
        
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            error('Current and new password are required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if (!verifyPassword($currentPassword, $userData['password'])) {
            error('Current password is incorrect');
        }
        
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([hashPassword($newPassword), $user['id']]);
        
        response(null, 'Password changed successfully');
    }

    public function getTeamMembers() {
        $user = authenticate();
        
        $members = $this->userModel->getTeamMembers($user['id']);
        $teamCount = $this->userModel->getTeamCount($user['id']);
        
        response([
            'members' => $members,
            'total_count' => $teamCount
        ]);
    }

    public function getReferrer() {
        $user = authenticate();
        
        $referrer = $this->userModel->getReferrer($user['id']);
        
        response($referrer);
    }

    public function getWalletInfo() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT 
                balance,
                total_recharge,
                total_withdraw,
                total_income,
                team_income,
                level
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch();
        
        $stmt = $db->prepare("
            SELECT SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as total_wins,
                   SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END) as total_bets
            FROM wallet_transactions WHERE user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch();
        
        response([
            'balance' => $wallet['balance'],
            'total_recharge' => $wallet['total_recharge'],
            'total_withdraw' => $wallet['total_withdraw'],
            'total_income' => $wallet['total_income'],
            'team_income' => $wallet['team_income'],
            'level' => $wallet['level'],
            'total_wins' => abs($stats['total_wins'] ?? 0),
            'total_bets' => abs($stats['total_bets'] ?? 0)
        ]);
    }
}