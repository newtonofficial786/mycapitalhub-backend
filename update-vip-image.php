<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

try {
    $db->exec("ALTER TABLE vip_packages ADD COLUMN image VARCHAR(255)");
} catch (PDOException $e) {
    // Column already exists
}

$images = [
    'https://placehold.co/400x300/cd7f32/fff?text=Bronze+VIP',
    'https://placehold.co/400x300/c0c0c0/333?text=Silver+VIP',
    'https://placehold.co/400x300/ffd700/333?text=Gold+VIP',
    'https://placehold.co/400x300/e5e4e2/333?text=Platinum+VIP',
    'https://placehold.co/400x300/b9f2ff/333?text=Diamond+VIP',
];

$stmt = $db->query("SELECT id FROM vip_packages ORDER BY id");
$vipPackages = $stmt->fetchAll();

foreach ($vipPackages as $index => $pkg) {
    if (isset($images[$index])) {
        $stmt = $db->prepare("UPDATE vip_packages SET image = ? WHERE id = ?");
        $stmt->execute([$images[$index], $pkg['id']]);
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'updated' => count($vipPackages)]);