<?php
return [
    'db' => [
        'host' => 'sdb-77.hosting.stackcp.net',
        'port' => '3306',
        'database' => 'tatainvest-35303735512d',
        'username' => 'admin-5bc5',
        'password' => 'Newton@786',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Gaming Platform',
        'url' => getenv('APP_URL') ?: 'https://your-domain.com',
        'api_url' => getenv('API_URL') ?: 'https://your-domain.com/api',
        'env' => 'production'
    ],
    'jwt' => [
        'expiry' => getenv('JWT_EXPIRY') ?: 86400
    ],
    'debug' => false,
    'display_errors' => false
];
