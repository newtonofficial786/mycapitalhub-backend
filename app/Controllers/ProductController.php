<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';

class ProductController
{
    public function getProducts()
    {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM products WHERE active = 1 ORDER BY price ASC");
        $stmt->execute();
        $products = $stmt->fetchAll();

        response($products);
    }

    public function getUserProducts()
    {
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

        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $serverTimestamp = $now->getTimestamp();

        foreach ($products as &$p) {
            $p['server_time'] = $serverTimestamp;
            if ($p['last_claimed']) {
                $last = new DateTime($p['last_claimed'], new DateTimeZone('Asia/Kolkata'));
                $next = clone $last;
                $next->modify('+1 day');
                $p['claimable_at'] = $next->format('c');
                $p['claimable_timestamp'] = $next->getTimestamp();
            }
        }

        response($products);
    }

    public function purchaseProduct()
    {
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
            $userProductId = $db->lastInsertId();

            $productName = $product['name'] ?? 'Product';

            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bet', ?, ?, ?, 'completed', ?, 'main')
            ");
            $stmt->execute([$user['id'], -$product['price'], $userBefore, $userBefore - $product['price'], 'Investment: ' . $productName]);

            $db->commit();

            response([
                'id' => $userProductId,
                'expiry_date' => $expiryDate,
                'daily_income' => $product['daily_income']
            ], 'Product purchased successfully');
        } catch (Exception $e) {
            $db->rollBack();
            error('Purchase failed');
        }
    }

    public function claimDailyIncome()
    {
        try {
            date_default_timezone_set('Asia/Kolkata');
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
                error('Product ID is required', 400);
            }

            $db = getDb();
            $db->exec("SET time_zone = '+05:30'");
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
                // Accumulate income for all missed calendar days
                $lastDate = (clone $last)->setTime(0, 0, 0);
                $todayDate = (clone $now)->setTime(0, 0, 0);
                $missedDays = (int)$lastDate->diff($todayDate)->days;
                if ($missedDays > 0) {
                    $income = $income * $missedDays;
                }
            }

            // Build description with date range for accumulated claims
            $description = "Daily income: {$product['name']}";
            if ($lastClaimed && isset($missedDays) && $missedDays > 1) {
                $startDate = (clone $lastDate)->add(new DateInterval('P1D'));
                $description .= " (" . $startDate->format('d/m/Y') . " - " . $todayDate->format('d/m/Y') . ")";
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
            $stmt->execute([$user['id'], $income, $balanceBefore, $balanceAfter, $description]);

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

class VipController
{
    public function getVipPackages()
    {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM vip_packages WHERE active = 1 ORDER BY min_recharge ASC");
        $stmt->execute();
        $packages = $stmt->fetchAll();

        response($packages);
    }

    public function getUserVip()
    {
        date_default_timezone_set('Asia/Kolkata');
        $user = authenticate();

        $db = getDb();
        $db->exec("SET time_zone = '+05:30'");
        $stmt = $db->prepare("
            SELECT uv.*, vp.name, vp.daily_income, vp.level, vp.min_recharge, vp.reward_amount, vp.wait_minutes,
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

        foreach ($vips as &$v) {
            if ($v['claimable_at']) {
                $v['claimable_timestamp'] = strtotime($v['claimable_at']);
            }
            $v['server_time'] = time();
        }

        response($vips ?: []);
    }

    public function purchaseVip()
    {
        date_default_timezone_set('Asia/Kolkata');
        $user = authenticate();
        $data = getJsonInput();

        $packageId = intval($data['package_id'] ?? 0);

        if ($packageId <= 0) {
            error('Invalid package');
        }

        $db = getDb();
        $db->exec("SET time_zone = '+05:30'");
        $stmt = $db->prepare("SELECT * FROM vip_packages WHERE id = ? AND active = 1");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();

        if (!$package) {
            error('Package not found');
        }

        $stmt = $db->prepare("SELECT main_wallet, level FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $currentUser = $stmt->fetch();
        $currentBalance = floatval($currentUser['main_wallet'] ?? 0);

        $price = floatval($package['min_recharge'] ?? 0);

        $userLevel = intval($currentUser['level'] ?? 0);
        $requiredLevel = intval($package['level'] ?? 0);

        if ($userLevel < $requiredLevel) {
            error('This VIP package requires Level ' . $requiredLevel . '. Your current level: ' . $userLevel);
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM user_products
            WHERE user_id = ? AND status = 'active' AND expiry_date >= CURRENT_DATE
        ");
        $stmt->execute([$user['id']]);
        $hasActiveProduct = intval($stmt->fetch()['cnt']) > 0;

        if (!$hasActiveProduct) {
            error('You must purchase a Stable package before buying Welfare');
        }

        if ($currentBalance < $price) {
            error('Insufficient balance in main wallet. Your balance: ₹' . $currentBalance . ', Price: ₹' . $price);
        }

        $stmt = $db->prepare("
            SELECT id FROM user_vip WHERE user_id = ? AND vip_package_id = ?
        ");
        $stmt->execute([$user['id'], $packageId]);
        $alreadyPurchased = $stmt->fetch();

        if ($alreadyPurchased) {
            error('You have already purchased this Welfare package. Each package can only be bought once.');
        }

        $stmt = $db->prepare("SELECT id, is_claimed FROM user_vip WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $existingVip = $stmt->fetch();

        if ($existingVip && !$existingVip['is_claimed']) {
            error('You already have an active VIP package. Please wait until it can be claimed.');
        }

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("UPDATE users SET main_wallet = main_wallet - ? WHERE id = ?");
            $stmt->execute([$price, $user['id']]);

            $waitMinutes = intval($package['wait_minutes'] ?? 60);
            $now = date('Y-m-d H:i:s');
            $claimableAt = date('Y-m-d H:i:s', strtotime("+{$waitMinutes} minutes"));

            $stmt = $db->prepare("
                INSERT INTO user_vip (user_id, vip_package_id, purchased_at, claimable_at, is_claimed)
                VALUES (?, ?, ?, ?, 0)
            ");
            $stmt->execute([$user['id'], $packageId, $now, $claimableAt]);

            $stmt = $db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'bet', ?, ?, ?, 'completed', ?, 'main')
            ");
            $stmt->execute([$user['id'], -$price, $currentUser['main_wallet'], $currentUser['main_wallet'] - $price, 'VIP Jackpot: ' . $package['name']]);

            $db->commit();

            $claimableTime = new DateTime("+$waitMinutes minutes");
            date_default_timezone_set('Asia/Kolkata');
            $claimableTime->setTimezone(new DateTimeZone('Asia/Kolkata'));

            response([
                'id' => $db->lastInsertId(),
                'package_name' => $package['name'],
                'reward_amount' => $package['reward_amount'],
                'claimable_at' => $claimableTime->format('c')
            ], 'VIP Jackpot purchased successfully');
        } catch (Exception $e) {
            $db->rollBack();
            error('Purchase failed: ' . $e->getMessage());
        }
    }

    public function claimVipIncome()
    {
        date_default_timezone_set('Asia/Kolkata');
        $user = authenticate();

        $db = getDb();
        $db->exec("SET time_zone = '+05:30'");

        try {
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
                error('VIP package ID required', 400);
            }

            $db = getDb();
            $stmt = $db->prepare("
                SELECT uv.id, uv.claimable_at, uv.is_claimed, vp.reward_amount, vp.name
                FROM user_vip uv
                JOIN vip_packages vp ON uv.vip_package_id = vp.id
                WHERE uv.id = ? AND uv.user_id = ?
            ");
            $stmt->execute([$packageId, $user['id']]);
            $vip = $stmt->fetch();

            if (!$vip) {
                error('Invalid VIP package');
            }

            if ($vip['is_claimed']) {
                error('This VIP jackpot has already been claimed');
            }

            $rewardAmount = floatval($vip['reward_amount']);

            if ($rewardAmount <= 0) {
                error('No reward available');
            }

            $claimableAt = $vip['claimable_at'] ?? null;

            if ($claimableAt) {
                $claimableTs = strtotime($claimableAt);
                $nowTs = time();
                if ($nowTs < $claimableTs) {
                    $remaining = $claimableTs - $nowTs;
                    $hours = floor($remaining / 3600);
                    $minutes = floor(($remaining % 3600) / 60);
                    $seconds = $remaining % 60;
                    error("Please wait {$hours}h {$minutes}m {$seconds}s before claiming.", 400);
                }
            }

            $stmt = $db->prepare("SELECT vip_wallet FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $balanceBefore = floatval($stmt->fetch()['vip_wallet'] ?? 0);
            $balanceAfter = $balanceBefore + $rewardAmount;

            $db->beginTransaction();

            try {
                $stmt = $db->prepare("UPDATE user_vip SET is_claimed = 1 WHERE id = ?");
                $stmt->execute([$packageId]);

                $stmt = $db->prepare("UPDATE users SET vip_wallet = ? WHERE id = ?");
                $stmt->execute([$balanceAfter, $user['id']]);

                $stmt = $db->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                    VALUES (?, 'bonus', ?, ?, ?, 'completed', ?, 'vip')
                ");
                $stmt->execute([$user['id'], $rewardAmount, $balanceBefore, $balanceAfter, 'VIP Jackpot Claimed: ' . $vip['name']]);

                $db->commit();

                response([
                    'amount' => $rewardAmount,
                    'package_name' => $vip['name']
                ], 'VIP Jackpot claimed successfully!');
            } catch (Exception $e) {
                $db->rollBack();
                error('Claim failed: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            error('Server error: ' . $e->getMessage(), 500);
        }
    }
}
