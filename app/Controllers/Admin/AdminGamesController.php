<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminGamesController {
    public function getBets() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 50), 200);
        $offset = intval($data['offset'] ?? 0);
        $gameType = $data['game_type'] ?? '';
        
        $db = getDb();
        $where = '1=1';
        $params = [];
        
        if ($gameType) {
            $where = 'gb.game_type = ?';
            $params[] = $gameType;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT gb.*, u.mobile
            FROM game_bets gb
            LEFT JOIN users u ON gb.user_id = u.id
            WHERE $where
            ORDER BY gb.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        
        response($stmt->fetchAll());
    }
    
    public function getResults() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 50), 200);
        $offset = intval($data['offset'] ?? 0);
        $gameType = $data['game_type'] ?? '';
        
        $db = getDb();
        $where = '1=1';
        $params = [];
        
        if ($gameType) {
            $where = 'gh.game_type = ?';
            $params[] = $gameType;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT * FROM game_history WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        
        response($stmt->fetchAll());
    }
    
    public function addResult() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $gameType = $data['game_type'] ?? '';
        $periodId = $data['period_id'] ?? '';
        $result = $data['result'] ?? '';
        
        if (empty($gameType) || empty($periodId) || empty($result)) {
            error('game_type, period_id, and result required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO game_history (game_type, period_id, result) VALUES (?, ?, ?)
        ");
        $stmt->execute([$gameType, $periodId, $result]);
        
        response(['id' => $db->lastInsertId()], 'Result added');
    }
    
    public function deleteResult() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) error('Result ID required');
        
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM game_history WHERE id = ?");
        $stmt->execute([$id]);
        
        response(null, 'Result deleted');
    }
    
    public function getStats() {
        authenticateAdmin();
        $data = getJsonInput();
        $gameType = $data['game_type'] ?? '';
        
        $db = getDb();
        $where = '';
        $params = [];
        
        if ($gameType) {
            $where = 'WHERE game_type = ?';
            $params[] = $gameType;
        }
        
        $stats = $db->prepare("
            SELECT 
                COUNT(*) as total_bets,
                SUM(bet_amount) as total_bet_amount,
                SUM(win_amount) as total_win_amount,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as total_wins,
                game_type
            FROM game_bets $where
            GROUP BY game_type
        ");
        $stats->execute($params);
        
        response($stats->fetchAll());
    }
}
