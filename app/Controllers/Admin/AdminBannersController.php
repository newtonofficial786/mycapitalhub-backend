<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminBannersController {
    public function getAll() {
        authenticateAdmin();
        
        $banners = getDb()->query("SELECT * FROM banners ORDER BY sort_order ASC")->fetchAll();
        response($banners);
    }
    
    public function create() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $title = $data['title'] ?? '';
        $imageUrl = $data['image_url'] ?? '';
        $linkUrl = $data['link_url'] ?? '';
        $sortOrder = intval($data['sort_order'] ?? 0);
        $active = intval($data['active'] ?? 1);
        
        if (empty($title) || empty($imageUrl)) error('Title and image URL required');
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO banners (title, image_url, link_url, sort_order, active) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $imageUrl, $linkUrl, $sortOrder, $active]);
        
        response(['id' => $db->lastInsertId()], 'Banner created');
    }
    
    public function update() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Banner ID required');
        
        $updates = [];
        $params = [];
        
        foreach (['title', 'image_url', 'link_url', 'sort_order', 'active'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) error('No fields to update');
        
        $params[] = $id;
        $db = getDb();
        $stmt = $db->prepare("UPDATE banners SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        response(null, 'Banner updated');
    }
    
    public function delete() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Banner ID required');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Banner deleted');
    }
    
    public function toggleActive() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Banner ID required');
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE banners SET active = 1 - active WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Banner toggled');
    }
}
