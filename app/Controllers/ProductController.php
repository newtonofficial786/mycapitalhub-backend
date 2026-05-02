<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';

class ProductController {
    public function getProducts() {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM products WHERE active = 1 ORDER BY price ASC");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        response($products);
    }

    public function getUserProducts() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT up.*, p.name, p.daily_income, p.duration_days,
                   CASE 
                       WHEN up.expiry_date >= CURRENT_DATE AND up.status = 'active' THEN 'active'
                       ELSE 'expired'
                   END as status
            FROM user_products up
            JOIN products p ON up.product_id = p.id
            WHERE up.user_id = ?
            ORDER BY up.purchase_date DESC
        ");
        $stmt->execute([$user['id']]);
        $products = $stmt->fetchAll();
        
        response($products);
    }

    public function purchaseProduct() {
        $user = authenticate();
        $data = getJsonInput();
        
        $productId = intval($data['product_id'] ?? 0);
        
        if ($productId <= 0) {
            error('Invalid product');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            error('Product not found');
        }
        
        $stmt = $db->prepare("SELECT main_wallet FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if ($userData['main_wallet'] < $product['price']) {
            error('Insufficient balance in main wallet');
        }
        
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("SELECT main_wallet FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userBefore = floatval($stmt->fetch()['main_wallet'] ?? 0);
            
            $stmt = $db->prepare("UPDATE users SET main_wallet = main_wallet - ? WHERE id = ?");
            $stmt->execute([$product['price'], $user['id']]);
            
            $expiryDate = date('Y-m-d', strtotime('+' . $product['duration_days'] . ' days'));
            
            $stmt = $db->prepare("
                INSERT INTO user_products (user_id, product_id, expiry_date, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([$user['id'], $productId, $expiryDate]);
            
            $productName = $product['name'] ?? 'Product';
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bet', ?, ?, ?, 'completed', ?, 'main')
            ");
            $stmt->execute([$user['id'], -$product['price'], $userBefore, $userBefore - $product['price'], 'Investment: ' . $productName]);
            
            $db->commit();
            
            response([
                'id' => $db->lastInsertId(),
                'expiry_date' => $expiryDate,
                'daily_income' => $product['daily_income']
            ], 'Product purchased successfully');
        } catch (Exception $e) {
            $db->rollBack();
            error('Purchase failed');
        }
    }

    public function claimDailyIncome() {
        try {
            $user = authenticate();
            
            // Accept both JSON and form-encoded
            $data = [];
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (stripos($contentType, 'application/json') !== false) {
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true) ?? [];
            } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $data = $_POST;
            } else {
                // Try JSON first, fallback to POST
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true) ?? $_POST;
            }
            
            $productId = $data['id'] ?? $data['product_id'] ?? null;
            
            if (!$productId) {
                error('Product ID required. Received: ' . json_encode($data), 400);
            }
            
            $db = getDb();
            $stmt = $db->prepare("
                SELECT up.id, up.last_claimed, p.daily_income, p.name
                FROM user_products up
                JOIN products p ON up.product_id = p.id
                WHERE up.id = ? AND up.user_id = ? AND up.status = 'active'
                AND up.expiry_date >= CURRENT_DATE
            ");
            $stmt->execute([$productId, $user['id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                error('Invalid product or not eligible for claim');
            }
            
            $income = floatval($product['daily_income']);
            
            if ($income <= 0) {
                error('No income available for this product');
            }
            
            $lastClaimed = $product['last_claimed'] ?? null;
            error_log("Product {$productId} last_claimed: " . ($lastClaimed ?? 'null') . ", now: " . date('Y-m-d H:i:s'));
            
            if ($lastClaimed) {
                $last = new DateTime($lastClaimed);
                $next = clone $last;
                $next->add(new DateInterval('P1D'));
                $now = new DateTime();
                if ($now < $next) {
                    $remaining = $next->getTimestamp() - $now->getTimestamp();
                    $hours = floor($remaining / 3600);
                    $minutes = floor(($remaining % 3600) / 60);
                    error("Please wait {$hours}h {$minutes}m before claiming again", 400);
                }
            }
            
            $stmt = $db->prepare("SELECT stable_wallet FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $balanceBefore = floatval($stmt->fetch()['stable_wallet'] ?? 0);
            $balanceAfter = $balanceBefore + $income;
            
            $stmt = $db->prepare("UPDATE users SET stable_wallet = ? WHERE id = ?");
            $stmt->execute([$balanceAfter, $user['id']]);
            
            $stmt = $db->prepare("UPDATE user_products SET last_claimed = NOW() WHERE id = ?");
            $stmt->execute([$productId]);
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bonus', ?, ?, ?, 'completed', ?, 'stable')
            ");
            $stmt->execute([$user['id'], $income, $balanceBefore, $balanceAfter, "Daily income: {$product['name']}"]);
            
            response([
                'amount' => $income,
                'product_id' => $productId,
                'product_name' => $product['name'],
                'next_claim_at' => (new DateTime('+1 day'))->format('Y-m-d H:i:s')
            ], 'Income claimed successfully');
        } catch (Throwable $e) {
            error('Server error: ' . $e->getMessage(), 500);
        }
    }
}

class VipController {
    public function getVipPackages() {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM vip_packages WHERE active = 1 ORDER BY level ASC");
        $stmt->execute();
        $packages = $stmt->fetchAll();
        
        response($packages);
    }
    
    public function getUserVip() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT uv.*, vp.name, vp.daily_income, vp.level, vp.min_recharge,
                   CASE 
                       WHEN vp.active = 1 AND uv.user_id IS NOT NULL THEN 'active'
                       ELSE 'expired'
                   END as status
            FROM user_vip uv
            JOIN vip_packages vp ON uv.vip_package_id = vp.id
            WHERE uv.user_id = ?
            ORDER BY uv.id DESC
        ");
        $stmt->execute([$user['id']]);
        $vips = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT level FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userLevel = $stmt->fetch();
        
        if (empty($vips)) {
            $userLevel = intval($userLevel['level'] ?? 0);
            $vips = [ [
                'id' => 0,
                'vip_package_id' => null,
                'level' => $userLevel,
                'name' => 'Bronze VIP',
                'daily_income' => 0,
                'status' => 'active',
                'last_claimed' => null
            ] ];
        }
        
        response($vips);
    }
    
    public function purchaseVip() {
        $user = authenticate();
        $data = getJsonInput();
        
        $packageId = intval($data['package_id'] ?? 0);
        
        if ($packageId <= 0) {
            error('Invalid package');
        }
        
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM vip_packages WHERE id = ? AND active = 1");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();
        
        if (!$package) {
            error('Package not found');
        }
        
        $stmt = $db->prepare("SELECT main_wallet, level FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $currentUser = $stmt->fetch();
        $currentLevel = intval($currentUser['level'] ?? 0);
        $currentBalance = floatval($currentUser['main_wallet'] ?? 0);
        
        $price = floatval($package['min_recharge'] ?? 0);
        
        if ($currentLevel >= intval($package['level'])) {
            error('You already have this VIP level or higher. Current: ' . $currentLevel . ', Package: ' . $package['level']);
        }
        
        if ($currentBalance < $price) {
            error('Insufficient balance in main wallet. Your balance: ' . $currentBalance . ', Price: ' . $price);
        }
        
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("UPDATE users SET main_wallet = main_wallet - ?, level = ? WHERE id = ?");
            $stmt->execute([$price, $package['level'], $user['id']]);
            
            $stmt = $db->prepare("DELETE FROM user_vip WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            $stmt = $db->prepare("
                INSERT INTO user_vip (user_id, vip_package_id, total_earned)
                VALUES (?, ?, 0)
            ");
            $stmt->execute([$user['id'], $packageId]);
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bet', ?, ?, ?, 'completed', ?, 'main')
            ");
            $stmt->execute([$user['id'], -$price, $currentUser['main_wallet'], $currentUser['main_wallet'] - $price, 'VIP Upgrade: ' . $package['name']]);
            
            $db->commit();
            
            response([
                'id' => $db->lastInsertId(),
                'package_name' => $package['name'],
                'daily_income' => $package['daily_income'],
                'level' => $package['level']
            ], 'VIP upgraded successfully');
        } catch (Exception $e) {
            $db->rollBack();
            error('Purchase failed');
        }
    }

    public function claimVipIncome() {
        try {
            $user = authenticate();
            
            $data = [];
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true) ?? [];
            } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $data = $_POST;
            } else {
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true) ?? $_POST;
            }
            
            $packageId = $data['id'] ?? $data['package_id'] ?? null;
            
            if (!$packageId) {
                error('VIP package ID required. Received: ' . json_encode($data), 400);
            }
            
            $db = getDb();
            $stmt = $db->prepare("
                SELECT uv.id, uv.last_claimed, vp.daily_income, vp.name
                FROM user_vip uv
                JOIN vip_packages vp ON uv.vip_package_id = vp.id
                WHERE uv.id = ? AND uv.user_id = ?
            ");
            $stmt->execute([$packageId, $user['id']]);
            $vip = $stmt->fetch();
            
            if (!$vip) {
                error('Invalid VIP package');
            }
            
            $income = floatval($vip['daily_income']);
            
            if ($income <= 0) {
                error('No VIP income available');
            }
            
            $lastClaimed = $vip['last_claimed'] ?? null;
            error_log("VIP {$packageId} last_claimed: " . ($lastClaimed ?? 'null') . ", now: " . date('Y-m-d H:i:s'));
            
            if ($lastClaimed) {
                $last = new DateTime($lastClaimed);
                $next = clone $last;
                $next->add(new DateInterval('P1D'));
                $now = new DateTime();
                if ($now < $next) {
                    $remaining = $next->getTimestamp() - $now->getTimestamp();
                    $hours = floor($remaining / 3600);
                    $minutes = floor(($remaining % 3600) / 60);
                    error("Please wait {$hours}h {$minutes}m before claiming again", 400);
                }
            }
            
            $stmt = $db->prepare("SELECT vip_wallet FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $balanceBefore = floatval($stmt->fetch()['vip_wallet'] ?? 0);
            $balanceAfter = $balanceBefore + $income;
            
            $stmt = $db->prepare("UPDATE user_vip SET total_earned = total_earned + ?, last_claimed = NOW() WHERE id = ?");
            $stmt->execute([$income, $packageId]);
            
            $stmt = $db->prepare("UPDATE users SET vip_wallet = ? WHERE id = ?");
            $stmt->execute([$balanceAfter, $user['id']]);
            
            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bonus', ?, ?, ?, 'completed', ?, 'vip')
            ");
            $stmt->execute([$user['id'], $income, $balanceBefore, $balanceAfter, 'VIP Daily Income: ' . $vip['name']]);
            
            response([
                'amount' => $income,
                'package_id' => $packageId,
                'package_name' => $vip['name'],
                'next_claim_at' => (new DateTime('+1 day'))->format('Y-m-d H:i:s')
            ], 'VIP income claimed');
        } catch (Throwable $e) {
            error('Server error: ' . $e->getMessage(), 500);
        }
    }
}
