<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

// Check existing columns
$existing = $db->query("SHOW COLUMNS FROM wallet_transactions LIKE 'bank_name'")->fetchAll();
$hasBankName = count($existing) > 0;

if (!$hasBankName) {
    $queries = [
        "ALTER TABLE wallet_transactions ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE wallet_transactions ADD COLUMN bank_account VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE wallet_transactions ADD COLUMN account_holder VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE wallet_transactions ADD COLUMN ifsc_code VARCHAR(20) DEFAULT NULL",
    ];

    foreach ($queries as $sql) {
        try {
            $db->exec($sql);
            echo "OK: $sql\n";
        } catch (PDOException $e) {
            echo "ERROR: $sql\nMsg: " . $e->getMessage() . "\n\n";
        }
    }
    echo "Added bank detail columns to wallet_transactions.\n";
} else {
    echo "wallet_transactions already has bank detail columns.\n";
}

echo "\nDone.\n";
