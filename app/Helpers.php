<?php

require_once __DIR__ . '/../bootstrap.php';

function response($data = null, $message = 'Success', $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $status >= 200 && $status < 400,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function error($message = 'Error', $status = 400) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateReferralCode($mobile) {
    return strtoupper(substr($mobile, -6) . bin2hex(random_bytes(2)));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getConfig() {
    $env = env('APP_ENV', 'local');
    $configFile = __DIR__ . '/../config/' . $env . '.php';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/../config/local.php';
    }
    return require $configFile;
}