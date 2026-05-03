<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class WithdrawSettingsController {
    public function getSettings() {
        date_default_timezone_set('Asia/Kolkata');
        
        $db = getDb();
        $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings) {
            response([
                'min_amount' => 100,
                'max_amount' => 100000,
                'fee_percentage' => 2,
                'daily_limit' => 50000,
                'withdrawal_time' => '07:00am-05:00pm',
                'processing_time' => '1-24 hours',
                'close_from' => '07:00',
                'close_to' => '17:00',
                'server_time' => time(),
                'target_time' => null,
                'is_closed' => false
            ]);
            return;
        }
        
        $closeFrom = $settings['close_from'] ?? '07:00';
        $closeTo = $settings['close_to'] ?? '17:00';
        $now = date('H:i');
        $serverTimestamp = time();
        $targetTimestamp = null;
        $isClosed = false;
        
        if ($closeFrom < $closeTo) {
            // Same-day window (e.g., 07:00 to 17:00)
            if ($now < $closeFrom) {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom);
            } elseif ($now > $closeTo) {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom . ' +1 day');
            } else {
                $targetTimestamp = strtotime($closeTo);
            }
        } else {
            // Overnight window (e.g., 18:00 to 07:00)
            if ($now >= $closeFrom || $now <= $closeTo) {
                $targetTimestamp = strtotime($closeTo);
                if ($now > $closeTo) $targetTimestamp = strtotime($closeTo . ' +1 day');
            } else {
                $isClosed = true;
                $targetTimestamp = strtotime($closeFrom);
            }
        }
        
        response([
            'min_amount' => floatval($settings['min_amount']),
            'max_amount' => floatval($settings['max_amount']),
            'fee_percentage' => floatval($settings['fee_percentage']),
            'daily_limit' => floatval($settings['daily_limit']),
            'withdrawal_time' => $settings['withdrawal_time'],
            'processing_time' => $settings['processing_time'],
            'close_from' => $closeFrom,
            'close_to' => $closeTo,
            'server_time' => $serverTimestamp,
            'target_time' => $targetTimestamp,
            'is_closed' => $isClosed
        ]);
    }
    
    public function updateSettings() {
        authenticate();
        $data = getJsonInput();
        
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE withdraw_settings SET 
                min_amount = ?,
                max_amount = ?,
                fee_percentage = ?,
                daily_limit = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['min_amount'] ?? 100,
            $data['max_amount'] ?? 100000,
            $data['fee_percentage'] ?? 2,
            $data['daily_limit'] ?? 50000,
            $data['id'] ?? 1
        ]);
        
        response(null, 'Settings updated successfully');
    }
}