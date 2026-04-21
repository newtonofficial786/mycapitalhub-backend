<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

function setupDatabase() {
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $user = env('DB_USERNAME', 'root');
    $pass = env('DB_PASSWORD', '');
    $dbName = env('DB_DATABASE', 'gaming_platform');
    
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
        $pdo->exec("USE `$dbName`");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mobile VARCHAR(20) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                withdrawal_pin VARCHAR(10) NOT NULL,
                referrer_id INT NULL,
                referral_code VARCHAR(20) UNIQUE NOT NULL,
                level INT DEFAULT 1,
                balance DECIMAL(15, 2) DEFAULT 0.00,
                total_recharge DECIMAL(15, 2) DEFAULT 0.00,
                total_withdraw DECIMAL(15, 2) DEFAULT 0.00,
                total_income DECIMAL(15, 2) DEFAULT 0.00,
                team_income DECIMAL(15, 2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status ENUM('active', 'suspended') DEFAULT 'active',
                FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wallet_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('recharge', 'withdraw', 'bet', 'win', 'commission', 'bonus', 'other') NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                balance_before DECIMAL(15, 2) NOT NULL,
                balance_after DECIMAL(15, 2) NOT NULL,
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS recharges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                payment_method VARCHAR(50),
                transaction_id VARCHAR(100),
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS withdrawals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(15, 2) NOT NULL,
                bank_name VARCHAR(100),
                bank_account VARCHAR(50),
                account_holder VARCHAR(100),
                status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS game_bets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                game_type ENUM('card_game', 'dice_roller', 'color_prediction') NOT NULL,
                bet_amount DECIMAL(15, 2) NOT NULL,
                choice VARCHAR(50) NOT NULL,
                result VARCHAR(50),
                win_amount DECIMAL(15, 2) DEFAULT 0.00,
                is_win TINYINT(1) DEFAULT 0,
                period_id VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS game_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                game_type ENUM('card_game', 'dice_roller', 'color_prediction') NOT NULL,
                period_id VARCHAR(50) NOT NULL,
                result VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(15, 2) NOT NULL,
                daily_income DECIMAL(15, 2) NOT NULL,
                duration_days INT NOT NULL,
                image VARCHAR(255),
                description TEXT,
                active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expiry_date DATE NOT NULL,
                status ENUM('active', 'expired') DEFAULT 'active',
                total_earned DECIMAL(15, 2) DEFAULT 0.00,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vip_packages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                min_recharge DECIMAL(15, 2) NOT NULL,
                price DECIMAL(15, 2) NOT NULL,
                daily_income DECIMAL(15, 2) NOT NULL,
                level INT NOT NULL,
                active TINYINT(1) DEFAULT 1
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_vip (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                vip_package_id INT NOT NULL,
                start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_earned DECIMAL(15, 2) DEFAULT 0.00,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (vip_package_id) REFERENCES vip_packages(id) ON DELETE CASCADE
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS commission_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level INT NOT NULL,
                commission_rate DECIMAL(5, 2) NOT NULL,
                description VARCHAR(100)
            )
        ");
        
        // Insert default data
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM products");
        if ($stmt->fetch()['cnt'] == 0) {
            $pdo->exec("INSERT INTO products (name, price, daily_income, duration_days, description) VALUES
                ('Starter Pack', 100.00, 5.00, 30, 'Perfect for beginners'),
                ('Silver Package', 500.00, 30.00, 30, 'Great returns'),
                ('Gold Package', 2000.00, 140.00, 30, 'Premium investment'),
                ('Platinum Package', 5000.00, 400.00, 30, 'Best value')");
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vip_packages");
        if ($stmt->fetch()['cnt'] == 0) {
            $pdo->exec("INSERT INTO vip_packages (name, min_recharge, daily_income, level) VALUES
                ('Bronze VIP', 0.00, 0.00, 0),
                ('Silver VIP', 1000.00, 10.00, 1),
                ('Gold VIP', 5000.00, 60.00, 2),
                ('Platinum VIP', 20000.00, 300.00, 3),
                ('Diamond VIP', 50000.00, 1000.00, 4)");
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM commission_settings");
        if ($stmt->fetch()['cnt'] == 0) {
            $pdo->exec("INSERT INTO commission_settings (level, commission_rate, description) VALUES
                (1, 5.00, 'Level 1 team members'),
                (2, 3.00, 'Level 2 team members'),
                (3, 2.00, 'Level 3 team members')");
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Database setup completed']);
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

setupDatabase();