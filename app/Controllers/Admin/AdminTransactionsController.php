<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminTransactionsController {
    public function getAll() {
        authenticateAdmin();
        $data = getJsonInput();
        
        $limit = min(intval($data['limit'] ?? 50), 200);
        $offset = intval($data['offset'] ?? 0);
        $type = $data['type'] ?? '';
        $status = $data['status'] ?? '';
        $userId = intval($data['user_id'] ?? 0);
        
        $db = getDb();
        $where = ['1=1'];
        $params = [];
        
        if ($userId) {
            $where[] = 'wt.user_id = ?';
            $params[] = $userId;
        }
        if ($type) {
            $where[] = 'wt.type = ?';
            $params[] = $type;
        }
        if ($status) {
            $where[] = 'wt.status = ?';
            $params[] = $status;
        }
        
        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare("
            SELECT wt.*, u.mobile
            FROM wallet_transactions wt
            LEFT JOIN users u ON wt.user_id = u.id
            WHERE $whereClause
            ORDER BY wt.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
        
        response($transactions);
    }
    
    public function updateStatus() {
        authenticateAdmin();
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        $status = $data['status'] ?? '';
        
        if (!$id || !in_array($status, ['pending', 'completed', 'failed'])) {
            error('Valid ID and status required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("UPDATE wallet_transactions SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($stmt->rowCount() === 0) error('Transaction not found');
        
        response(null, 'Status updated');
    }
}
