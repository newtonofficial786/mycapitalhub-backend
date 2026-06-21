<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';

class ActivityController {

    public function log() {
        error_log('[Activity] Request received');
        try {
            $user = authenticate();
            $userId = $user['id'];
            $data = getJsonInput();
            $db = getDb();
            error_log('[Activity] User ID: ' . $userId . ', Data: ' . json_encode($data));

            $activityType = $data['activity_type'] ?? 'other';
            $metadata = $data['metadata'] ?? null;
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            $validTypes = [
                'app_open', 'login', 'register', 'logout', 'page_view',
                'recharge_initiated', 'recharge_success', 'recharge_failed', 'recharge_pending',
                'withdraw_initiated', 'withdraw_success', 'withdraw_failed', 'withdraw_pending',
                'product_purchase', 'vip_purchase', 'income_claim',
                'game_bet', 'game_win', 'game_loss',
                'profile_update', 'bank_update', 'pin_verify', 'referral_share', 'other'
            ];

            if (!in_array($activityType, $validTypes)) {
                $activityType = 'other';
            }

            $stmt = $db->prepare("
                INSERT INTO user_activities (user_id, activity_type, metadata, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $activityType,
                $metadata ? json_encode($metadata) : null,
                $ipAddress,
                $userAgent,
            ]);

            error_log('[Activity] Saved successfully');
            response(['logged' => true]);
        } catch (Throwable $e) {
            error_log('[Activity] ERROR: ' . $e->getMessage());
            response(['logged' => false, 'error' => $e->getMessage()], 'Error', 500);
        }
    }
}
