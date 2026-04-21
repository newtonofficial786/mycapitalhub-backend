<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

$db->exec("
    CREATE TABLE IF NOT EXISTS user_bank_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        account_holder VARCHAR(100) NOT NULL,
        bank_name VARCHAR(100),
        account_number VARCHAR(50) NOT NULL,
        ifsc_code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'User bank details table created']);