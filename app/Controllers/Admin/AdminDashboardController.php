<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminDashboardController {
    public function getStats() {
        authenticateAdmin();
        
        $db = getDb();
        
        $stats = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                (SELECT COUNT(*) FROM users WHERE status = 'suspended') as suspended_users,
                (SELECT SUM(main_wallet + stable_wallet + vip_wallet + referral_wallet) FROM users) as total_balance,
                (SELECT SUM(main_wallet) FROM users) as total_main_wallet,
                (SELECT SUM(stable_wallet) FROM users) as total_stable_wallet,
                (SELECT SUM(vip_wallet) FROM users) as total_vip_wallet,
                (SELECT SUM(referral_wallet) FROM users) as total_referral_wallet,
                (SELECT SUM(total_recharge) FROM users) as total_recharge,
                (SELECT SUM(total_withdraw) FROM users) as total_withdraw,
                (SELECT COUNT(*) FROM recharges WHERE status = 'pending') as pending_recharges,
                (SELECT COUNT(*) FROM withdrawals WHERE status = 'pending') as pending_withdrawals,
                (SELECT COUNT(*) FROM wallet_transactions WHERE status = 'pending') as pending_transactions
        ")->fetch();
        
        $todayIncome = $db->query("
            SELECT 
                SUM(CASE WHEN type = 'commission' THEN amount ELSE 0 END) as total_commission,
                SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) as total_bonus,
                SUM(CASE WHEN type = 'bet' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_bets,
                SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as total_wins
            FROM wallet_transactions 
            WHERE DATE(created_at) = CURDATE()
        ")->fetch();
        
        $recentUsers = $db->query("
            SELECT id, mobile, main_wallet, stable_wallet, vip_wallet, referral_wallet, total_recharge, created_at 
            FROM users ORDER BY created_at DESC LIMIT 5
        ")->fetchAll();
        
        response([
            'stats' => array_map('floatval', $stats),
            'today_income' => array_map(fn($v) => floatval($v ?? 0), $todayIncome),
            'recent_users' => $recentUsers
        ]);
    }
}
