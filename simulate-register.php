<?php

define('REQUEST_TEST', true);

require_once __DIR__ . '/bootstrap.php';

$_SERVER['REQUEST_URI'] = '/api/auth/register';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$input = '{"mobile":"9123456780","password":"test1234","withdrawal_pin":"1234"}';

$stream = fopen('php://memory', 'r+');
fwrite($stream, $input);
rewind($stream);

class MockInputStream {
    private $stream;
    private $position;
    
    public function __construct($data) {
        $this->stream = $data;
        $this->position = 0;
    }
    
    public function read($count) {
        $data = substr($this->stream, $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }
}

$GLOBALS['mock_stream'] = new MockInputStream($input);

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/Helpers.php';
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';

$ctrl = new AuthController();

try {
    $reflection = new ReflectionClass($ctrl);
    $method = $reflection->getMethod('register');
    $method->invoke($ctrl);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}