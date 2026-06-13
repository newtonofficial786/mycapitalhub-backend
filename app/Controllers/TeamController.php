<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class TeamController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function getTeam() {
        $user = authenticate();
        
        $members = $this->userModel->getTeamMembers($user['id']);
        $teamCount = $this->userModel->getTeamCount($user['id']);
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT 
                SUM(total_recharge) as total_team_recharge,
                SUM(main_wallet + stable_wallet + vip_wallet + referral_wallet) as total_team_balance
            FROM users WHERE referrer_id = ?
        ");
        $stmt->execute([$user['id']]);
        $teamStats = $stmt->fetch();
        
        $stmt = $db->prepare("
            SELECT commission_rate FROM commission_settings WHERE level = 1
        ");
        $stmt->execute();
        $rate = $stmt->fetch();
        
        response([
            'members' => $members,
            'total_count' => $teamCount,
            'total_team_recharge' => floatval($teamStats['total_team_recharge'] ?? 0),
            'total_team_balance' => floatval($teamStats['total_team_balance'] ?? 0),
            'commission_rate' => floatval($rate['commission_rate'] ?? 5)
        ]);
    }

    public function getCommission() {
        $user = authenticate();
        
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.mobile,
                u.total_recharge,
                u.created_at as join_date,
                cs.commission_rate,
                (u.total_recharge * cs.commission_rate / 100) as commission
            FROM users u
            LEFT JOIN commission_settings cs ON cs.level = 1
            WHERE u.referrer_id = ?
        ");
        $stmt->execute([$user['id']]);
        $commissions = $stmt->fetchAll();
        
        $stmt = $db->prepare("
            SELECT SUM(amount) as total_commission
            FROM wallet_transactions
            WHERE user_id = ? AND type = 'commission'
        ");
        $stmt->execute([$user['id']]);
        $totalCommission = $stmt->fetch();
        
        response([
            'commissions' => $commissions,
            'total_commission' => floatval($totalCommission['total_commission'] ?? 0)
        ]);
    }

    public function getInviteInfo() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("SELECT referral_code, mobile FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        $teamCount = $this->userModel->getTeamCount($user['id']);
        
        $stmt = $db->prepare("
            SELECT commission_rate FROM commission_settings WHERE level = 1
        ");
        $stmt->execute();
        $rate = $stmt->fetch();
        
        response([
            'referral_code' => $userData['referral_code'],
            'team_count' => $teamCount,
            'commission_rate' => floatval($rate['commission_rate'] ?? 5),
            'invite_link' => (env('FRONTEND_URL', 'https://mycapitalhub.xyz') . '/auth/register?invite=' . $userData['referral_code'])
        ]);
    }

    public function getInviteRewards() {
        $user = authenticate();
        
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_invites,
                SUM(CASE WHEN total_recharge > 0 THEN 1 ELSE 0 END) as active_invites
            FROM users WHERE referrer_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch();
        
        $rewards = [
            ['level' => 1, 'required' => 1, 'reward' => 10, 'description' => '1 Active invite'],
            ['level' => 2, 'required' => 3, 'reward' => 50, 'description' => '3 Active invites'],
            ['level' => 3, 'required' => 10, 'reward' => 200, 'description' => '10 Active invites']
        ];
        
        response([
            'total_invites' => intval($stats['total_invites']),
            'active_invites' => intval($stats['active_invites']),
            'rewards' => $rewards
        ]);
    }
}