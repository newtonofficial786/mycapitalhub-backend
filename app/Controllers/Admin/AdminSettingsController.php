<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminSettingsController {
    public function getCommissionSettings() {
        authenticateAdmin();
        
        $settings = getDb()->query("SELECT * FROM commission_settings ORDER BY level ASC")->fetchAll();
        response($settings);
    }
    
    public function updateCommissionSettings() {
        authenticateAdmin();
        $data = getJsonInput();
        
        if (!isset($data['level']) || !isset($data['commission_rate'])) {
            error('Level and commission_rate required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE commission_settings SET commission_rate = ? WHERE level = ?
        ");
        $stmt->execute([floatval($data['commission_rate']), intval($data['level'])]);
        
        if ($stmt->rowCount() === 0) error('Level not found');
        
        response(null, 'Commission rate updated');
    }
    
    public function getReferSettings() {
        authenticateAdmin();
        
        $settings = getDb()->query("SELECT * FROM refer_settings LIMIT 1")->fetch();
        response($settings ?: []);
    }
    
    public function updateReferSettings() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $updates = [];
        $params = [];
        
        foreach (['title', 'description', 'commission_percentage', 'active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE refer_settings SET " . implode(', ', $updates) . " WHERE id = 1");
        $stmt->execute($params);
        
        response(null, 'Refer settings updated');
    }
    
    public function getWithdrawSettings() {
        authenticateAdmin();
        
        $settings = getDb()->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1")->fetch();
        response($settings ?: []);
    }
    
    public function updateWithdrawSettings() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $updates = [];
        $params = [];
        
        foreach (['min_amount', 'max_amount', 'fee_percentage', 'daily_limit', 'withdrawal_time', 'processing_time'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE withdraw_settings SET " . implode(', ', $updates) . " WHERE active = 1");
        $stmt->execute($params);
        
        response(null, 'Withdraw settings updated');
    }
    
    public function getAllSettings() {
        authenticateAdmin();
        
        $db = getDb();
        
        $commission = $db->query("SELECT * FROM commission_settings ORDER BY level ASC")->fetchAll();
        $refer = $db->query("SELECT * FROM refer_settings LIMIT 1")->fetch();
        $withdraw = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1")->fetch();
        
        response([
            'commission' => $commission,
            'refer' => $refer ?: [],
            'withdraw' => $withdraw ?: []
        ]);
    }
}
