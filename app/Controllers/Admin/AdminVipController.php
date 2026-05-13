<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminVipController {
    public function getPackages() {
        authenticateAdmin();
        
        $packages = getDb()->query("SELECT * FROM vip_packages ORDER BY level ASC")->fetchAll();
        response($packages);
    }
    
    public function create() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $name = $data['name'] ?? '';
        $minRecharge = floatval($data['min_recharge'] ?? 0);
        $dailyIncome = floatval($data['daily_income'] ?? 0);
        $rewardAmount = floatval($data['reward_amount'] ?? 0);
        $waitMinutes = intval($data['wait_minutes'] ?? 60);
        $level = intval($data['level'] ?? 0);
        $active = intval($data['active'] ?? 1);
        
        if (empty($name)) error('Name required');
        
        $db = getDb();
        $colorFrom = $data['color_from'] ?? '#6b7280';
        $colorTo = $data['color_to'] ?? '#1f2937';
        
        $stmt = $db->prepare("
            INSERT INTO vip_packages (name, min_recharge, daily_income, reward_amount, wait_minutes, level, active, color_from, color_to)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $minRecharge, $dailyIncome, $rewardAmount, $waitMinutes, $level, $active, $colorFrom, $colorTo]);
        
        response(['id' => $db->lastInsertId()], 'VIP package created');
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Package ID required');
        
        $db = getDb();
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            if ($value === null || $value === '') continue;
            
            if (in_array($key, ['min_recharge', 'daily_income', 'reward_amount'])) {
                $updates[] = "$key = ?";
                $params[] = floatval($value);
            } elseif (in_array($key, ['wait_minutes', 'level', 'active'])) {
                $updates[] = "$key = ?";
                $params[] = intval($value);
            } else {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE vip_packages SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'VIP package updated');
    }
    
    public function delete() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Package ID required');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM vip_packages WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'VIP package deleted');
    }
    
    public function getUserVips() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 20), 100);
        $offset = intval($data['offset'] ?? 0);
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT uv.*, u.mobile, vp.name as package_name, vp.level as vip_level
            FROM user_vip uv
            JOIN users u ON uv.user_id = u.id
            JOIN vip_packages vp ON uv.vip_package_id = vp.id
            ORDER BY uv.start_date DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        
        response($stmt->fetchAll());
    }
}
