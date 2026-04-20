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
        'url' => getenv('APP_URL') ?: 'http://localhost:8000',
        'api_url' => getenv('API_URL') ?: 'http://localhost:3000/api',
        'env' => getenv('APP_ENV') ?: 'development'
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'dev-secret-key-do-not-use-in-production',
        'expiry' => getenv('JWT_EXPIRY') ?: 86400
    ]
];
