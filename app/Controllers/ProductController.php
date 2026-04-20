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
            SELECT up.*, p.name, p.daily_income, p.duration_days
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
        
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if ($userData['balance'] < $product['price']) {
            error('Insufficient balance');
        }
        
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$product['price'], $user['id']]);
            
            $expiryDate = date('Y-m-d', strtotime('+' . $product['duration_days'] . ' days'));
            
            $stmt = $db->prepare("
                INSERT INTO user_products (user_id, product_id, expiry_date, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([$user['id'], $productId, $expiryDate]);
            
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
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT up.*, p.daily_income, p.duration_days
            FROM user_products up
            JOIN products p ON up.product_id = p.id
            WHERE up.user_id = ? AND up.status = 'active'
            AND up.expiry_date >= CURRENT_DATE
        ");
        $stmt->execute([$user['id']]);
        $activeProducts = $stmt->fetchAll();
        
        if (empty($activeProducts)) {
            error('No active products');
        }
        
        $totalIncome = 0;
        
        foreach ($activeProducts as $product) {
            $totalIncome += floatval($product['daily_income']);
        }
        
        if ($totalIncome <= 0) {
            error('No income to claim');
        }
        
        $stmt = $db->prepare("UPDATE users SET balance = balance + ?, total_income = total_income + ? WHERE id = ?");
        $stmt->execute([$totalIncome, $totalIncome, $user['id']]);
        
        $stmt = $db->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description)
            SELECT ?, 'bonus', ?, balance, balance + ?, 'Daily product income'
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user['id'], $totalIncome, $totalIncome, $user['id']]);
        
        response([
            'amount' => $totalIncome,
            'products_claimed' => count($activeProducts)
        ], 'Income claimed successfully');
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
            SELECT uv.*, vp.name, vp.daily_income, vp.level
            FROM user_vip uv
            JOIN vip_packages vp ON uv.vip_package_id = vp.id
            WHERE uv.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $vip = $stmt->fetch();
        
        $stmt = $db->prepare("SELECT level FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userLevel = $stmt->fetch();
        
        if (!$vip) {
            $vip = ['level' => $userLevel['level'], 'name' => 'Bronze VIP'];
        }
        
        response($vip);
    }

    public function claimVipIncome() {
        $user = authenticate();
        
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT uv.total_earned, vp.daily_income
            FROM user_vip uv
            JOIN vip_packages vp ON uv.vip_package_id = vp.id
            WHERE uv.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $vip = $stmt->fetch();
        
        if (!$vip || floatval($vip['daily_income']) <= 0) {
            error('No VIP income available');
        }
        
        $income = floatval($vip['daily_income']);
        
        $stmt = $db->prepare("UPDATE user_vip SET total_earned = total_earned + ? WHERE user_id = ?");
        $stmt->execute([$income, $user['id']]);
        
        $stmt = $db->prepare("UPDATE users SET balance = balance + ?, total_income = total_income + ? WHERE id = ?");
        $stmt->execute([$income, $income, $user['id']]);
        
        $stmt = $db->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description)
            SELECT ?, 'bonus', ?, balance, balance + ?, 'VIP daily income'
            FROM users WHERE id = ?
        ");
        $stmt->execute([$user['id'], $income, $income, $user['id']]);
        
        response([
            'amount' => $income
        ], 'VIP income claimed');
    }
}