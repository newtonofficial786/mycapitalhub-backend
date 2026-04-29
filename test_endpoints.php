<?php
// Quick test script
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/app/Helpers.php';
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Controllers/PaymentController.php';

// Simulate a token (replace with valid token from login)
$userId = 1; // Change to a real user ID

// Mock authentication
function authenticateMock($userId) {
    // Bypass for testing
    return ['id' => $userId];
}

// Override authenticate for testing
$GLOBALS['mock_user_id'] = $userId;

// Test getWithdrawalSettings
$controller = new PaymentController();

echo "<h2>Testing getWithdrawalSettings</h2>";
try {
    // Manually call with mocked auth
    $db = getDb();
    $stmt = $db->query("SELECT * FROM withdraw_settings WHERE active = 1 LIMIT 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        $settings = [
            'min_amount' => 100,
            'max_amount' => 100000,
            'fee_percentage' => 2,
            'daily_limit' => 50000,
            'withdrawal_time' => '07:00am-05:00pm',
            'processing_time' => '1-24 hours'
        ];
    }
    
    $result = [
        'min_amount' => floatval($settings['min_amount'] ?? 100),
        'max_amount' => floatval($settings['max_amount'] ?? 100000),
        'fee_percentage' => floatval($settings['fee_percentage'] ?? 2),
        'daily_limit' => floatval($settings['daily_limit'] ?? 50000),
        'withdrawal_time' => $settings['withdrawal_time'] ?? '07:00am-05:00pm',
        'processing_time' => $settings['processing_time'] ?? '1-24 hours'
    ];
    
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h2>Testing getRechargeHistory (requires auth)</h2>";
echo "Need valid token to test authenticated endpoints";
