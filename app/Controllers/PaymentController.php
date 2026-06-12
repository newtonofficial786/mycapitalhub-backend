<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class PaymentController
{
    private $userModel;

    public function __construct()
    {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function createRecharge()
    {
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

    public function getRechargeMethods()
    {
        $db = getDb();
        $items = $db->query("SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
        response($items);
    }

    public function getRechargeHistory()
    {
        $user = authenticate();
        $data = getJsonInput();

        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);

        if ($limit > 100)
            $limit = 100;

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

    public function confirmRecharge($id)
    {
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

        // Auto level-up based on total recharge
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_recharge FROM recharges WHERE user_id = ? AND status = 'completed'");
            $stmt->execute([$user['id']]);
            $rechargeData = $stmt->fetch();
            $totalRecharge = floatval($rechargeData['total_recharge'] ?? 0);

            $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
            $stmt->execute([$totalRecharge]);
            $newLevel = $stmt->fetch();

            if ($newLevel) {
                $level = intval($newLevel['level']);
                $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ? AND level < ?");
                $stmt->execute([$level, $user['id'], $level]);
            }
        } catch (Exception $e) {
            error_log("Level up check failed: " . $e->getMessage());
        }

        response(null, 'Recharge completed');
    }

    public function createWithdrawal()
    {
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

        // If withdrawing from stable wallet, check no active stable products remain
        if ($walletType === 'stable') {
            $stmt = $db->prepare("
                SELECT COUNT(*) as cnt FROM user_products
                WHERE user_id = ? AND expiry_date >= CURRENT_DATE AND status = 'active'
            ");
            $stmt->execute([$user['id']]);
            $activeCount = intval($stmt->fetch()['cnt'] ?? 0);
            if ($activeCount > 0) {
                error('Cannot withdraw from stable wallet while you have active stable plans. Please wait until all plans complete.');
            }
        }

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

        // Check withdrawal window (close_from / close_to)
        $closeFrom = $settings['close_from'] ?? '00:00';
        $closeTo = $settings['close_to'] ?? '23:59';
        $now = date('H:i');

        $isClosed = false;
        if ($closeFrom < $closeTo) {
            // Window is within the same day (e.g., 07:00 to 17:00)
            if ($now < $closeFrom || $now > $closeTo)
                $isClosed = true;
        } else {
            // Window spans midnight (e.g., 18:00 to 07:00)
            if ($now < $closeFrom && $now > $closeTo)
                $isClosed = false;
            else
                $isClosed = true;
        }

        if ($isClosed) {
            // Calculate next opening time
            $nextOpen = new DateTime($closeFrom);
            $nowDt = new DateTime();
            if ($nowDt > $nextOpen) {
                $nextOpen->modify('+1 day');
            }
            $diff = $nowDt->diff($nextOpen);
            $totalSeconds = ($diff->h * 3600) + ($diff->i * 60) + $diff->s;

            error(sprintf(
                "Withdrawals are closed. Please wait %dh %dm %ds until %s to withdraw.",
                $diff->h,
                $diff->i,
                $diff->s,
                $closeFrom
            ), 400);
        }

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

        if ($withdrawalPin !== $userData['withdrawal_pin']) {
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

    private function getWalletColumn($walletType)
    {
        $columns = [
            'main' => 'main_wallet',
            'stable' => 'stable_wallet',
            'vip' => 'vip_wallet',
            'referral' => 'referral_wallet',
        ];
        return $columns[$walletType] ?? 'main_wallet';
    }

    public function getWithdrawalHistory()
    {
        $user = authenticate();
        $data = getJsonInput();

        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);

        if ($limit > 100)
            $limit = 100;

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

    public function getWithdrawalInfo()
    {
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

    public function getWithdrawalSettings()
    {
        $user = authenticate();

        $db = getDb();
        $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            $settings = [
                'min_amount' => 100,
                'max_amount' => 100000,
                'fee_percentage' => 2,
                'daily_limit' => 50000,
                'withdrawal_time' => '07:00am-05:00pm',
                'processing_time' => '1-24 hours'
            ];
        }

        $closeFrom = $settings['close_from'] ?? '00:00';
        $closeTo = $settings['close_to'] ?? '23:59';
        $now = date('H:i');
        $serverTimestamp = time();

        $isClosed = false;
        $targetTimestamp = null;

        if ($closeFrom < $closeTo) {
            if ($now < $closeFrom) {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom);
            } elseif ($now > $closeTo) {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom . ' +1 day');
            }
            if (!$isClosed) {
                $targetTimestamp = strtotime($closeTo);
            }
        } else {
            if ($now >= $closeFrom || $now <= $closeTo) {
                $targetTimestamp = strtotime($closeTo);
                if ($now > $closeTo)
                    $targetTimestamp = strtotime($closeTo . ' +1 day');
            } else {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom);
            }
        }

        response([
            'min_amount' => floatval($settings['min_amount'] ?? 100),
            'max_amount' => floatval($settings['max_amount'] ?? 100000),
            'fee_percentage' => floatval($settings['fee_percentage'] ?? 2),
            'daily_limit' => floatval($settings['daily_limit'] ?? 50000),
            'withdrawal_time' => $settings['withdrawal_time'] ?? '07:00am-05:00pm',
            'processing_time' => $settings['processing_time'] ?? '1-24 hours',
            'close_from' => $closeFrom,
            'close_to' => $closeTo,
            'server_time' => $serverTimestamp,
            'target_time' => $targetTimestamp,
            'is_closed' => $isClosed
        ]);
    }

    public function getPaymentResult($type)
    {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM payment_result WHERE status_type = ? LIMIT 1");
        $stmt->execute([$type]);
        $result = $stmt->fetch();
        response($result);
    }

    public function createWatchPaysRecharge()
    {
        $user = authenticate();
        $data = getJsonInput();

        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0) {
            error('Invalid amount');
        }

        require_once __DIR__ . '/../Services/WatchPaysService.php';
        $watchpays = new WatchPaysService();

        $orderId = 'WP' . date('YmdHis') . $user['id'] . rand(100, 999);

        $callbackUrl = env('WATCHPAYS_CALLBACK_URL', 'https://tatainvest.in/tatainvest/api/payment/watchpays/callback');

        $result = $watchpays->createPaymentOrder([
            'amount' => $amount,
            'order_id' => $orderId,
            'callback_url' => $callbackUrl,
            'extra' => json_encode(['user_id' => $user['id']])
        ]);

        if (!$result['success']) {
            error('Payment gateway error: ' . ($result['error'] ?? 'Unknown error'));
        }

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status, yoyopay_order_id, gateway_order_id)
            VALUES (?, ?, 'watchpays', ?, 'pending', ?, ?)
        ");
        $stmt->execute([$user['id'], $amount, $orderId, $orderId, $result['order_no'] ?? '']);

        $paymentUrl = $result['payment_url'] ?? '';
        $resultUrl = $callbackUrl;

        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'payment_url' => $paymentUrl,
            'merchant_order_id' => $orderId,
            'result_url' => $resultUrl,
            'status' => 'pending'
        ], 'Payment order created. Redirect to payment_url to complete payment.');
    }

    public function handleWatchPaysCallback()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        $gatewayOrderNo = $data['orderNo'] ?? '';
        $merchantOrderNo = $data['merchantOrder'] ?? '';
        $status = $data['status'] ?? '';
        $amount = floatval($data['amount'] ?? 0);

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE (yoyopay_order_id = ? OR gateway_order_id = ?) AND status = 'pending'");
        $stmt->execute([$merchantOrderNo, $gatewayOrderNo]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if ($amount > 0 && abs($amount - floatval($recharge['amount'])) > 0.01) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if (strtolower($status) === 'success') {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$gatewayOrderNo, $recharge['id']]);

            $creditAmount = floatval($recharge['amount']);
            $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'WatchPays recharge completed');

            $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$creditAmount, $recharge['user_id']]);

            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
            } catch (Exception $e) {
                // Commission payment failed
            }

            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_recharge FROM recharges WHERE user_id = ? AND status = 'completed'");
                $stmt->execute([$recharge['user_id']]);
                $rechargeData = $stmt->fetch();
                $totalRecharge = floatval($rechargeData['total_recharge'] ?? 0);

                $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
                $stmt->execute([$totalRecharge]);
                $newLevel = $stmt->fetch();

                if ($newLevel) {
                    $level = intval($newLevel['level']);
                    $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ? AND level < ?");
                    $stmt->execute([$level, $recharge['user_id'], $level]);
                }
            } catch (Exception $e) {
                // Level up failed
            }
        } else {
            $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$recharge['id']]);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['code' => 200, 'msg' => 'success']);
        exit;
    }

    public function queryWatchPaysOrder()
    {
        $user = authenticate();
        $data = getJsonInput();

        $orderId = $data['order_id'] ?? '';

        if (!$orderId) {
            error('Order ID is required');
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE yoyopay_order_id = ? AND user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            error('Order not found');
        }

        if ($recharge['status'] === 'pending') {
            require_once __DIR__ . '/../Services/WatchPaysService.php';
            $watchpays = new WatchPaysService();
            $apiResult = $watchpays->queryOrder($orderId);

            error_log('[WatchPays Query] Order: ' . $orderId . ' Response: ' . json_encode($apiResult));

            $apiData = $apiResult['data'] ?? null;
            if ($apiData) {
                $apiStatus = $apiData['status'] ?? $apiData['pay_status'] ?? $apiData['order_status'] ?? '';

                error_log('[WatchPays Query] Status: ' . $apiStatus);

                if (in_array(strtolower($apiStatus), ['completed', 'success', 'paid'], true)) {
                    $stmt = $db->prepare("UPDATE recharges SET status = 'completed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$recharge['id']]);
                    $recharge['status'] = 'completed';

                    $creditAmount = floatval($recharge['amount']);
                    $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'WatchPays recharge completed (query)');

                    $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
                    $stmt->execute([$creditAmount, $recharge['user_id']]);

                    try {
                        $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
                    } catch (Exception $e) {
                        error_log('[WatchPays Query] Referral commission error: ' . $e->getMessage());
                    }
                } elseif (in_array(strtolower($apiStatus), ['failed', 'cancelled', 'expired'], true)) {
                    $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$recharge['id']]);
                    $recharge['status'] = 'failed';
                }
            }
        }

        response([
            'order_id' => $orderId,
            'status' => $recharge['status'],
            'amount' => floatval($recharge['amount']),
            'local_status' => $recharge['status'],
            'created_at' => $recharge['created_at'],
        ]);
    }

    public function createGalePayRecharge()
    {
        $user = authenticate();
        $data = getJsonInput();

        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0) {
            error('Invalid amount');
        }

        require_once __DIR__ . '/../Services/GalePayService.php';
        $galepay = new GalePayService();

        $orderId = 'GP' . date('YmdHis') . $user['id'] . rand(100, 999);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $callbackUrl = env('GALEPAY_CALLBACK_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/') . '/api/payment/galepay/callback');
        $returnUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderId;

        $phone = $user['mobile'] ?? '9102380668';
        $email = $user['email'] ?? 'user@example.com';

        $result = $galepay->createPayIn([
            'amount' => $amount,
            'order_id' => $orderId,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'phone' => $phone,
            'email' => $email,
        ]);

        if (!$result['success']) {
            error('Payment gateway error: ' . ($result['error'] ?? 'Unknown error'));
        }

        $paymentData = $result['data'];
        $paymentUrl = $paymentData['paymentUrl'] ?? '';

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status, yoyopay_order_id, gateway_order_id, payment_url)
            VALUES (?, ?, 'galepay', ?, 'pending', ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $amount,
            $orderId,
            $orderId,
            $paymentData['orderNo'] ?? '',
            $paymentUrl
        ]);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $resultUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderId;

        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'payment_url' => $paymentUrl,
            'merchant_order_id' => $orderId,
            'gateway_order_no' => $paymentData['orderNo'] ?? '',
            'result_url' => $resultUrl,
            'status' => 'pending'
        ], 'Payment order created. Redirect to payment_url to complete payment.');
    }

    public function handleGalePayCallback()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        require_once __DIR__ . '/../Services/GalePayService.php';
        $galepay = new GalePayService();

        $callbackResult = $galepay->handleCallback($data);

        if (!$callbackResult['success']) {
            error_log('[GalePay] Invalid signature: ' . json_encode($data));
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 500, 'msg' => 'invalid signature']);
            return;
        }

        $mchOrderId = $callbackResult['mchOrderId'];
        $gatewayOrderNo = $callbackResult['orderNo'];
        $payStatus = $callbackResult['payStatus'];
        $amount = floatval($callbackResult['amount']);

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE (yoyopay_order_id = ? OR gateway_order_id = ?) AND status = 'pending'");
        $stmt->execute([$mchOrderId, $gatewayOrderNo]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if ($amount > 0 && abs($amount - floatval($recharge['amount'])) > 0.01) {
            error_log('[GalePay] Amount mismatch for order ' . $mchOrderId . ': expected ' . $recharge['amount'] . ', got ' . $amount);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if ($payStatus === '1') {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$gatewayOrderNo, $recharge['id']]);

            $creditAmount = floatval($recharge['amount']);
            $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'GalePay recharge completed');

            $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$creditAmount, $recharge['user_id']]);

            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
            } catch (Exception $e) {
                error_log('[GalePay] Commission payment failed: ' . $e->getMessage());
            }

            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_recharge FROM recharges WHERE user_id = ? AND status = 'completed'");
                $stmt->execute([$recharge['user_id']]);
                $rechargeData = $stmt->fetch();
                $totalRecharge = floatval($rechargeData['total_recharge'] ?? 0);

                $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
                $stmt->execute([$totalRecharge]);
                $newLevel = $stmt->fetch();

                if ($newLevel) {
                    $level = intval($newLevel['level']);
                    $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ? AND level < ?");
                    $stmt->execute([$level, $recharge['user_id'], $level]);
                }
            } catch (Exception $e) {
                error_log('[GalePay] Level up failed: ' . $e->getMessage());
            }
        } else {
            $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$recharge['id']]);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['code' => 200, 'msg' => 'success']);
        exit;
    }

    public function queryGalePayOrder()
    {
        $user = authenticate();
        $data = getJsonInput();

        $orderId = $data['order_id'] ?? '';

        if (!$orderId) {
            error('Order ID is required');
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE yoyopay_order_id = ? AND user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            error('Order not found');
        }

        require_once __DIR__ . '/../Services/GalePayService.php';
        $galepay = new GalePayService();

        $result = $galepay->queryPayIn($orderId);

        if (!$result['success']) {
            error('Failed to query order: ' . ($result['error'] ?? 'Unknown error'));
        }

        $orderData = $result['data'];
        $payStatus = $orderData['payStatus'] ?? '0';

        if ($payStatus === '1' && $recharge['status'] === 'pending') {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$orderData['orderNo'] ?? '', $recharge['id']]);

            $creditAmount = floatval($recharge['amount']);
            $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'GalePay recharge completed (query)');

            $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$creditAmount, $recharge['user_id']]);

            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
            } catch (Exception $e) {
                error_log('[GalePay] Commission payment failed: ' . $e->getMessage());
            }
        }

        response([
            'order_id' => $orderId,
            'status' => $payStatus === '1' ? 'completed' : 'pending',
            'amount' => $orderData['amount'] ?? $recharge['amount'],
            'pay_status' => $payStatus,
            'local_status' => $recharge['status'],
            'created_at' => $recharge['created_at'],
        ]);
    }

    public function createJazpayRecharge()
    {
        $user = authenticate();
        $data = getJsonInput();

        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0) {
            error('Invalid amount');
        }

        require_once __DIR__ . '/../Services/JazpayService.php';
        $jazpay = new JazpayService();

        $orderId = 'JZ' . date('YmdHis') . $user['id'] . rand(100, 999);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $callbackUrl = env('JAZPAY_CALLBACK_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/') . '/api/payment/jazpay/callback');
        $returnUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderId;

        $result = $jazpay->createPaymentOrder([
            'amount' => $amount,
            'order_id' => $orderId,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
        ]);

        if (!$result['success']) {
            error('Payment gateway error: ' . ($result['error'] ?? 'Unknown error'));
        }

        $paymentData = $result['data'];
        error_log('[Jazpay] API response data: ' . json_encode($paymentData));
        $paymentUrl = $paymentData['payment_url'] ?? $paymentData['paymentUrl'] ?? $paymentData['pay_url'] ?? $paymentData['url'] ?? '';

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status, yoyopay_order_id, gateway_order_id, payment_url)
            VALUES (?, ?, 'jazpay', ?, 'pending', ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $amount,
            $orderId,
            $orderId,
            $paymentData['orderNo'] ?? '',
            $paymentUrl
        ]);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $resultUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderId;

        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'payment_url' => $paymentUrl,
            'merchant_order_id' => $orderId,
            'gateway_order_no' => $paymentData['orderNo'] ?? '',
            'result_url' => $resultUrl,
            'status' => 'pending'
        ], 'Payment order created. Redirect to payment_url to complete payment.');
    }

    public function handleJazpayCallback()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        require_once __DIR__ . '/../Services/JazpayService.php';
        $jazpay = new JazpayService();

        $callbackResult = $jazpay->handleCallback($data);

        if (!$callbackResult['success']) {
            error_log('[Jazpay] Invalid signature: ' . json_encode($data));
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 500, 'msg' => 'invalid signature']);
            return;
        }

        $merchantOrder = $callbackResult['merchantOrder'];
        $gatewayOrderNo = $callbackResult['orderNo'];
        $status = $callbackResult['status'];
        $amount = floatval($callbackResult['amount']);

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE (yoyopay_order_id = ? OR gateway_order_id = ?) AND status = 'pending'");
        $stmt->execute([$merchantOrder, $gatewayOrderNo]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if ($amount > 0 && abs($amount - floatval($recharge['amount'])) > 0.01) {
            error_log('[Jazpay] Amount mismatch for order ' . $merchantOrder . ': expected ' . $recharge['amount'] . ', got ' . $amount);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if (strtolower($status) === 'success') {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$gatewayOrderNo, $recharge['id']]);

            $creditAmount = floatval($recharge['amount']);
            $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'Jazpay recharge completed');

            $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$creditAmount, $recharge['user_id']]);

            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
            } catch (Exception $e) {
                error_log('[Jazpay] Commission payment failed: ' . $e->getMessage());
            }

            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_recharge FROM recharges WHERE user_id = ? AND status = 'completed'");
                $stmt->execute([$recharge['user_id']]);
                $rechargeData = $stmt->fetch();
                $totalRecharge = floatval($rechargeData['total_recharge'] ?? 0);

                $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
                $stmt->execute([$totalRecharge]);
                $newLevel = $stmt->fetch();

                if ($newLevel) {
                    $level = intval($newLevel['level']);
                    $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ? AND level < ?");
                    $stmt->execute([$level, $recharge['user_id'], $level]);
                }
            } catch (Exception $e) {
                error_log('[Jazpay] Level up failed: ' . $e->getMessage());
            }
        } else {
            $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$recharge['id']]);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['code' => 200, 'msg' => 'success']);
        exit;
    }

    public function queryJazpayOrder()
    {
        $user = authenticate();
        $data = getJsonInput();

        $orderId = $data['order_id'] ?? '';

        if (!$orderId) {
            error('Order ID is required');
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE yoyopay_order_id = ? AND user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            error('Order not found');
        }

        response([
            'order_id' => $orderId,
            'status' => $recharge['status'],
            'amount' => floatval($recharge['amount']),
            'local_status' => $recharge['status'],
            'created_at' => $recharge['created_at'],
        ]);
    }

    public function createZoxpayRecharge()
    {
        $user = authenticate();
        $data = getJsonInput();

        $amount = floatval($data['amount'] ?? 0);

        if ($amount <= 0) {
            error('Invalid amount');
        }

        require_once __DIR__ . '/../Services/ZoxpayService.php';
        $zoxpay = new ZoxpayService();

        $orderNo = 'ZX' . date('YmdHis') . $user['id'] . rand(100, 999);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $callbackUrl = env('ZOXPAY_CALLBACK_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/') . '/api/payment/zoxpay/callback');
        $returnUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderNo;

        $result = $zoxpay->createPaymentOrder([
            'amount' => $amount,
            'order_no' => $orderNo,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
        ]);

        if (!$result['success']) {
            $code = $result['code'] ?? 0;
            error('Payment gateway error [HTTP ' . $code . ']: ' . ($result['error'] ?? 'Unknown error'));
        }

        $paymentData = $result['data'];
        $paymentUrl = $paymentData['payment_url'] ?? $paymentData['paymentUrl'] ?? $paymentData['pay_url'] ?? $paymentData['url'] ?? '';
        if (!$paymentUrl && isset($paymentData['message']) && filter_var($paymentData['message'], FILTER_VALIDATE_URL)) {
            $paymentUrl = $paymentData['message'];
        }
        if (!$paymentUrl) {
            $orderNoFromApi = $paymentData['order_no'] ?? $orderNo;
            $paymentUrl = rtrim(env('ZOXPAY_API_BASE', 'https://api.zoxpays.com'), '/') . '/pay/' . $orderNoFromApi;
        }

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO recharges (user_id, amount, payment_method, transaction_id, status, yoyopay_order_id, gateway_order_id, payment_url)
            VALUES (?, ?, 'zoxpay', ?, 'pending', ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $amount,
            $orderNo,
            $orderNo,
            $paymentData['order_no'] ?? '',
            $paymentUrl
        ]);

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000'));
        $resultUrl = rtrim($frontendUrl, '/') . '/payment-result?order=' . $orderNo;

        response([
            'id' => $db->lastInsertId(),
            'amount' => $amount,
            'payment_url' => $paymentUrl,
            'merchant_order_id' => $orderNo,
            'gateway_order_no' => $paymentData['order_no'] ?? '',
            'result_url' => $resultUrl,
            'status' => 'pending'
        ], 'Payment order created. Redirect to payment_url to complete payment.');
    }

    public function handleZoxpayCallback()
    {
        $input = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($input, true);
        } else {
            parse_str($input, $data);
        }

        if (!$data || !isset($data['order_no'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        require_once __DIR__ . '/../Services/ZoxpayService.php';
        $zoxpay = new ZoxpayService();

        $callbackResult = $zoxpay->handleCallback($data);

        if (!$callbackResult['success']) {
            error_log('[Zoxpay] Invalid callback: ' . json_encode($data));
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 500, 'msg' => 'invalid callback']);
            return;
        }

        $orderNo = $callbackResult['orderNo'];
        $status = $callbackResult['status'];
        $amount = $callbackResult['amount'];

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE (yoyopay_order_id = ? OR gateway_order_id = ?) AND status = 'pending'");
        $stmt->execute([$orderNo, $orderNo]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if ($amount > 0 && abs($amount - floatval($recharge['amount'])) > 0.01) {
            error_log('[Zoxpay] Amount mismatch for order ' . $orderNo . ': expected ' . $recharge['amount'] . ', got ' . $amount);
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['code' => 200, 'msg' => 'success']);
            return;
        }

        if (strtolower($status) === 'success') {
            $stmt = $db->prepare("UPDATE recharges SET status = 'completed', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$orderNo, $recharge['id']]);

            $creditAmount = floatval($recharge['amount']);
            $this->userModel->updateWalletBalance($recharge['user_id'], $creditAmount, 'recharge', 'main', 'Zoxpay recharge completed');

            $stmt = $db->prepare("UPDATE users SET total_recharge = total_recharge + ? WHERE id = ?");
            $stmt->execute([$creditAmount, $recharge['user_id']]);

            try {
                $this->userModel->payReferralCommission($recharge['user_id'], $creditAmount);
            } catch (Exception $e) {
                error_log('[Zoxpay] Commission payment failed: ' . $e->getMessage());
            }

            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_recharge FROM recharges WHERE user_id = ? AND status = 'completed'");
                $stmt->execute([$recharge['user_id']]);
                $rechargeData = $stmt->fetch();
                $totalRecharge = floatval($rechargeData['total_recharge'] ?? 0);

                $stmt = $db->prepare("SELECT level FROM user_level_settings WHERE active = 1 AND min_recharge <= ? ORDER BY level DESC LIMIT 1");
                $stmt->execute([$totalRecharge]);
                $newLevel = $stmt->fetch();

                if ($newLevel) {
                    $level = intval($newLevel['level']);
                    $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ? AND level < ?");
                    $stmt->execute([$level, $recharge['user_id'], $level]);
                }
            } catch (Exception $e) {
                error_log('[Zoxpay] Level up failed: ' . $e->getMessage());
            }
        } else {
            $stmt = $db->prepare("UPDATE recharges SET status = 'failed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$recharge['id']]);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['code' => 200, 'msg' => 'success']);
        exit;
    }

    public function queryZoxpayOrder()
    {
        $user = authenticate();
        $data = getJsonInput();

        $orderId = $data['order_id'] ?? '';

        if (!$orderId) {
            error('Order ID is required');
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM recharges WHERE yoyopay_order_id = ? AND user_id = ?");
        $stmt->execute([$orderId, $user['id']]);
        $recharge = $stmt->fetch();

        if (!$recharge) {
            error('Order not found');
        }

        response([
            'order_id' => $orderId,
            'status' => $recharge['status'],
            'amount' => floatval($recharge['amount']),
            'local_status' => $recharge['status'],
            'created_at' => $recharge['created_at'],
        ]);
    }
}