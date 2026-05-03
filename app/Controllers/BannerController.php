<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class BannerController {
    public function getBanners() {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
        $stmt->execute();
        $banners = $stmt->fetchAll();
        
        response($banners);
    }
    
    public function createBanner() {
        authenticate();
        $data = getJsonInput();
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO banners (title, image_url, link_url, sort_order, active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'] ?? '',
            $data['image_url'],
            $data['link_url'] ?? '',
            $data['sort_order'] ?? 0,
            $data['active'] ?? 1
        ]);
        
        response(['id' => $db->lastInsertId()], 'Banner created');
    }
    
    public function updateBanner() {
        authenticate();
        $data = getJsonInput();
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            error('Banner ID required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE banners SET title = ?, image_url = ?, link_url = ?, sort_order = ?, active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'] ?? '',
            $data['image_url'],
            $data['link_url'] ?? '',
            $data['sort_order'] ?? 0,
            $data['active'] ?? 1,
            $id
        ]);
        
        response(null, 'Banner updated');
    }
    
    public function deleteBanner() {
        authenticate();
        $data = getJsonInput();
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            error('Banner ID required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Banner deleted');
    }
}