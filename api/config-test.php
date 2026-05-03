<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../config/production.php';
    $db = $config['db'];

    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Test connection and list tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'message' => 'Database connected successfully',
        'host' => $db['host'],
        'database' => $db['database'],
        'username' => $db['username'],
        'tables' => $tables,
        'table_count' => count($tables)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'dsn' => "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}"
    ]);
}
