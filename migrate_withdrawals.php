<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

try {
    $db->exec("ALTER TABLE withdrawals ADD COLUMN ifsc_code VARCHAR(20) DEFAULT NULL");
    echo "Added ifsc_code column\n";
} catch (PDOException $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}

try {
    // Update existing withdrawals with user's current IFSC (if available)
    $stmt = $db->query("
        UPDATE withdrawals w
        JOIN user_bank_details ubd ON w.user_id = ubd.user_id
        SET w.ifsc_code = ubd.ifsc_code
        WHERE w.ifsc_code IS NULL
    ");
    $count = $stmt->rowCount();
    echo "Updated $count existing withdrawals with IFSC code\n";
} catch (PDOException $e) {
    echo "Error updating existing records: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
