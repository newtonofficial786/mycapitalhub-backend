<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'gaming_platform',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'Gaming Platform',
        'url' => getenv('APP_URL') ?: 'https://your-domain.com',
        'api_url' => getenv('API_URL') ?: 'https://your-domain.com/api',
        'env' => 'production'
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: '',
        'expiry' => getenv('JWT_EXPIRY') ?: 86400
    ],
    'debug' => false,
    'display_errors' => false
];
