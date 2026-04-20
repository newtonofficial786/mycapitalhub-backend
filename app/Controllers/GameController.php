<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';
require_once __DIR__ . '/../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/Models/User.php';

class GameController {
    private $userModel;

    public function __construct() {
        $db = getDb();
        $this->userModel = new User($db);
    }

    public function placeBet() {
        $user = authenticate();
        $data = getJsonInput();
        
        $gameType = $data['game_type'] ?? '';
        $betAmount = floatval($data['bet_amount'] ?? 0);
        $choice = $data['choice'] ?? '';
        $periodId = $data['period_id'] ?? $this->generatePeriodId($gameType);
        
        if (empty($gameType) || empty($choice) || $betAmount <= 0) {
            error('Invalid bet data');
        }
        
        $validGames = ['card_game', 'dice_roller', 'color_prediction'];
        if (!in_array($gameType, $validGames)) {
            error('Invalid game type');
        }
        
        $odds = $this->getOdds($gameType, $choice);
        if ($odds === 0) {
            error('Invalid choice');
        }
        
        try {
            $this->userModel->updateBalance($user['id'], -$betAmount, 'bet', "Bet on {$gameType}");
        } catch (Exception $e) {
            error('Insufficient balance');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO game_bets (user_id, game_type, bet_amount, choice, period_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user['id'], $gameType, $betAmount, $choice, $periodId]);
        $betId = $db->lastInsertId();
        
        $result = $this->generateResult($gameType);
        $isWin = $this->checkWin($choice, $result, $gameType);
        
        if ($isWin) {
            $winAmount = $betAmount * $odds;
            try {
                $this->userModel->updateBalance($user['id'], $winAmount, 'win', "Win on {$gameType}");
            } catch (Exception $e) {
                $winAmount = 0;
            }
        } else {
            $winAmount = 0;
        }
        
        $stmt = $db->prepare("UPDATE game_bets SET result = ?, win_amount = ?, is_win = ? WHERE id = ?");
        $stmt->execute([$result, $winAmount, $isWin ? 1 : 0, $betId]);
        
        response([
            'bet_id' => $betId,
            'period_id' => $periodId,
            'result' => $result,
            'choice' => $choice,
            'bet_amount' => $betAmount,
            'win_amount' => $winAmount,
            'is_win' => $isWin
        ]);
    }

    public function getHistory() {
        $user = authenticate();
        $data = getJsonInput();
        
        $gameType = $data['game_type'] ?? '';
        $limit = intval($data['limit'] ?? 20);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        
        if ($gameType) {
            $stmt = $db->prepare("
                SELECT * FROM game_bets 
                WHERE user_id = ? AND game_type = ?
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->execute([$user['id'], $gameType, $limit]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM game_bets 
                WHERE user_id = ?
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->execute([$user['id'], $limit]);
        }
        
        $history = $stmt->fetchAll();
        response($history);
    }

    public function getGameResults() {
        $data = getJsonInput();
        
        $gameType = $data['game_type'] ?? '';
        $limit = intval($data['limit'] ?? 20);
        
        if ($limit > 100) $limit = 100;
        
        $db = getDb();
        
        if ($gameType) {
            $stmt = $db->prepare("
                SELECT * FROM game_history 
                WHERE game_type = ?
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->execute([$gameType, $limit]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM game_history 
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        
        $results = $stmt->fetchAll();
        response($results);
    }

    public function getCurrentPeriod() {
        $data = getJsonInput();
        $gameType = $data['game_type'] ?? '';
        
        if (empty($gameType)) {
            error('Game type required');
        }
        
        $periodId = $this->generatePeriodId($gameType);
        $db = getDb();
        
        $stmt = $db->prepare("
            SELECT result FROM game_history 
            WHERE game_type = ? AND period_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$gameType, $periodId]);
        $existingResult = $stmt->fetch();
        
        if (!$existingResult) {
            $result = $this->generateResult($gameType);
            $stmt = $db->prepare("
                INSERT INTO game_history (game_type, period_id, result)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$gameType, $periodId, $result]);
        }
        
        response([
            'period_id' => $periodId,
            'countdown' => $this->getCountdown()
        ]);
    }

    private function generatePeriodId($gameType) {
        return date('YmdHis') . '_' . strtoupper(substr($gameType, 0, 3));
    }

    private function getCountdown() {
        $seconds = time() % 60;
        return 60 - $seconds;
    }

    private function getOdds($gameType, $choice) {
        $odds = [
            'card_game' => [
                'red' => 1.98,
                'black' => 1.98,
                'Ace' => 10.00,
                '2-10' => 1.95,
                'J' => 10.00,
                'Q' => 10.00,
                'K' => 10.00
            ],
            'dice_roller' => [
                '1-3' => 1.98,
                '4-6' => 1.98,
                '1' => 5.00,
                '2' => 5.00,
                '3' => 5.00,
                '4' => 5.00,
                '5' => 5.00,
                '6' => 5.00
            ],
            'color_prediction' => [
                'red' => 1.98,
                'green' => 1.98,
                'violet' => 4.00
            ]
        ];
        
        return $odds[$gameType][$choice] ?? 0;
    }

    private function generateResult($gameType) {
        return match($gameType) {
            'card_game' => $this->randomElement(['red', 'black', 'Ace', '2-10', 'J', 'Q', 'K']),
            'dice_roller' => strval(rand(1, 6)),
            'color_prediction' => $this->randomElement(['red', 'green', 'violet'])
        };
    }

    private function checkWin($choice, $result, $gameType) {
        return match($gameType) {
            'card_game' => $choice === $result,
            'dice_roller' => $choice === $result || 
                ($choice === '1-3' && in_array($result, ['1', '2', '3'])) ||
                ($choice === '4-6' && in_array($result, ['4', '5', '6'])),
            'color_prediction' => $choice === $result
        };
    }

    private function randomElement($array) {
        return $array[array_rand($array)];
    }
}