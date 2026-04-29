<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/Database.php';

$db = getDb();

echo "<h2>All Wallet Transactions (latest 20)</h2>";
$stmt = $db->query("SELECT wt.*, u.mobile FROM wallet_transactions wt JOIN users u ON wt.user_id = u.id ORDER BY wt.created_at DESC LIMIT 20");
$txns = $stmt->fetchAll();

echo "<table border='1' style='border-collapse:collapse;cellpadding:5'>";
echo "<tr><th>ID</th><th>User</th><th>Type</th><th>Amount</th><th>Status</th><th>Description</th><th>Created</th></tr>";
foreach ($txns as $t) {
    echo "<tr>";
    echo "<td>{$t['id']}</td>";
    echo "<td>{$t['mobile']}</td>";
    echo "<td>{$t['type']}</td>";
    echo "<td>₹{$t['amount']}</td>";
    echo "<td>{$t['status']}</td>";
    echo "<td>{$t['description']}</td>";
    echo "<td>{$t['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Withdrawals Table (with user mobile)</h2>";
$stmt = $db->query("SELECT w.*, u.mobile FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.created_at DESC LIMIT 10");
$wd = $stmt->fetchAll();
echo "<table border='1' style='border-collapse:collapse;cellpadding:5'>";
echo "<tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
foreach ($wd as $w) {
    echo "<tr>";
    echo "<td>{$w['id']}</td>";
    echo "<td>{$w['mobile']}</td>";
    echo "<td>₹{$w['amount']}</td>";
    echo "<td>{$w['status']}</td>";
    echo "<td>{$w['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check for duplicate withdrawals in same second
echo "<h2>Potential Duplicate Withdrawals (same user, same amount, within 5 seconds)</h2>";
$stmt = $db->query("
    SELECT w1.*, u.mobile, 
           (SELECT COUNT(*) FROM withdrawals w2 
            WHERE w2.user_id = w1.user_id 
              AND w2.amount = w1.amount 
              AND ABS(TIMESTAMPDIFF(SECOND, w1.created_at, w2.created_at)) <= 5
              AND w2.id != w1.id) as dup_count
    FROM withdrawals w1
    JOIN users u ON w1.user_id = u.id
    WHERE w1.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    HAVING dup_count > 0
    ORDER BY w1.created_at DESC
    LIMIT 10
");
$dups = $stmt->fetchAll();
if (count($dups) > 0) {
    echo "<pre>"; print_r($dups); echo "</pre>";
} else {
    echo "No duplicates found in withdrawals table";
}

// Also check wallet_transactions for duplicate withdraw entries
echo "<h2>Potential Duplicate Withdraw Transactions (same user, same amount, within 5 seconds)</h2>";
$stmt = $db->query("
    SELECT wt1.*, u.mobile,
           (SELECT COUNT(*) FROM wallet_transactions wt2 
            WHERE wt2.user_id = wt1.user_id 
              AND wt2.type = 'withdraw'
              AND wt2.amount = wt1.amount 
              AND ABS(TIMESTAMPDIFF(SECOND, wt1.created_at, wt2.created_at)) <= 5
              AND wt2.id != wt1.id) as dup_count
    FROM wallet_transactions wt1
    JOIN users u ON wt1.user_id = u.id
    WHERE wt1.type = 'withdraw'
      AND wt1.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    HAVING dup_count > 0
    ORDER BY wt1.created_at DESC
    LIMIT 10
");
$dups2 = $stmt->fetchAll();
if (count($dups2) > 0) {
    echo "<pre>"; print_r($dups2); echo "</pre>";
} else {
    echo "No duplicates found in wallet_transactions for withdraw type";
}
