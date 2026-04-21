<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

$images = [
    'https://placehold.co/600x400/22c55e/fff?text=Starter+Pack',
    'https://placehold.co/600x400/3b82f6/fff?text=Silver+Package',
    'https://placehold.co/600x400/eab308/333?text=Gold+Package',
    'https://placehold.co/600x400/a855f7/fff?text=Platinum+Package',
];

$stmt = $db->query("SELECT id FROM products ORDER BY id");
$products = $stmt->fetchAll();

foreach ($products as $index => $pkg) {
    if (isset($images[$index])) {
        $stmt = $db->prepare("UPDATE products SET image = ? WHERE id = ?");
        $stmt->execute([$images[$index], $pkg['id']]);
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'updated' => count($products)]);