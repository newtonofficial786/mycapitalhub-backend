<?php

require_once __DIR__ . '/../bootstrap.php';

// Disable error output to prevent HTML in responses
// Errors should be logged but not displayed
ini_set('display_errors', 0);
error_reporting(0);

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
    // Clear any previous output to ensure pure JSON
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
    $env = env('APP_ENV', 'local');
    $configFile = __DIR__ . '/../config/' . $env . '.php';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/../config/local.php';
    }
    return require $configFile;
}

function debugEndpoint($rawInput, $postData) {
    $logFile = __DIR__ . '/../debug.log';
    $log = date('Y-m-d H:i:s') . ' | Raw: ' . strlen($rawInput) . ' bytes | post: ' . count($postData) . ' | ' . json_encode($postData) . "\n";
    @file_put_contents($logFile, $log, FILE_APPEND);
    
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'raw_len' => strlen($rawInput),
        'raw' => $rawInput,
        'post' => $postData,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
    ]);
    exit;
}