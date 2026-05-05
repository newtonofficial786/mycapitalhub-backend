<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../../app/Models/User.php';

class AdminUsersController {
    public function getUsers() {
        authenticateAdmin();
        
        $limit = min(intval($_GET['limit'] ?? 20), 100);
        $offset = intval($_GET['offset'] ?? 0);
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        
        $isAdmin = null;
        if (isset($_GET['is_admin'])) {
            $isAdmin = intval($_GET['is_admin']);
        }
        
        $db = getDb();
        $where = [];
        $params = [];
        
        if ($search !== '') {
            $where[] = '(mobile LIKE ? OR referral_code LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status !== '' && $status !== 'all') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        
        if ($isAdmin !== null) {
            $where[] = 'is_admin = ?';
            $params[] = $isAdmin;
        }
        
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT id, mobile, referral_code, level, main_wallet, stable_wallet, vip_wallet, referral_wallet, balance, total_recharge, total_withdraw, 
                   total_income, team_income, status, is_admin, created_at
            FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        $countParams = $params;
        array_pop($countParams);
        array_pop($countParams);
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereClause");
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();
        
        response(['users' => $users, 'total' => intval($total)]);
    }
    
    public function getUser() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        
        if (!$userId) error('User ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) error('User not found');
        unset($user['password']);
        
        response($user);
    }
    
    public function updateUser() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        
        if (!$userId) error('User ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) error('User not found');
        
        $updates = [];
        $params = [];
        
        $allowed = ['level', 'status'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $userId;
        $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'User updated');
    }
    
    public function adjustBalance() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        $amount = floatval($data['amount'] ?? 0);
        $type = $data['type'] ?? 'bonus';
        $reason = $data['reason'] ?? 'Admin adjustment';
        $walletType = $data['wallet_type'] ?? 'main';
        
        if (!in_array($walletType, ['main', 'stable', 'vip', 'referral'])) error('Invalid wallet type');
        if (!$userId) error('User ID required');
        if ($amount == 0) error('Amount cannot be zero');
        
        $walletColumn = $this->getWalletColumn($walletType);
        
        $db = getDb();
        $stmt = $db->prepare("SELECT {$walletColumn} as wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) error('User not found');
        
        $newBalance = $user['wallet_balance'] + $amount;
        if ($newBalance < 0) error('Insufficient balance in ' . ucfirst($walletType) . ' wallet');
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE users SET {$walletColumn} = ? WHERE id = ?");
            $stmt->execute([$newBalance, $userId]);
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, ?, ?, ?, ?, 'completed', ?, ?)
            ");
            $stmt->execute([$userId, $type, $amount, $user['wallet_balance'], $newBalance, $reason, $walletType]);
            
            $db->commit();
            response(['new_balance' => $newBalance], 'Balance adjusted');
        } catch (Exception $e) {
            $db->rollBack();
            error('Failed to adjust balance');
        }
    }
    
    public function suspendUser() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        
        if (!$userId) error('User ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$userId]);
        
        response(null, 'User suspended');
    }
    
    public function activateUser() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        
        if (!$userId) error('User ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$userId]);
        
        response(null, 'User activated');
    }
    
    public function resetWithdrawalPin() {
        authenticateAdmin();
        $data = getJsonInput();
        $userId = intval($data['user_id'] ?? 0);
        $newPin = $data['new_pin'] ?? '';
        
        if (!$userId || empty($newPin)) error('User ID and new PIN required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET withdrawal_pin = ? WHERE id = ?");
        $stmt->execute([$newPin, $userId]);
        
        response(null, 'Withdrawal PIN reset');
    }
    
    private function getWalletColumn($walletType) {
        $columns = [
            'main' => 'main_wallet',
            'stable' => 'stable_wallet',
            'vip' => 'vip_wallet',
            'referral' => 'referral_wallet',
        ];
        return $columns[$walletType] ?? 'main_wallet';
    }
}
