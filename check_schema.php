<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

echo "<h2>wallet_transactions columns:</h2>";
$stmt = $db->query("SHOW COLUMNS FROM wallet_transactions");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>"; print_r($cols); echo "</pre>";

echo "<h2>withdrawals columns:</h2>";
$stmt = $db->query("SHOW COLUMNS FROM withdrawals");
$cols2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>"; print_r($cols2); echo "</pre>";
