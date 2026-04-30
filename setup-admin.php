<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Add is_admin column if not exists
    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
    if (!$columnCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER status");
        echo "Added is_admin column to users table\n";
    } else {
        echo "is_admin column already exists\n";
    }
    
    // Add last_login column if not exists
    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'")->fetch();
    if (!$columnCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at");
        echo "Added last_login column to users table\n";
    } else {
        echo "last_login column already exists\n";
    }
    
    echo "Admin setup complete\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
