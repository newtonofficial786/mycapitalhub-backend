<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminPaymentMethodsController {
    
    public function getAll() {
        authenticateAdmin();
        
        $db = getDb();
        $items = $db->query("SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC")->fetchAll();
        response($items);
    }
    
    public function create() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $name = $data['name'] ?? '';
        $subtitle = $data['subtitle'] ?? '';
        $backendKey = $data['backend_key'] ?? '';
        $iconName = $data['icon_name'] ?? 'credit-card';
        $minAmount = floatval($data['min_amount'] ?? 100);
        $maxAmount = floatval($data['max_amount'] ?? 100000);
        $processingTime = $data['processing_time'] ?? '';
        $instructions = $data['instructions'] ?? '';
        $active = intval($data['active'] ?? 1);
        $sortOrder = intval($data['sort_order'] ?? 0);
        
        if (empty($name)) error('Name is required');
        if (empty($backendKey)) error('Backend key is required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT id FROM payment_methods WHERE backend_key = ?");
        $stmt->execute([$backendKey]);
        if ($stmt->fetch()) error('Backend key already exists');
        
        $stmt = $db->prepare("
            INSERT INTO payment_methods (name, subtitle, backend_key, icon_name, min_amount, max_amount, processing_time, instructions, active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $subtitle, $backendKey, $iconName, $minAmount, $maxAmount, $processingTime, $instructions, $active, $sortOrder]);
        
        response(['id' => $db->lastInsertId()], 'Payment method created');
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT id FROM payment_methods WHERE backend_key = ? AND id != ?");
        $stmt->execute([$data['backend_key'] ?? '', $id]);
        if ($stmt->fetch()) error('Backend key already exists');
        
        $updates = [];
        $params = [];
        
        $fields = ['name', 'subtitle', 'backend_key', 'icon_name', 'processing_time', 'instructions'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['min_amount'])) {
            $updates[] = "min_amount = ?";
            $params[] = floatval($data['min_amount']);
        }
        if (isset($data['max_amount'])) {
            $updates[] = "max_amount = ?";
            $params[] = floatval($data['max_amount']);
        }
        if (isset($data['active'])) {
            $updates[] = "active = ?";
            $params[] = intval($data['active']);
        }
        if (isset($data['sort_order'])) {
            $updates[] = "sort_order = ?";
            $params[] = intval($data['sort_order']);
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE payment_methods SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'Payment method updated');
    }
    
    public function delete() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('ID required');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Payment method deleted');
    }
    
    public function toggleActive() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE payment_methods SET active = 1 - active WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Toggled');
    }

    public function setDefault() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE payment_methods SET is_default = 0");
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Default gateway updated');
    }
}
