<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class IncomeController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function getTransactions() {
        $user = authenticate();
        $data = getJsonInput();
        
        $type = $data['type'] ?? '';
        $limit = intval($data['limit'] ?? 20);
        $offset = intval($data['offset'] ?? 0);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        
        if ($type) {
            $stmt = $db->prepare("
                SELECT * FROM wallet_transactions 
                WHERE user_id = ? AND type = ?
                ORDER BY created_at DESC LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['id'], $type, $limit, $offset]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM wallet_transactions 
                WHERE user_id = ?
                ORDER BY created_at DESC LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user['id'], $limit, $offset]);
        }
        
        $transactions = $stmt->fetchAll();
        response($transactions);
    }

    public function getSummary() {
        $user = authenticate();
        
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT 
                type,
                SUM(amount) as total
            FROM wallet_transactions 
            WHERE user_id = ? AND status = 'completed'
            GROUP BY type
        ");
        $stmt->execute([$user['id']]);
        $typeTotals = $stmt->fetchAll();
        
        $summary = [
            'total_recharge' => 0,
            'total_withdraw' => 0,
            'total_bet' => 0,
            'total_win' => 0,
            'total_commission' => 0,
            'total_bonus' => 0
        ];
        
        foreach ($typeTotals as $row) {
            $type = $row['type'];
            $total = floatval($row['total']);
            
            switch ($type) {
                case 'recharge':
                    $summary['total_recharge'] = $total;
                    break;
                case 'withdraw':
                    $summary['total_withdraw'] = $total;
                    break;
                case 'bet':
                    $summary['total_bet'] = abs($total);
                    break;
                case 'win':
                    $summary['total_win'] = $total;
                    break;
                case 'commission':
                    $summary['total_commission'] = $total;
                    break;
                case 'bonus':
                    $summary['total_bonus'] = $total;
                    break;
            }
        }
        
        $stmt = $db->prepare("SELECT main_wallet, stable_wallet, vip_wallet, referral_wallet FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $balanceData = $stmt->fetch();
        
        $summary['main_wallet'] = floatval($balanceData['main_wallet'] ?? 0);
        $summary['stable_wallet'] = floatval($balanceData['stable_wallet'] ?? 0);
        $summary['vip_wallet'] = floatval($balanceData['vip_wallet'] ?? 0);
        $summary['referral_wallet'] = floatval($balanceData['referral_wallet'] ?? 0);
        $summary['balance'] = floatval($balanceData['main_wallet'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT wallet_type, SUM(amount) as wallet_total
            FROM wallet_transactions 
            WHERE user_id = ? AND status = 'completed' AND amount > 0
            GROUP BY wallet_type
        ");
        $stmt->execute([$user['id']]);
        $walletTotals = $stmt->fetchAll();
        
        $summary['wallet_income'] = [
            'main' => 0,
            'stable' => 0,
            'vip' => 0,
            'referral' => 0
        ];
        foreach ($walletTotals as $row) {
            $summary['wallet_income'][$row['wallet_type']] = floatval($row['wallet_total']);
        }
        
        response($summary);
    }

    public function getDailyIncome() {
        $user = authenticate();
        $data = getJsonInput();
        
        $days = intval($data['days'] ?? 30);
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN type = 'win' THEN amount ELSE 0 END) as income
            FROM wallet_transactions 
            WHERE user_id = ? 
            AND status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$user['id'], $days]);
        $dailyIncome = $stmt->fetchAll();
        
        response($dailyIncome);
    }
}