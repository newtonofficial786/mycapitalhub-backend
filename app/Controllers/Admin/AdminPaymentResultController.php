<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminPaymentResultController {
    
    public function getAll() {
        authenticateAdmin();
        
        $db = getDb();
        $items = $db->query("SELECT * FROM payment_result ORDER BY FIELD(status_type, 'success', 'failed', 'error')")->fetchAll();
        response($items);
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('ID required');
        
        $db = getDb();
        $stmt = $db->prepare("SELECT id FROM payment_result WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Record not found');
        
        $updates = [];
        $params = [];
        
        $fields = ['title', 'message', 'sub_message', 'btn_text', 'btn_link', 'icon', 'bg_color', 'icon_color'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE payment_result SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'Payment result page updated');
    }
}
