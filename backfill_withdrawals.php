<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

echo "Backfilling bank details for existing withdrawal transactions...\n";

$stmt = $db->prepare("
    UPDATE wallet_transactions wt
    JOIN user_bank_details ubd ON wt.user_id = ubd.user_id
    SET wt.bank_name = ubd.bank_name,
        wt.bank_account = ubd.account_number,
        wt.account_holder = ubd.account_holder,
        wt.ifsc_code = ubd.ifsc_code
    WHERE wt.type = 'withdraw'
      AND (wt.bank_name IS NULL OR wt.bank_account IS NULL)
");

$count = $stmt->rowCount();
echo "Updated $count withdrawal transactions with bank details.\n";
