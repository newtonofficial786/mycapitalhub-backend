<?php
return [
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'gaming_platform'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Gaming Platform',
        'url' => env('APP_URL', 'http://localhost:8000'),
        'api_url' => env('API_URL', 'http://localhost:8000/api'),
        'env' => 'local'
    ],
    'jwt' => [
        'expiry' => env('JWT_EXPIRY', 86400)
    ],
    'yoyopay' => [
        'merchant_id' => env('YOYOPAY_MERCHANT_ID', ''),
        'secret_key' => env('YOYOPAY_SECRET_KEY', ''),
        'country_code' => env('YOYOPAY_COUNTRY_CODE', 'IN'),
        'pay_type' => env('YOYOPAY_PAY_TYPE', 'IMPS'),
        'gateway' => env('YOYOPAY_GATEWAY', 'https://merchant.yoyopays.com'),
        'callback_url' => env('YOYOPAY_CALLBACK_URL', 'http://localhost:8000/api/yoyopay/callback')
    ],
    'debug' => true,
    'display_errors' => true
];
