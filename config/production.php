<?php
return [
    'db' => [
        'host' => env('DB_HOST_PROD', 'sdb-77.hosting.stackcp.net'),
        'port' => env('DB_PORT_PROD', '3306'),
        'database' => env('DB_DATABASE_PROD', 'tatainvest-35303735512d'),
        'username' => env('DB_USERNAME_PROD', 'admin-5bc5'),
        'password' => env('DB_PASSWORD_PROD', 'Newton@786'),
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Gaming Platform',
        'url' => env('APP_URL', 'https://your-domain.com'),
        'api_url' => env('API_URL', 'https://your-domain.com/api'),
        'frontend_url' => env('FRONTEND_URL', 'https://your-domain.com'),
        'env' => 'production'
    ],
    'jwt' => [
        'expiry' => env('JWT_EXPIRY', 86400)
    ],
    'watchpays' => [
        'merchant_id' => env('WATCHPAYS_MERCHANT_ID', ''),
        'api_key' => env('WATCHPAYS_API_KEY', ''),
        'gateway' => 'https://api.watchpays.com/v1',
        'callback_url' => env('WATCHPAYS_CALLBACK_URL', env('APP_URL', 'https://your-domain.com') . '/api/payment/watchpays/callback')
    ],
    'debug' => false,
    'display_errors' => false
];
