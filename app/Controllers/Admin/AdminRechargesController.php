<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../../app/Models/User.php';

class AdminRechargesController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User(getDb());
    }
    
    public function getRecharges() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 20), 100);
        $offset = intval($data['offset'] ?? 0);
        $status = $data['status'] ?? '';
        
        $db = getDb();
        $where = '1=1';
        $params = [];
        
        if ($status) {
            $where = 'r.status = ?';
            $params[] = $status;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT r.*, u.mobile, u.referral_code
            FROM recharges r
            JOIN users u ON r.user_id = u.id
            WHERE $where
            ORDER BY r.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $recharges = $stmt->fetchAll();
        
        response($recharges);
    }
    
    public function approve() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Recharge ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE id = ?");
        $stmt->execute([$id]);
        $recharge = $stmt->fetch();
        
        if (!$recharge) error('Recharge not found');
        if ($recharge['status'] !== 'pending') error('Recharge not pending');
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("UPDATE users SET balance = balance + ?, total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$recharge['amount'], $recharge['amount'], $recharge['user_id']]);
            
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$recharge['user_id']]);
            $newBalance = $stmt->fetch()['balance'];
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description)
                VALUES (?, 'recharge', ?, ?, ?, 'completed', 'Recharge approved by admin')
            ");
            $stmt->execute([
                $recharge['user_id'],
                $recharge['amount'],
                $newBalance - $recharge['amount'],
                $newBalance
            ]);
            
            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $recharge['amount']);
            } catch (Exception $e) {
                error_log("Commission error: " . $e->getMessage());
            }
            
            $db->commit();
            response(null, 'Recharge approved');
        } catch (Exception $e) {
            $db->rollBack();
            error('Failed to approve: ' . $e->getMessage());
        }
    }
    
    public function reject() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Recharge ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) error('Recharge not found or not pending');
        
        response(null, 'Recharge rejected');
    }
}
