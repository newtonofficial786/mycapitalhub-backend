<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

try {
    $db->exec("ALTER TABLE withdrawals ADD COLUMN ifsc_code VARCHAR(20) DEFAULT NULL");
    echo "Added ifsc_code column to withdrawals\n";
} catch (PDOException $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}

echo "Done.\n";
