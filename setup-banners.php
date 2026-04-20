<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

function setupBannerTable() {
    $db = getDb();
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100),
            image_url VARCHAR(255) NOT NULL,
            link_url VARCHAR(255),
            sort_order INT DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert sample banners if empty
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM banners");
    if ($stmt->fetch()['cnt'] == 0) {
        $db->exec("INSERT INTO banners (title, image_url, link_url, sort_order) VALUES
            ('Welcome Bonus', 'https://placehold.co/400x200/ff6b35/fff?text=Welcome+Bonus', '/auth/register', 1),
            ('VIP Program', 'https://placehold.co/400x200/4ecdc4/fff?text=VIP+Program', '/vip', 2),
            ('Special Offer', 'https://placehold.co/400x200/95e1d3/333?text=Special+Offer', '/products', 3)
        ");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Banners table created']);
}

setupBannerTable();