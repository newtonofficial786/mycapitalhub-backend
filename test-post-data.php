<?php

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Directly test register
$_SERVER['REQUEST_URI'] = '/api/auth/register';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['mobile' => '9999999999', 'password' => 'test1234', 'withdrawal_pin' => '1234'];

echo "Testing with \$_POST data...\n";

$raw = file_get_contents('php://input');
if (empty($raw)) {
    $raw = json_encode($_POST);
}

echo "Raw input: " . $raw . "\n";
echo "Parsed: " . print_r(json_decode($raw, true), true);