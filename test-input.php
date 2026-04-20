<?php

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? '';

file_put_contents(__DIR__ . '/request_log.txt', 
    date('Y-m-d H:i:s') . "\n" .
    "Method: $method\n" .
    "Content-Type: $contentType\n" .
    "Content-Length: $contentLength\n" .
    "Raw Input: " . var_export($raw, true) . "\n" .
    "POST: " . var_export($_POST, true) . "\n" .
    "----------\n",
    FILE_APPEND
);

echo json_encode([
    'method' => $method,
    'content_type' => $contentType,
    'content_length' => $contentLength,
    'raw' => $raw,
    'post' => $_POST
]);