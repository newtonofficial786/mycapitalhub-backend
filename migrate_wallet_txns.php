<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

// Add bank detail columns to wallet_transactions if not exist
$columns = [
    'bank_name' => "VARCHAR(100) DEFAULT NULL",
    'bank_account' => "VARCHAR(50) DEFAULT NULL",
    'account_holder' => "VARCHAR(100) DEFAULT NULL",
    'ifsc_code' => "VARCHAR(20) DEFAULT NULL"
];

foreach ($columns as $col => $def) {
    try {
        $db->exec("ALTER TABLE wallet_transactions ADD COLUMN $col $def");
        echo "Added column: $col\n";
    } catch (PDOException $e) {
        echo "Column $col might already exist or error: " . $e->getMessage() . "\n";
    }
}

echo "Migration done.\n";
