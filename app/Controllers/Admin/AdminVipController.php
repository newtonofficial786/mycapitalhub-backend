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
        $price = floatval($data['price'] ?? 0);
        $dailyIncome = floatval($data['daily_income'] ?? 0);
        $level = intval($data['level'] ?? 0);
        $active = intval($data['active'] ?? 1);
        
        if (empty($name)) error('Name required');
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO vip_packages (name, min_recharge, price, daily_income, level, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $minRecharge, $price, $dailyIncome, $level, $active]);
        
        response(['id' => $db->lastInsertId()], 'VIP package created');
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Package ID required');
        
        $updates = [];
        $params = [];
        
        foreach (['name', 'min_recharge', 'price', 'daily_income', 'level', 'active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $db = getDb();
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
