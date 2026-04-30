<?php

require_once __DIR__ . '/../bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

function getJsonInput() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if ($method === 'GET') {
        return $_GET;
    }
    
    if (!empty($_POST)) {
        return array_merge($_POST, [
            'withdrawalPin' => $_POST['withdrawalPin'] ?? $_POST['withdrawal_pin'] ?? '',
            'referrerCode' => $_POST['referrerCode'] ?? $_POST['referrer_code'] ?? '',
            'withdrawal_pin' => $_POST['withdrawal_pin'] ?? $_POST['withdrawalPin'] ?? '',
            'referrer_code' => $_POST['referrer_code'] ?? $_POST['referrerCode'] ?? '',
            'account_holder' => $_POST['account_holder'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? '',
            'account_number' => $_POST['account_number'] ?? '',
            'ifsc_code' => $_POST['ifsc_code'] ?? ''
        ]);
    }
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? [];
    
    if (isset($data['withdrawalPin']) && !isset($data['withdrawal_pin'])) {
        $data['withdrawal_pin'] = $data['withdrawalPin'];
    }
    if (isset($data['referrerCode']) && !isset($data['referrer_code'])) {
        $data['referrer_code'] = $data['referrerCode'];
    }
    
    return $data;
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateReferralCode($mobile) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getConfig() {
    $envFile = __DIR__ . '/../.env';
    $env = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value);
            }
        }
    }
    return [
        'jwt' => [
            'expiry' => (int)($env['JWT_EXPIRY'] ?? 86400)
        ]
    ];
}
