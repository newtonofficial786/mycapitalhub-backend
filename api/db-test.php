<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$results = [];

$hosts = ['127.0.0.1', 'localhost', 'mysql', 'db'];
$users = ['root', 'najira_user'];
$dbs = ['gaming_platform', 'najira_db', 'tata_db'];

foreach ($hosts as $host) {
    foreach ($users as $user) {
        foreach ($dbs as $db) {
            $key = "$host / $user / $db";
            try {
                $dsn = "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $results[$key] = 'SUCCESS';
            } catch (PDOException $e) {
                $results[$key] = $e->getMessage();
            }
        }
    }
}

echo json_encode(['results' => $results], JSON_PRETTY_PRINT);
