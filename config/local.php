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
    'watchpays' => [
        'merchant_id' => env('WATCHPAYS_MERCHANT_ID', ''),
        'api_key' => env('WATCHPAYS_API_KEY', ''),
        'gateway' => 'https://api.watchpays.com/v1',
        'callback_url' => env('WATCHPAYS_CALLBACK_URL', 'http://localhost:8000/api/payment/watchpays/callback')
    ],
    'debug' => true,
    'display_errors' => true
];
