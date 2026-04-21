<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();
$stmt = $db->query("SHOW TABLES LIKE 'user_bank_details'");
$tableExists = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'table_exists' => (bool)$tableExists,
    'test_insert' => 'Run /api/test-save-bank to test'
]);