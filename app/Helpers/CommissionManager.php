<?php

class CommissionManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function payReferralCommission($rechargedUserId, $rechargeAmount) {
        $stmt = $this->db->prepare("SELECT referrer_id FROM users WHERE id = ?");
        $stmt->execute([$rechargedUserId]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['referrer_id'])) {
            return;
        }
        
        $referrerId = $user['referrer_id'];
        
        $stmt = $this->db->prepare("SELECT commission_rate FROM commission_settings WHERE level = 1 LIMIT 1");
        $stmt->execute();
        $rate = $stmt->fetch();
        
        $commissionRate = floatval($rate['commission_rate'] ?? 5);
        $commissionAmount = $rechargeAmount * $commissionRate / 100;
        
        if ($commissionAmount <= 0) {
            return;
        }
        
        $stmt = $this->db->prepare("SELECT referral_wallet FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$referrerId]);
        $referrer = $stmt->fetch();
        
        $newBalance = floatval($referrer['referral_wallet'] ?? 0) + $commissionAmount;
        
        $stmt = $this->db->prepare("UPDATE users SET referral_wallet = ? WHERE id = ?");
        $stmt->execute([$newBalance, $referrerId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
            VALUES (?, 'commission', ?, ?, ?, 'completed', ?, 'referral')
        ");
        $stmt->execute([
            $referrerId,
            $commissionAmount,
            floatval($referrer['referral_wallet'] ?? 0),
            $newBalance,
            "Referral commission from user #{$rechargedUserId} recharge of ₹{$rechargeAmount}"
        ]);
    }
    
    public function payMultiLevelCommission($rechargedUserId, $rechargeAmount) {
        $currentUserId = $rechargedUserId;
        $level = 1;
        $maxLevel = 3;
        
        while ($level <= $maxLevel) {
            $stmt = $this->db->prepare("SELECT referrer_id FROM users WHERE id = ?");
            $stmt->execute([$currentUserId]);
            $user = $stmt->fetch();
            
            if (!$user || empty($user['referrer_id'])) {
                break;
            }
            
            $referrerId = $user['referrer_id'];
            
            $stmt = $this->db->prepare("SELECT commission_rate FROM commission_settings WHERE level = ? LIMIT 1");
            $stmt->execute([$level]);
            $rate = $stmt->fetch();
            
            if (!$rate) {
                break;
            }
            
            $commissionRate = floatval($rate['commission_rate'] ?? 0);
            $commissionAmount = $rechargeAmount * $commissionRate / 100;
            
            if ($commissionAmount <= 0) {
                break;
            }
            
            $stmt = $this->db->prepare("SELECT referral_wallet FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$referrerId]);
            $referrer = $stmt->fetch();
            
            $newBalance = floatval($referrer['referral_wallet'] ?? 0) + $commissionAmount;
            
            $stmt = $this->db->prepare("UPDATE users SET referral_wallet = ? WHERE id = ?");
            $stmt->execute([$newBalance, $referrerId]);
            
            $stmt = $this->db->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, status, description, wallet_type)
                VALUES (?, 'commission', ?, ?, ?, 'completed', ?, 'referral')
            ");
            $stmt->execute([
                $referrerId,
                $commissionAmount,
                floatval($referrer['referral_wallet'] ?? 0),
                $newBalance,
                "Level {$level} referral commission from user #{$rechargedUserId} recharge of ₹{$rechargeAmount}"
            ]);
            
            $currentUserId = $referrerId;
            $level++;
        }
    }
}
