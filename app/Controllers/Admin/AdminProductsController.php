<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminProductsController {
    public function getAll() {
        authenticateAdmin();
        
        $products = getDb()->query("SELECT * FROM products ORDER BY price ASC")->fetchAll();
        response($products);
    }
    
    public function create() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $name = $data['name'] ?? '';
        $price = floatval($data['price'] ?? 0);
        $dailyIncome = floatval($data['daily_income'] ?? 0);
        $durationDays = intval($data['duration_days'] ?? 0);
        $image = $data['image'] ?? '';
        $description = $data['description'] ?? '';
        $active = intval($data['active'] ?? 1);
        
        if (empty($name) || $price <= 0) error('Name and valid price required');
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO products (name, price, daily_income, duration_days, image, description, active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $price, $dailyIncome, $durationDays, $image, $description, $active]);
        
        response(['id' => $db->lastInsertId()], 'Product created');
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Product ID required');
        
        $updates = [];
        $params = [];
        
        foreach (['name', 'price', 'daily_income', 'duration_days', 'image', 'description', 'active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $db = getDb();
        $stmt = $db->prepare("UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'Product updated');
    }
    
    public function delete() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Product ID required');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Product deleted');
    }
    
    public function toggleActive() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Product ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE products SET active = 1 - active WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Product toggled');
    }
}
