<?php

require_once __DIR__ . '/bootstrap.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

header('Content-Type: application/json');
echo json_encode([
    'received' => $data,
    'post' => $_POST,
    'auth' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'yes' : 'no'
]);