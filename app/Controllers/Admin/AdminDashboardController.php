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
                (SELECT COUNT(*) FROM users WHERE is_admin = 0) as total_users,
                (SELECT COUNT(*) FROM users WHERE is_admin = 0 AND status = 'active') as active_users,
                (SELECT COUNT(*) FROM users WHERE is_admin = 0 AND status = 'suspended') as suspended_users,
                (SELECT SUM(main_wallet + stable_wallet + vip_wallet + referral_wallet) FROM users WHERE is_admin = 0) as total_balance,
                (SELECT SUM(main_wallet) FROM users WHERE is_admin = 0) as total_main_wallet,
                (SELECT SUM(stable_wallet) FROM users WHERE is_admin = 0) as total_stable_wallet,
                (SELECT SUM(vip_wallet) FROM users WHERE is_admin = 0) as total_vip_wallet,
                (SELECT SUM(referral_wallet) FROM users WHERE is_admin = 0) as total_referral_wallet,
                (SELECT SUM(total_recharge) FROM users WHERE is_admin = 0) as total_recharge,
                (SELECT SUM(total_withdraw) FROM users WHERE is_admin = 0) as total_withdraw,
                (SELECT COUNT(*) FROM recharges r JOIN users u ON r.user_id = u.id WHERE r.status = 'pending' AND u.is_admin = 0) as pending_recharges,
                (SELECT COALESCE(SUM(r.amount), 0) FROM recharges r JOIN users u ON r.user_id = u.id WHERE r.status = 'pending' AND u.is_admin = 0) as pending_recharge_amount,
                (SELECT COUNT(*) FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' AND u.is_admin = 0) as pending_withdrawals,
                (SELECT COALESCE(SUM(w.amount), 0) FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' AND u.is_admin = 0) as pending_withdrawal_amount,
                (SELECT COUNT(*) FROM wallet_transactions WHERE status = 'pending') as pending_transactions
        ")->fetch();
        
        $todayIncome = $db->query("
            SELECT 
                SUM(CASE WHEN type = 'commission' THEN amount ELSE 0 END) as total_commission,
                SUM(CASE WHEN type = 'bonus' THEN amount ELSE 0 END) as total_bonus,
                SUM(CASE WHEN type = 'bet' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_bets,
                SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as total_wins
            FROM wallet_transactions wt
            JOIN users u ON wt.user_id = u.id
            WHERE DATE(wt.created_at) = CURDATE() AND u.is_admin = 0
        ")->fetch();
        
        $recentUsers = $db->query("
            SELECT id, mobile, main_wallet, stable_wallet, vip_wallet, referral_wallet, total_recharge, created_at 
            FROM users WHERE is_admin = 0 ORDER BY created_at DESC LIMIT 5
        ")->fetchAll();
        
        response([
            'stats' => array_map('floatval', $stats),
            'today_income' => array_map(fn($v) => floatval($v ?? 0), $todayIncome),
            'recent_users' => $recentUsers
        ]);
    }
}
