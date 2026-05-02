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
        
        foreach (['min_amount', 'max_amount', 'fee_percentage', 'daily_limit', 'withdrawal_time', 'processing_time', 'close_from', 'close_to'] as $field) {
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
        $levels = $db->query("SELECT * FROM user_level_settings ORDER BY level ASC")->fetchAll();
        
        response([
            'commission' => $commission,
            'refer' => $refer ?: [],
            'withdraw' => $withdraw ?: [],
            'levels' => $levels ?: []
        ]);
    }

    public function createLevel() {
        authenticateAdmin();
        $data = getJsonInput();
        
        if (!isset($data['level']) || !isset($data['name'])) {
            error('Level and name required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("INSERT INTO user_level_settings (level, name, min_recharge, icon, color, benefits) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            intval($data['level']),
            $data['name'],
            floatval($data['min_recharge'] ?? 0),
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $data['benefits'] ?? null
        ]);
        
        response(['id' => $db->lastInsertId()], 'Level created');
    }
    
    public function updateLevel() {
        authenticateAdmin();
        $data = getJsonInput();
        
        if (!isset($data['id'])) {
            error('Level ID required');
        }
        
        $updates = [];
        $params = [];
        
        foreach (['level', 'name', 'min_recharge', 'icon', 'color', 'benefits', 'active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = intval($data['id']);
        $db = getDb();
        $stmt = $db->prepare("UPDATE user_level_settings SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) error('Level not found');
        
        response(null, 'Level updated');
    }
    
    public function deleteLevel() {
        authenticateAdmin();
        $data = getJsonInput();
        
        if (!isset($data['id'])) {
            error('Level ID required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM user_level_settings WHERE id = ?");
        $stmt->execute([intval($data['id'])]);
        
        if ($stmt->rowCount() === 0) error('Level not found');
        
        response(null, 'Level deleted');
    }
    
    public function getUserLevel() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("SELECT total_recharge, level FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        $totalRecharge = floatval($userData['total_recharge'] ?? 0);
        $storedLevel = intval($userData['level'] ?? 0);
        
        $stmt = $db->query("SELECT * FROM user_level_settings WHERE active = 1 ORDER BY min_recharge DESC");
        $levels = $stmt->fetchAll();
        
        $calculatedLevel = 0;
        foreach ($levels as $lvl) {
            if ($totalRecharge >= floatval($lvl['min_recharge'])) {
                $calculatedLevel = intval($lvl['level']);
                break;
            }
        }
        
        $currentLevel = max($storedLevel, $calculatedLevel);
        
        response([
            'level' => $currentLevel,
            'total_recharge' => $totalRecharge,
            'levels' => $levels
        ]);
    }
}
