<?php

require_once __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/Helpers.php';
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UserController.php';
require_once __DIR__ . '/app/Controllers/GameController.php';
require_once __DIR__ . '/app/Controllers/IncomeController.php';
require_once __DIR__ . '/app/Controllers/PaymentController.php';
require_once __DIR__ . '/app/Controllers/TeamController.php';
require_once __DIR__ . '/app/Controllers/ProductController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$rawInput = file_get_contents('php://input');
$logFile = __DIR__ . '/req.log';
$log = date('Y-m-d H:i:s') . " $method $uri ct:" . ($_SERVER['CONTENT_TYPE'] ?? '') . " raw:" . strlen($rawInput) . " post:" . count($_POST) . " postdata:" . json_encode($_POST) . "\n";
file_put_contents($logFile, $log, FILE_APPEND);

if (empty($rawInput) && !empty($_POST)) {
    $rawInput = json_encode($_POST);
}

if (empty($rawInput) && !empty($_POST)) {
    $rawInput = json_encode($_POST);
}

try {
    $authController = new AuthController();
    $userController = new UserController();
    $gameController = new GameController();
    $incomeController = new IncomeController();
    $paymentController = new PaymentController();
    $teamController = new TeamController();
    $productController = new ProductController();
    $vipController = new VipController();

    $appEnv = env('APP_ENV', 'local');

    if ($appEnv !== 'production') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    match (true) {
        $uri === 'api/auth/login' && $method === 'POST' => $authController->login(),
        $uri === 'api/auth/register' && $method === 'POST' => $authController->register(),
        $uri === 'api/auth/logout' && $method === 'POST' => $authController->logout(),

        $uri === 'api/user/profile' && $method === 'GET' => $userController->profile(),
        $uri === 'api/user/profile' && $method === 'PUT' => $userController->updateProfile(),
        $uri === 'api/user/password' && $method === 'PUT' => $userController->changePassword(),
        $uri === 'api/user/wallet' && $method === 'GET' => $userController->getWalletInfo(),

        $uri === 'api/games/bet' && $method === 'POST' => $gameController->placeBet(),
        $uri === 'api/games/history' && $method === 'GET' => $gameController->getHistory(),
        $uri === 'api/games/results' && $method === 'GET' => $gameController->getGameResults(),
        $uri === 'api/games/period' && $method === 'GET' => $gameController->getCurrentPeriod(),

        $uri === 'api/income/transactions' && $method === 'GET' => $incomeController->getTransactions(),
        $uri === 'api/income/summary' && $method === 'GET' => $incomeController->getSummary(),
        $uri === 'api/income/daily' && $method === 'GET' => $incomeController->getDailyIncome(),

        $uri === 'api/payment/recharge' && $method === 'POST' => $paymentController->createRecharge(),
        $uri === 'api/payment/recharge/methods' && $method === 'GET' => $paymentController->getRechargeMethods(),
        $uri === 'api/payment/recharge/history' && $method === 'GET' => $paymentController->getRechargeHistory(),
        $uri === 'api/payment/recharge/confirm' && $method === 'POST' => $paymentController->confirmRecharge(getJsonInput()['id'] ?? 0),

        $uri === 'api/payment/withdraw' && $method === 'POST' => $paymentController->createWithdrawal(),
        $uri === 'api/payment/withdraw/history' && $method === 'GET' => $paymentController->getWithdrawalHistory(),
        $uri === 'api/payment/withdraw/info' && $method === 'GET' => $paymentController->getWithdrawalInfo(),

        $uri === 'api/team' && $method === 'GET' => $teamController->getTeam(),
        $uri === 'api/team/commission' && $method === 'GET' => $teamController->getCommission(),
        $uri === 'api/team/invite' && $method === 'GET' => $teamController->getInviteInfo(),
        $uri === 'api/team/rewards' && $method === 'GET' => $teamController->getInviteRewards(),

        $uri === 'api/products' && $method === 'GET' => $productController->getProducts(),
        $uri === 'api/products/user' && $method === 'GET' => $productController->getUserProducts(),
        $uri === 'api/products/purchase' && $method === 'POST' => $productController->purchaseProduct(),
        $uri === 'api/products/claim' && $method === 'POST' => $productController->claimDailyIncome(),

        $uri === 'api/vip/packages' && $method === 'GET' => $vipController->getVipPackages(),
        $uri === 'api/vip/user' && $method === 'GET' => $vipController->getUserVip(),
        $uri === 'api/vip/claim' && $method === 'POST' => $vipController->claimVipIncome(),
        
        $uri === 'api/debug' && $method === 'POST' => debugEndpoint($rawInput, $_POST),

        default => error('Endpoint not found', 404)
    };
} catch (PDOException $e) {
    $appEnv = env('APP_ENV', 'local');
    if ($appEnv !== 'production') {
        error('Database error: ' . $e->getMessage(), 500);
    } else {
        error('Database error', 500);
    }
} catch (Exception $e) {
    error($e->getMessage(), 400);
}