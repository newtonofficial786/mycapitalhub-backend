<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

$db->exec("
    CREATE TABLE IF NOT EXISTS refer_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT 'Invite your friends and earn commission',
        description TEXT,
        commission_percentage DECIMAL(5,2) DEFAULT 30,
        active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$stmt = $db->query("SELECT COUNT(*) as cnt FROM refer_settings");
if ($stmt->fetch()['cnt'] == 0) {
    $db->exec("INSERT INTO refer_settings (title, description, commission_percentage) VALUES ('Invite your friends and earn 30% commission', 'Invite your friends to join Tide and you will receive commission on their betting activities.', 30)");
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success']);