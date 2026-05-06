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
        'env' => 'production'
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
        'callback_url' => env('YOYOPAY_CALLBACK_URL', '')
    ],
    'debug' => false,
    'display_errors' => false
];
