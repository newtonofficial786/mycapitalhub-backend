<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/Helpers.php';
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/Models/User.php';

// Simulate claim request
$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/claim_debug.log', 
    date('Y-m-d H:i:s') . " - Raw: " . $raw . "\n" .
    "POST: " . print_r($_POST, true) . "\n" .
    "GET: " . print_r($_GET, true) . "\n" .
    "-----------\n",
    FILE_APPEND
);

// Parse JSON
$data = json_decode($raw, true);
file_put_contents(__DIR__ . '/claim_debug.log', 
    "Decoded: " . print_r($data, true) . "\n",
    FILE_APPEND
);

echo json_encode(['received' => $data, 'raw' => $raw]);
