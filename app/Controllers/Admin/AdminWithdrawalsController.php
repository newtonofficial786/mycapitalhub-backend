<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../../app/Models/User.php';

class AdminWithdrawalsController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User(getDb());
    }
    
    public function getWithdrawals() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 20), 100);
        $offset = intval($data['offset'] ?? 0);
        $status = $data['status'] ?? '';
        
        $db = getDb();
        $where = '1=1';
        $params = [];
        
        if ($status) {
            $where = 'w.status = ?';
            $params[] = $status;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT w.*, u.mobile, u.referral_code
            FROM withdrawals w
            JOIN users u ON w.user_id = u.id
            WHERE $where
            ORDER BY w.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $withdrawals = $stmt->fetchAll();
        
        response($withdrawals);
    }
    
    public function approve() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Withdrawal ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if (!$withdrawal) error('Withdrawal not found');
        if ($withdrawal['status'] !== 'pending') error('Withdrawal not pending');
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE withdrawals SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("UPDATE wallet_transactions SET status = 'completed' WHERE type = 'withdraw' AND user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$withdrawal['user_id']]);
            
            $stmt = $db->prepare("UPDATE users SET total_withdraw = total_withdraw + ? WHERE id = ?");
            $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
            
            $db->commit();
            response(null, 'Withdrawal approved');
        } catch (Exception $e) {
            $db->rollBack();
            error('Failed to approve: ' . $e->getMessage());
        }
    }
    
    public function reject() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        $reason = $data['reason'] ?? 'Rejected by admin';
        
        if (!$id) error('Withdrawal ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$id]);
        $withdrawal = $stmt->fetch();
        
        if (!$withdrawal) error('Withdrawal not found');
        if ($withdrawal['status'] !== 'pending') error('Withdrawal not pending');
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE withdrawals SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE type = 'withdraw' AND user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$withdrawal['user_id']]);
            
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([abs($withdrawal['amount']), $withdrawal['user_id']]);
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description)
                SELECT ?, 'other', ?, balance, balance + ?, 'completed', ?
                FROM users WHERE id = ?
            ");
            $amount = abs($withdrawal['amount']);
            $stmt->execute([
                $withdrawal['user_id'],
                $amount,
                $amount,
                "Withdrawal #$id rejected: $reason",
                $withdrawal['user_id']
            ]);
            
            $db->commit();
            response(null, 'Withdrawal rejected and refunded');
        } catch (Exception $e) {
            $db->rollBack();
            error('Failed to reject: ' . $e->getMessage());
        }
    }
}
