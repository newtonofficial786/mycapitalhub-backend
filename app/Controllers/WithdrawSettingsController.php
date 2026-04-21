<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';

class WithdrawSettingsController {
    public function getSettings() {
        $db = getDb();
        $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();
        
        response($settings);
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