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
            WHERE user_id = ? AND status = 'completed'
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
        
        if ($recharge['status'] === 'completed') {
            error('Recharge already completed');
        }
        
        $stmt = $db->prepare("
            UPDATE recharges SET status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        try {
            $this->userModel->updateWalletBalance($user['id'], $recharge['amount'], 'recharge', 'main', 'Recharge completed');
        } catch (Exception $e) {
            error('Failed to update balance');
        }
        
        $stmt = $db->prepare("
            UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?
        ");
        $stmt->execute([$recharge['amount'], $user['id']]);
        
        // Pay referral commission to the referrer (if any)
        try {
            $this->userModel->payReferralCommission($user['id'], $recharge['amount']);
        } catch (Exception $e) {
            // Don't fail the recharge if commission payment fails
            error_log("Commission payment failed: " . $e->getMessage());
        }
        
        response(null, 'Recharge completed');
    }

    public function createWithdrawal() {
        $user = authenticate();
        $data = getJsonInput();
        
        $amount = floatval($data['amount'] ?? 0);
        $withdrawalPin = $data['withdrawal_pin'] ?? '';
        $walletType = $data['wallet_type'] ?? 'main';
        
        if (!in_array($walletType, ['main', 'stable', 'vip', 'referral'])) {
            error('Invalid wallet type');
        }
        
        if ($amount <= 0) {
            error('Invalid amount');
        }
        
        $db = getDb();
        
        // Check if user already has ANY pending withdrawal
        $stmt = $db->prepare("
            SELECT id, amount, created_at FROM withdrawals 
            WHERE user_id = ? AND status = 'pending'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $existingPending = $stmt->fetch();
        
        if ($existingPending) {
            $created = new DateTime($existingPending['created_at']);
            $now = new DateTime();
            $diff = $now->diff($created);
            $minutes = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
            
            // Get processing time from settings for display
            $settingsStmt = $db->query("SELECT processing_time FROM withdraw_settings WHERE active = 1 LIMIT 1");
            $settings = $settingsStmt->fetch();
            $processingTime = $settings['processing_time'] ?? '1-24 hours';
            
            error(sprintf(
                "You already have a pending withdrawal of ₹%s (ID: %s) created %d minutes ago. Processing time: %s. Please wait for it to complete before requesting another withdrawal.",
                number_format($existingPending['amount'], 2, '.', ''),
                $existingPending['id'],
                $minutes,
                $processingTime
            ), 400);
        }
        
        // Prevent duplicate pending withdrawals (same amount within last 10 seconds) - additional guard
        $stmt = $db->prepare("
            SELECT id FROM withdrawals 
            WHERE user_id = ? AND amount = ? AND status = 'pending' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ");
        $stmt->execute([$user['id'], $amount]);
        if ($stmt->fetch()) {
            error('A withdrawal with this amount is already being processed. Please wait.', 400);
        }
        
        // Also check for duplicate pending wallet_transactions (defense in depth)
        $stmt = $db->prepare("
            SELECT id FROM wallet_transactions 
            WHERE user_id = ? AND type = 'withdraw' AND amount = ? AND status = 'pending'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ");
        $stmt->execute([$user['id'], -$amount]);
        if ($stmt->fetch()) {
            error('A withdrawal transaction is already being processed. Please wait.', 400);
        }
        
        // Get withdraw settings
        $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();
        
        $minAmount = floatval($settings['min_amount'] ?? 100);
        $maxAmount = floatval($settings['max_amount'] ?? 100000);
        $feePercentage = floatval($settings['fee_percentage'] ?? 2);
        
        // Get user bank details (full)
        $stmt = $db->prepare("SELECT account_holder, bank_name, account_number, ifsc_code FROM user_bank_details WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $bankDetails = $stmt->fetch();
        
        if (!$bankDetails || empty($bankDetails['account_holder']) || empty($bankDetails['account_number']) || empty($bankDetails['ifsc_code'])) {
            error('Please add complete bank details first');
        }
        
        // Use bank details from database (full, unmasked)
        $bankName = $bankDetails['bank_name'] ?? '';
        $bankAccount = $bankDetails['account_number'] ?? '';
        $accountHolder = $bankDetails['account_holder'] ?? '';
        $ifscCode = $bankDetails['ifsc_code'] ?? '';
        
        // Verify withdrawal pin
        $stmt = $db->prepare("SELECT withdrawal_pin FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if ($userData['withdrawal_pin'] !== $withdrawalPin) {
            error('Invalid withdrawal pin');
        }
        
        // Check min/max amount
        if ($amount < $minAmount) {
            error('Minimum withdrawal amount is ₹' . $minAmount);
        }
        if ($amount > $maxAmount) {
            error('Maximum withdrawal amount is ₹' . $maxAmount);
        }
        
        // Check user wallet balance (with lock)
        $walletColumn = $this->getWalletColumn($walletType);
        $stmt = $db->prepare("SELECT {$walletColumn} as wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user['id']]);
        $userBalance = floatval($stmt->fetch()['wallet_balance'] ?? 0);
        
        if ($userBalance < $amount) {
            error('Insufficient balance in ' . ucfirst($walletType) . ' wallet. Your balance: ₹' . $userBalance);
        }
        
        // Calculate fee
        $fee = ($amount * $feePercentage) / 100;
        $receiveAmount = $amount - $fee;
        
        try {
            // Include bank details in wallet transaction
            $bankData = [
                'bank_name' => $bankName,
                'bank_account' => $bankAccount,
                'account_holder' => $accountHolder,
                'ifsc_code' => $ifscCode
            ];
            $txnId = $this->userModel->updateWalletBalanceWithBankDetails($user['id'], -$amount, 'withdraw', $walletType, 'Withdrawal request - Fee: ₹' . $fee, $bankData, 'pending');
        } catch (Exception $e) {
            error('Insufficient balance or transaction failed: ' . $e->getMessage());
        }
        
        $stmt = $db->prepare("
            INSERT INTO withdrawals (user_id, amount, bank_name, bank_account, account_holder, ifsc_code, wallet_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $amount, $bankName, $bankAccount, $accountHolder, $ifscCode, $walletType]);
        
        response([
            'id' => $txnId,
            'amount' => $amount,
            'status' => 'pending',
            'wallet_type' => $walletType,
            'bank_name' => $bankName,
            'bank_account' => $bankAccount,
            'account_holder' => $accountHolder,
            'ifsc_code' => $ifscCode
        ], 'Withdrawal request created');
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

    public function getWithdrawalHistory() {
        $user = authenticate();
        $data = getJsonInput();
        
        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        
        // Get from wallet_transactions where type = 'withdraw' (including bank details if stored)
        $stmt = $db->prepare("
            SELECT id, user_id, type, amount, balance_before, balance_after, status, description, created_at,
                   bank_name, bank_account, account_holder, ifsc_code
            FROM wallet_transactions 
            WHERE user_id = ? AND type = 'withdraw'
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
            SELECT main_wallet, stable_wallet, vip_wallet, referral_wallet, total_withdraw 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $info = $stmt->fetch();
        
        // Also calculate pending withdrawal amount from wallet_transactions
        $stmt = $db->prepare("
            SELECT SUM(ABS(amount)) as pending_amount
            FROM wallet_transactions 
            WHERE user_id = ? AND type = 'withdraw' AND status = 'pending'
        ");
        $stmt->execute([$user['id']]);
        $pending = $stmt->fetch();
        
        response([
            'main_wallet' => floatval($info['main_wallet'] ?? 0),
            'stable_wallet' => floatval($info['stable_wallet'] ?? 0),
            'vip_wallet' => floatval($info['vip_wallet'] ?? 0),
            'referral_wallet' => floatval($info['referral_wallet'] ?? 0),
            'available_balance' => floatval($info['main_wallet'] ?? 0),
            'total_withdrawn' => floatval($info['total_withdraw']),
            'pending_withdrawals' => floatval($pending['pending_amount'] ?? 0)
        ]);
    }

    public function getWithdrawalSettings() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings) {
            // Fallback defaults
            $settings = [
                'min_amount' => 100,
                'max_amount' => 100000,
                'fee_percentage' => 2,
                'daily_limit' => 50000,
                'withdrawal_time' => '07:00am-05:00pm',
                'processing_time' => '1-24 hours'
            ];
        }
        
        // Return full settings
        response([
            'min_amount' => floatval($settings['min_amount'] ?? 100),
            'max_amount' => floatval($settings['max_amount'] ?? 100000),
            'fee_percentage' => floatval($settings['fee_percentage'] ?? 2),
            'daily_limit' => floatval($settings['daily_limit'] ?? 50000),
            'withdrawal_time' => $settings['withdrawal_time'] ?? '07:00am-05:00pm',
            'processing_time' => $settings['processing_time'] ?? '1-24 hours'
        ]);
    }
}