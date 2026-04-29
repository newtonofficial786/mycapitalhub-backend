<?php

class User {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function findByMobile($mobile) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT id, mobile, referral_code, referrer_id, level, balance, total_recharge, total_withdraw, total_income, team_income, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByReferralCode($code) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE referral_code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (mobile, password, withdrawal_pin, referrer_id, referral_code)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $referrerId = null;
        if (!empty($data['referrer_code'])) {
            $referrer = $this->findByReferralCode($data['referrer_code']);
            if ($referrer) {
                $referrerId = $referrer['id'];
            }
        }
        
        $stmt->execute([
            $data['mobile'],
            $data['password'],
            $data['withdrawal_pin'],
            $referrerId,
            generateReferralCode($data['mobile'])
        ]);
        
        return $this->db->lastInsertId();
    }

    public function updateBalance($userId, $amount, $type, $description, $status = 'completed') {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            $newBalance = $user['balance'] + $amount;
            if ($newBalance < 0) {
                throw new Exception("Insufficient balance");
            }
            
            $stmt = $this->db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $userId]);
            
            $stmt = $this->db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $type, $amount, $user['balance'], $newBalance, $status, $description]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getTeamMembers($userId) {
        $stmt = $this->db->prepare("
            SELECT id, mobile, level, balance, total_recharge, created_at
            FROM users WHERE referrer_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getTeamCount($userId) {
        $stmt = $this->db->prepare("
            WITH RECURSIVE team AS (
                SELECT id FROM users WHERE referrer_id = ?
                UNION ALL
                SELECT u.id FROM users u
                INNER JOIN team t ON u.referrer_id = t.id
            )
            SELECT COUNT(*) as count FROM team
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    public function getReferrer($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.mobile, u.level FROM users u
            JOIN users ui ON u.id = ui.referrer_id
            WHERE ui.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}