<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($uri === 'test-includes') {
        include __DIR__ . '/bootstrap.php';
        echo "bootstrap OK\n";
        
        include __DIR__ . '/config/Database.php';
        echo "Database OK\n";
        
        include __DIR__ . '/app/Helpers.php';
        echo "Helpers OK\n";
        
        include __DIR__ . '/app/Middleware/AuthMiddleware.php';
        echo "AuthMiddleware OK\n";
        
        include __DIR__ . '/app/Models/User.php';
        echo "User OK\n";
        
        echo "All includes OK";
        return;
    }
    
    http_response_code(404);
    echo "Not found";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}