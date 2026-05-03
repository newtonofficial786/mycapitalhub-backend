<?php

require_once __DIR__ . '/bootstrap.php';

$env = env('APP_ENV') ?? 'production';
if ($env !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

if ($uri === 'test') {
    header('Content-Type: application/json');
    echo json_encode(['test' => 'ok']);
    return;
}

if ($uri === 'api/banners') {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/config/Database.php';
    require_once __DIR__ . '/app/Helpers.php';
    require_once __DIR__ . '/app/Controllers/BannerController.php';
    
    $controller = new BannerController();
    $controller->getBanners();
    return;
}

if ($uri === 'api/user/profile') {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/config/Database.php';
    require_once __DIR__ . '/app/Helpers.php';
    require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
    require_once __DIR__ . '/app/Models/User.php';
    require_once __DIR__ . '/app/Controllers/UserController.php';
    
    $controller = new UserController();
    $controller->profile();
    return;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Not found']);