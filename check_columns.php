<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

echo "<h2>wallet_transactions columns:</h2>";
$stmt = $db->query("SHOW COLUMNS FROM wallet_transactions");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>"; print_r($cols); echo "</pre>";

echo "<h2>withdrawals columns:</h2>";
$stmt = $db->query("SHOW COLUMNS FROM withdrawals");
$cols2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>"; print_r($cols2); echo "</pre>";

// Check if the columns exist
$required = ['bank_name', 'bank_account', 'account_holder', 'ifsc_code'];
echo "<h2>Missing in wallet_transactions:</h2>";
foreach ($required as $col) {
    if (!in_array($col, $cols)) {
        echo "MISSING: $col<br>";
    }
}
