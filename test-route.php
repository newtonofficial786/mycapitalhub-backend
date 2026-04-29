<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$uri = '';
if (isset($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = trim($uri, '/');
}

try {
    file_put_contents(__DIR__ . '/test.log', "Start: $uri\n", FILE_APPEND);
    
    if ($uri === 'api/products/user') {
        require_once __DIR__ . '/bootstrap.php';
        require_once __DIR__ . '/config/Database.php';
        
        require_once __DIR__ . '/app/Helpers.php';
        
        require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
        require_once __DIR__ . '/app/Models/User.php';
        
        $user = authenticate();
        file_put_contents(__DIR__ . '/test.log', "Auth: " . $user['id'] . "\n", FILE_APPEND);
        
        require_once __DIR__ . '/app/Controllers/ProductController.php';
        
        $c = new ProductController();
        $c->getUserProducts();
        return;
    }
    
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not found', 'uri' => $uri]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}