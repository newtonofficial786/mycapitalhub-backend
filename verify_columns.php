<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

echo "=== wallet_transactions columns ===\n";
$stmt = $db->query("SHOW COLUMNS FROM wallet_transactions");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols as $col) {
    echo " - $col\n";
}

echo "\n=== withdrawals columns ===\n";
$stmt = $db->query("SHOW COLUMNS FROM withdrawals");
$cols2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($cols2 as $col) {
    echo " - $col\n";
}

// Check if all needed columns present
$needed = ['bank_name', 'bank_account', 'account_holder', 'ifsc_code'];
echo "\n=== Check wallet_transactions ===\n";
foreach ($needed as $col) {
    $exists = in_array($col, $cols) ? 'YES' : 'NO';
    echo "$col: $exists\n";
}
