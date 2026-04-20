<?php

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$raw = $input;
$parsed = json_decode($input, true);

echo json_encode([
    'raw_input' => $raw,
    'parsed' => $parsed,
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
]);