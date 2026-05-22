<?php
// Load environment helpers first
require_once __DIR__ . '/bootstrap.php';

// Security headers - MUST be set before any output
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS - restrict to specific origins
$allowedOrigins = ['http://localhost:5173', 'http://localhost:5174', 'https://mycapitalhub.xyz', 'https://tatainvest-admin.pages.dev', 'https://tatainvest-frontend.pages.dev'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Kolkata');

$env = env('APP_ENV') ?? 'production';
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

$uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$uri = trim($uri, '/');

// Strip subdirectory prefix (e.g., 'tatainvest' or 'backend') but keep 'api'
$scriptDir = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir) {
    $parts = explode('/', $scriptDir);
    foreach ($parts as $part) {
        if ($part && $part !== 'api' && strpos($uri, $part . '/') === 0) {
            $uri = substr($uri, strlen($part . '/'));
        }
    }
}

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

function load($path)
{
    if (file_exists(__DIR__ . $path)) {
        include_once __DIR__ . $path;
        return true;
    }
    return false;
}

try {
    // Test endpoint - no dependencies
    if ($uri === 'api/test' && $method === 'GET') {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'API is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
        ]);
        return;
    }

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
    if ($uri === 'api/maintenance/status' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/MaintenanceController.php');
        $c = new MaintenanceController();
        $c->getStatus();
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
    if ($uri === 'api/payment/methods' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->getRechargeMethods();
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
    if ($uri === 'api/payment/watchpays/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/WatchPaysService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createWatchPaysRecharge();
        return;
    }
    if ($uri === 'api/payment/watchpays/callback' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Models/User.php');
        load('/app/Services/WatchPaysService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->handleWatchPaysCallback();
        return;
    }
    if ($uri === 'api/payment/watchpays/query' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->queryWatchPaysOrder();
        return;
    }
    if ($uri === 'api/payment/galepay/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/GalePayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createGalePayRecharge();
        return;
    }
    if ($uri === 'api/payment/galepay/callback' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Models/User.php');
        load('/app/Services/GalePayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->handleGalePayCallback();
        return;
    }
    if ($uri === 'api/payment/galepay/query' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/GalePayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->queryGalePayOrder();
        return;
    }
    if ($uri === 'api/payment/jazpay/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/JazpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createJazpayRecharge();
        return;
    }
    if ($uri === 'api/payment/jazpay/callback' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Models/User.php');
        load('/app/Services/JazpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->handleJazpayCallback();
        return;
    }
    if ($uri === 'api/payment/jazpay/query' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/JazpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->queryJazpayOrder();
        return;
    }
    if ($uri === 'api/payment/zoxpay/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/ZoxpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->createZoxpayRecharge();
        return;
    }
    if ($uri === 'api/payment/zoxpay/callback' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Models/User.php');
        load('/app/Services/ZoxpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->handleZoxpayCallback();
        return;
    }
    if ($uri === 'api/payment/zoxpay/query' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Services/ZoxpayService.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->queryZoxpayOrder();
        return;
    }
    if ($uri === 'api/payment/result' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/PaymentController.php');
        $c = new PaymentController();
        $c->getPaymentResult($_GET['type'] ?? 'success');
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
    if ($uri === 'api/user/verify-pin' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/UserController.php');
        $c = new UserController();
        $c->verifyAndShowWithdrawalPin();
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

    // Admin endpoints
    if ($uri === 'api/admin/auth/login' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Controllers/Admin/AdminAuthController.php');
        $c = new AdminAuthController();
        $c->login();
        return;
    }
    if ($uri === 'api/admin/auth/logout' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Controllers/Admin/AdminAuthController.php');
        $c = new AdminAuthController();
        $c->logout();
        return;
    }
    if ($uri === 'api/admin/dashboard' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminDashboardController.php');
        $c = new AdminDashboardController();
        $c->getStats();
        return;
    }
    if ($uri === 'api/admin/users' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->getUsers();
        return;
    }
    if ($uri === 'api/admin/users/detail' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->getUser();
        return;
    }
    if ($uri === 'api/admin/users/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->updateUser();
        return;
    }
    if ($uri === 'api/admin/users/adjust-balance' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->adjustBalance();
        return;
    }
    if ($uri === 'api/admin/users/suspend' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->suspendUser();
        return;
    }
    if ($uri === 'api/admin/users/activate' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->activateUser();
        return;
    }
    if ($uri === 'api/admin/users/reset-pin' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminUsersController.php');
        $c = new AdminUsersController();
        $c->resetWithdrawalPin();
        return;
    }
    if ($uri === 'api/admin/withdrawals' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminWithdrawalsController.php');
        $c = new AdminWithdrawalsController();
        $c->getWithdrawals();
        return;
    }
    if ($uri === 'api/admin/withdrawals/approve' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminWithdrawalsController.php');
        $c = new AdminWithdrawalsController();
        $c->approve();
        return;
    }
    if ($uri === 'api/admin/withdrawals/reject' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminWithdrawalsController.php');
        $c = new AdminWithdrawalsController();
        $c->reject();
        return;
    }
    if ($uri === 'api/admin/withdrawals/revert' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminWithdrawalsController.php');
        $c = new AdminWithdrawalsController();
        $c->revertToPending();
        return;
    }
    if ($uri === 'api/admin/recharges' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminRechargesController.php');
        $c = new AdminRechargesController();
        $c->getRecharges();
        return;
    }
    if ($uri === 'api/admin/recharges/approve' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminRechargesController.php');
        $c = new AdminRechargesController();
        $c->approve();
        return;
    }
    if ($uri === 'api/admin/recharges/reject' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminRechargesController.php');
        $c = new AdminRechargesController();
        $c->reject();
        return;
    }
    if ($uri === 'api/admin/transactions' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminTransactionsController.php');
        $c = new AdminTransactionsController();
        $c->getAll();
        return;
    }
    if ($uri === 'api/admin/transactions/update-status' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminTransactionsController.php');
        $c = new AdminTransactionsController();
        $c->updateStatus();
        return;
    }
    if ($uri === 'api/admin/banners' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminBannersController.php');
        $c = new AdminBannersController();
        $c->getAll();
        return;
    }
    if ($uri === 'api/admin/banners/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminBannersController.php');
        $c = new AdminBannersController();
        $c->create();
        return;
    }
    if ($uri === 'api/admin/banners/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminBannersController.php');
        $c = new AdminBannersController();
        $c->update();
        return;
    }
    if ($uri === 'api/admin/banners/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminBannersController.php');
        $c = new AdminBannersController();
        $c->delete();
        return;
    }
    if ($uri === 'api/admin/banners/toggle' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminBannersController.php');
        $c = new AdminBannersController();
        $c->toggleActive();
        return;
    }
    if ($uri === 'api/admin/products' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminProductsController.php');
        $c = new AdminProductsController();
        $c->getAll();
        return;
    }
    if ($uri === 'api/admin/products/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminProductsController.php');
        $c = new AdminProductsController();
        $c->create();
        return;
    }
    if ($uri === 'api/admin/products/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminProductsController.php');
        $c = new AdminProductsController();
        $c->update();
        return;
    }
    if ($uri === 'api/admin/products/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminProductsController.php');
        $c = new AdminProductsController();
        $c->delete();
        return;
    }
    if ($uri === 'api/admin/products/toggle' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminProductsController.php');
        $c = new AdminProductsController();
        $c->toggleActive();
        return;
    }
    if ($uri === 'api/admin/vip/packages' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminVipController.php');
        $c = new AdminVipController();
        $c->getPackages();
        return;
    }
    if ($uri === 'api/admin/vip/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminVipController.php');
        $c = new AdminVipController();
        $c->create();
        return;
    }
    if ($uri === 'api/admin/vip/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminVipController.php');
        $c = new AdminVipController();
        $c->update();
        return;
    }
    if ($uri === 'api/admin/vip/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminVipController.php');
        $c = new AdminVipController();
        $c->delete();
        return;
    }
    if ($uri === 'api/admin/vip/users' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminVipController.php');
        $c = new AdminVipController();
        $c->getUserVips();
        return;
    }
    if ($uri === 'api/admin/settings' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->getAllSettings();
        return;
    }
    if ($uri === 'api/admin/settings/commission' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->getCommissionSettings();
        return;
    }
    if ($uri === 'api/admin/settings/commission/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->updateCommissionSettings();
        return;
    }
    if ($uri === 'api/admin/settings/refer' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->getReferSettings();
        return;
    }
    if ($uri === 'api/admin/settings/refer/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->updateReferSettings();
        return;
    }
    if ($uri === 'api/admin/settings/withdraw' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->getWithdrawSettings();
        return;
    }
    if ($uri === 'api/admin/settings/withdraw/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->updateWithdrawSettings();
        return;
    }
    if ($uri === 'api/admin/settings/level/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->createLevel();
        return;
    }
    if ($uri === 'api/admin/settings/level/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->updateLevel();
        return;
    }
    if ($uri === 'api/admin/settings/level/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->deleteLevel();
        return;
    }
    if ($uri === 'api/admin/maintenance' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/MaintenanceController.php');
        $c = new MaintenanceController();
        $c->getSettings();
        return;
    }
    if ($uri === 'api/admin/maintenance/save' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/MaintenanceController.php');
        $c = new MaintenanceController();
        $c->save();
        return;
    }
    if ($uri === 'api/recharge/settings' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Controllers/RechargeSettingsController.php');
        $c = new RechargeSettingsController();
        $c->index();
        return;
    }
    if ($uri === 'api/admin/recharge/settings' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/RechargeSettingsController.php');
        $c = new RechargeSettingsController();
        $c->adminList();
        return;
    }
    if ($uri === 'api/admin/recharge/settings/save' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/RechargeSettingsController.php');
        $c = new RechargeSettingsController();
        $c->save();
        return;
    }
    if ($uri === 'api/user/level' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Models/User.php');
        load('/app/Controllers/Admin/AdminSettingsController.php');
        $c = new AdminSettingsController();
        $c->getUserLevel();
        return;
    }
    if ($uri === 'api/admin/games/bets' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminGamesController.php');
        $c = new AdminGamesController();
        $c->getBets();
        return;
    }
    if ($uri === 'api/admin/games/results' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminGamesController.php');
        $c = new AdminGamesController();
        $c->getResults();
        return;
    }
    if ($uri === 'api/admin/games/results/add' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminGamesController.php');
        $c = new AdminGamesController();
        $c->addResult();
        return;
    }
    if ($uri === 'api/admin/games/results/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminGamesController.php');
        $c = new AdminGamesController();
        $c->deleteResult();
        return;
    }
    if ($uri === 'api/admin/games/stats' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminGamesController.php');
        $c = new AdminGamesController();
        $c->getStats();
        return;
    }
    if ($uri === 'api/admin/payment-methods' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->getAll();
        return;
    }
    if ($uri === 'api/admin/payment-methods/create' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->create();
        return;
    }
    if ($uri === 'api/admin/payment-methods/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->update();
        return;
    }
    if ($uri === 'api/admin/payment-methods/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->delete();
        return;
    }
    if ($uri === 'api/admin/payment-methods/toggle' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->toggleActive();
        return;
    }
    if ($uri === 'api/admin/payment-methods/set-default' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentMethodsController.php');
        $c = new AdminPaymentMethodsController();
        $c->setDefault();
        return;
    }
    if ($uri === 'api/admin/payment-result' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentResultController.php');
        $c = new AdminPaymentResultController();
        $c->getAll();
        return;
    }
    if ($uri === 'api/admin/payment-result/update' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/AdminPaymentResultController.php');
        $c = new AdminPaymentResultController();
        $c->update();
        return;
    }

    if ($uri === 'api/admin/images' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/ImageManagerController.php');
        $c = new ImageManagerController();
        $c->list();
        return;
    }
    if ($uri === 'api/admin/images/upload' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/ImageManagerController.php');
        $c = new ImageManagerController();
        $c->upload();
        return;
    }
    if ($uri === 'api/admin/images/delete' && $method === 'POST') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/ImageManagerController.php');
        $c = new ImageManagerController();
        $c->delete();
        return;
    }
    if ($uri === 'api/admin/images/details' && $method === 'GET') {
        load('/bootstrap.php');
        load('/config/Database.php');
        load('/app/Helpers.php');
        load('/app/Middleware/AuthMiddleware.php');
        load('/app/Middleware/AdminMiddleware.php');
        load('/app/Controllers/Admin/ImageManagerController.php');
        $c = new ImageManagerController();
        $c->getDetails();
        return;
    }
    if ($uri === 'api/uploads/images' && strpos($uri, '/uploads/images') === 0) {
        $filename = basename($_SERVER['REQUEST_URI']);
        $filepath = __DIR__ . '/uploads/images/' . $filename;
        if ($filename && file_exists($filepath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            return;
        }
        http_response_code(404);
        echo 'Not found';
        return;
    }
    if (strpos($uri, 'uploads/images/') === 0) {
        $filename = basename($uri);
        $filepath = __DIR__ . '/uploads/images/' . $filename;
        if ($filename && file_exists($filepath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            return;
        }
        http_response_code(404);
        echo 'Not found';
        return;
    }
    if (strpos($uri, 'api/uploads/images/') === 0) {
        $filename = basename($uri);
        $filepath = __DIR__ . '/uploads/images/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
        if ($filename && file_exists($filepath) && is_file($filepath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            return;
        }
        error_log("Uploads 404 - uri: $uri, filename: $filename, exists: " . (file_exists($filepath) ? 'yes' : 'no'));
        http_response_code(404);
        echo 'Not found';
        return;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Endpoint not found']);
} catch (Throwable $e) {
    error_log('[API Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}