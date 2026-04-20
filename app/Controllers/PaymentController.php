<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class PaymentController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function createRecharge() {
        $user = authenticate();
        $data = getJsonInput();
        
        $amount = floatval($data['amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
        $transactionId = $data['transaction_id'] ?? '';
        
        if ($amount <= 0) {
            error('Invalid amount');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $amount, $paymentMethod, $transactionId]);
        
        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'status' => 'pending'
        ], 'Recharge request created');
    }

    public function getRechargeMethods() {
        $methods = [
            ['id' => 'bank_transfer', 'name' => 'Bank Transfer', 'min_amount' => 100, 'max_amount' => 100000],
            ['id' => 'usdt', 'name' => 'USDT', 'min_amount' => 100, 'max_amount' => 50000],
            ['id' => 'crypto', 'name' => 'Crypto', 'min_amount' => 100, 'max_amount' => 100000]
        ];
        
        response($methods);
    }

    public function getRechargeHistory() {
        $user = authenticate();
        $data = getJsonInput();
        
        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT * FROM recharges 
            WHERE user_id = ?
            ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user['id'], $limit, $offset]);
        
        $history = $stmt->fetchAll();
        response($history);
    }

    public function confirmRecharge($id) {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
        $recharge = $stmt->fetch();
        
        if (!$recharge) {
            error('Recharge not found');
        }
        
        $stmt = $db->prepare("
            UPDATE recharges SET status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        try {
            $this->userModel->updateBalance($user['id'], $recharge['amount'], 'recharge', 'Recharge completed');
        } catch (Exception $e) {
            error('Failed to update balance');
        }
        
        $stmt = $db->prepare("
            UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?
        ");
        $stmt->execute([$recharge['amount'], $user['id']]);
        
        response(null, 'Recharge completed');
    }

    public function createWithdrawal() {
        $user = authenticate();
        $data = getJsonInput();
        
        $amount = floatval($data['amount'] ?? 0);
        $bankName = $data['bank_name'] ?? '';
        $bankAccount = $data['bank_account'] ?? '';
        $accountHolder = $data['account_holder'] ?? '';
        $withdrawalPin = $data['withdrawal_pin'] ?? '';
        
        if ($amount <= 0 || empty($bankName) || empty($bankAccount) || empty($accountHolder)) {
            error('All fields are required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT withdrawal_pin FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if ($userData['withdrawal_pin'] !== $withdrawalPin) {
            error('Invalid withdrawal pin');
        }
        
        $minWithdrawal = 100;
        if ($amount < $minWithdrawal) {
            error('Minimum withdrawal amount is ' . $minWithdrawal);
        }
        
        try {
            $this->userModel->updateBalance($user['id'], -$amount, 'withdraw', 'Withdrawal request');
        } catch (Exception $e) {
            error('Insufficient balance');
        }
        
        $stmt = $db->prepare("
            INSERT INTO withdrawals (user_id, amount, bank_name, bank_account, account_holder, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $amount, $bankName, $bankAccount, $accountHolder]);
        
        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'status' => 'pending'
        ], 'Withdrawal request created');
    }

    public function getWithdrawalHistory() {
        $user = authenticate();
        $data = getJsonInput();
        
        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT * FROM withdrawals 
            WHERE user_id = ?
            ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user['id'], $limit, $offset]);
        
        $history = $stmt->fetchAll();
        response($history);
    }

    public function getWithdrawalInfo() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT balance, total_withdraw FROM users WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $info = $stmt->fetch();
        
        response([
            'available_balance' => $info['balance'],
            'total_withdrawn' => $info['total_withdraw'],
            'min_withdrawal' => 100,
            'fee_percentage' => 2
        ]);
    }
}