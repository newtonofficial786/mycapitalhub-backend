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

    public function verifyAndShowWithdrawalPin() {
        $user = authenticate();
        $data = getJsonInput();
        
        $password = $data['password'] ?? '';
        
        if (empty($password)) {
            error('Password is required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT password, withdrawal_pin FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if (!verifyPassword($password, $userData['password'])) {
            error('Incorrect password');
        }
        
        response([
            'withdrawal_pin' => $userData['withdrawal_pin']
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
                main_wallet,
                stable_wallet,
                vip_wallet,
                referral_wallet,
                total_withdraw,
                total_income,
                team_income,
                level
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch();
        
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as total_wins,
                SUM(CASE WHEN type = 'bet' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_bets,
                SUM(CASE WHEN type = 'commission' THEN amount ELSE 0 END) as total_commission,
                SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) as total_bonus
            FROM wallet_transactions WHERE user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch();
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_completed_recharge
            FROM recharges WHERE user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$user['id']]);
        $rechargeStats = $stmt->fetch();
        
        // Recalculate level from total_recharge
        $totalRecharge = floatval($rechargeStats['total_completed_recharge'] ?? 0);
        $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
        $stmt->execute([$totalRecharge]);
        $levelRow = $stmt->fetch();
        $level = $levelRow ? intval($levelRow['level']) : 0;

        $totalBonus = floatval($stats['total_bonus'] ?? 0);
        $totalCommission = floatval($stats['total_commission'] ?? 0);
        $userIncome = $totalBonus + $totalCommission;
        
        response([
            'main_wallet' => floatval($wallet['main_wallet'] ?? 0),
            'stable_wallet' => floatval($wallet['stable_wallet'] ?? 0),
            'vip_wallet' => floatval($wallet['vip_wallet'] ?? 0),
            'referral_wallet' => floatval($wallet['referral_wallet'] ?? 0),
            'balance' => $wallet['main_wallet'],
            'total_recharge' => $totalRecharge,
            'total_withdraw' => $wallet['total_withdraw'],
            'total_income' => $userIncome,
            'team_income' => $wallet['team_income'],
            'level' => $level,
            'total_wins' => $stats['total_wins'] ?? 0,
            'total_bets' => $stats['total_bets'] ?? 0,
            'total_commission' => $totalCommission,
            'total_bonus' => $totalBonus
        ]);
    }
}