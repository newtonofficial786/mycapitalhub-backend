<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';

class BankController {
    public function getBankDetails() {
        $user = authenticate();
        
        $db = getDb();
        $stmt = $db->prepare("
            SELECT account_holder, bank_name, account_number, ifsc_code
            FROM user_bank_details WHERE user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $details = $stmt->fetch();
        
        if ($details) {
            $details['account_number'] = $this->maskAccount($details['account_number']);
        }
        
        response($details);
    }
    
    public function saveBankDetails() {
        $user = authenticate();
        $data = getJsonInput();
        
        $accountHolder = $data['account_holder'] ?? '';
        $bankName = $data['bank_name'] ?? '';
        $accountNumber = $data['account_number'] ?? '';
        $ifscCode = $data['ifsc_code'] ?? '';
        
        if (empty($accountHolder) || empty($accountNumber)) {
            error('Account holder name and account number are required');
        }
        
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO user_bank_details (user_id, account_holder, bank_name, account_number, ifsc_code)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                account_holder = VALUES(account_holder),
                bank_name = VALUES(bank_name),
                account_number = VALUES(account_number),
                ifsc_code = VALUES(ifsc_code)
        ");
        $stmt->execute([$user['id'], $accountHolder, $bankName, $accountNumber, $ifscCode]);
        
        response(null, 'Bank details saved successfully');
    }
    
    private function maskAccount($account) {
        if (strlen($account) <= 4) return $account;
        return str_repeat('*', strlen($account) - 4) . substr($account, -4);
    }
}