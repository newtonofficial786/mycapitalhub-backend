<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

$db->exec("
    CREATE TABLE IF NOT EXISTS withdraw_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        min_amount DECIMAL(15,2) NOT NULL DEFAULT 100,
        max_amount DECIMAL(15,2) NOT NULL DEFAULT 100000,
        fee_percentage DECIMAL(5,2) NOT NULL DEFAULT 2,
        daily_limit DECIMAL(15,2) NOT NULL DEFAULT 50000,
        withdrawal_time VARCHAR(100) DEFAULT '07:00am-05:00pm',
        processing_time VARCHAR(100) DEFAULT '1-24 hours',
        active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$stmt = $db->query("SELECT COUNT(*) as cnt FROM withdraw_settings");
if ($stmt->fetch()['cnt'] == 0) {
    $db->exec("INSERT INTO withdraw_settings (min_amount, max_amount, fee_percentage, daily_limit, withdrawal_time, processing_time) VALUES (100, 100000, 2, 50000, '07:00am-05:00pm', '1-24 hours')");
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success']);