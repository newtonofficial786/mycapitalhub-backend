<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($uri === '' || $uri === '/') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'API Running']);
    return;
}

function load($path) {
    if (file_exists(__DIR__ . $path)) {
        include __DIR__ . $path;
        return true;
    }
    return false;
}

try {
    // Public endpoints
    if ($uri === 'api/banners' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/BannerController.php');
        $c = new BannerController();
        $c->getBanners();
        return;
    }
    if ($uri === 'api/products' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/ProductController.php');
        $c = new ProductController();
        $c->getProducts();
        return;
    }
    if ($uri === 'api/vip/packages' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/ProductController.php');
        $c = new VipController();
        $c->getVipPackages();
        return;
    }
    if ($uri === 'api/auth/register' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/AuthController.php');
        $c = new AuthController();
        $c->register();
        return;
    }
    if ($uri === 'api/auth/login' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/AuthController.php');
        $c = new AuthController();
        $c->login();
        return;
    }
    if ($uri === 'api/auth/logout' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/AuthController.php');
        $c = new AuthController();
        $c->logout();
        return;
    }
    
    // Auth required endpoints
    if ($uri === 'api/user/profile' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/UserController.php');
        $c = new UserController();
        $c->profile();
        return;
    }
    if ($uri === 'api/user/wallet' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/UserController.php');
        $c = new UserController();
        $c->getWalletInfo();
        return;
    }
    if ($uri === 'api/products/user' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new ProductController();
        $c->getUserProducts();
        return;
    }
    if ($uri === 'api/vip/user' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new VipController();
        $c->getUserVip();
        return;
    }
    if ($uri === 'api/products/purchase' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new ProductController();
        $c->purchaseProduct();
        return;
    }
    if ($uri === 'api/products/claim' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new ProductController();
        $c->claimDailyIncome();
        return;
    }
    if ($uri === 'api/vip/purchase' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new VipController();
        $c->purchaseVip();
        return;
    }
    if ($uri === 'api/vip/claim' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ProductController.php');
        $c = new VipController();
        $c->claimVipIncome();
        return;
    }
    if ($uri === 'api/income/transactions' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/IncomeController.php');
        $c = new IncomeController();
        $c->getTransactions();
        return;
    }
    if ($uri === 'api/income/summary' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/IncomeController.php');
        $c = new IncomeController();
        $c->getSummary();
        return;
    }
    if ($uri === 'api/payment/recharge' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createRecharge();
        return;
    }
    if ($uri === 'api/payment/recharge/history' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->getRechargeHistory();
        return;
    }
    if ($uri === 'api/payment/withdraw' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createWithdrawal();
        return;
    }
    if ($uri === 'api/payment/withdraw/history' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->getWithdrawalHistory();
        return;
    }
    if ($uri === 'api/payment/withdraw/info' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->getWithdrawalInfo();
        return;
    }
    if ($uri === 'api/team' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/TeamController.php');
        $c = new TeamController();
        $c->getTeam();
        return;
    }
    if ($uri === 'api/team/invite' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/TeamController.php');
        $c = new TeamController();
        $c->getInviteInfo();
        return;
    }
    if ($uri === 'api/user/bank' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/BankController.php');
        $c = new BankController();
        $c->getBankDetails();
        return;
    }
    if ($uri === 'api/user/bank' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/BankController.php');
        $c = new BankController();
        $c->saveBankDetails();
        return;
    }
    if ($uri === 'api/withdraw/settings' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/WithdrawSettingsController.php');
        $c = new WithdrawSettingsController();
        $c->getSettings();
        return;
    }
    if ($uri === 'api/refer/settings' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/ReferSettingsController.php');
        $c = new ReferSettingsController();
        $c->getSettings();
        return;
    }
    if ($uri === 'api/game/place-bet' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/GameController.php');
        $c = new GameController();
        $c->placeBet();
        return;
    }
    if ($uri === 'api/game/history' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/GameController.php');
        $c = new GameController();
        $c->getHistory();
        return;
    }
    if ($uri === 'api/game/results' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/GameController.php');
        $c = new GameController();
        $c->getGameResults();
        return;
    }
    if ($uri === 'api/game/current-period' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/GameController.php');
        $c = new GameController();
        $c->getCurrentPeriod();
        return;
    }
    
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint not found']);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}