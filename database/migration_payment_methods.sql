CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Display name shown to users (e.g. WatchPay)',
    subtitle VARCHAR(255) COMMENT 'Subtitle shown under name (e.g. Powered by YoYoPay)',
    backend_key VARCHAR(50) NOT NULL COMMENT 'Backend identifier (e.g. yoyopay, bank_transfer)',
    icon_name VARCHAR(50) DEFAULT 'credit-card' COMMENT 'Icon identifier for frontend rendering',
    min_amount DECIMAL(15, 2) DEFAULT 100 COMMENT 'Minimum recharge amount',
    max_amount DECIMAL(15, 2) DEFAULT 100000 COMMENT 'Maximum recharge amount',
    processing_time VARCHAR(100) COMMENT 'Display text like "Instant" or "1-24 hours"',
    instructions TEXT COMMENT 'Additional instructions shown to users',
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0 COMMENT 'Display order in payment method list',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payment_methods (name, subtitle, backend_key, icon_name, min_amount, max_amount, processing_time, instructions, active, sort_order) VALUES
('WatchPay', 'Powered by WatchPays (UPI/IMPS)', 'watchpays', 'credit-card', 100, 100000, 'Instant', 'You will be redirected to the payment gateway to complete your payment.', 1, 1),
('EasyPay', 'Bank Transfer (Admin Verified)', 'bank_transfer', 'bank', 100, 100000, '1-24 hours', 'Transfer funds to the provided bank account. Admin will verify and credit your wallet.', 1, 2),
('QuickPay', 'Powered by Google Pay', 'gpay', 'gpay', 100, 100000, 'Instant', 'Coming soon.', 0, 3),
('FastPay', 'Powered by PhonePe', 'phonepe', 'phonepe', 100, 100000, 'Instant', 'Coming soon.', 0, 4),
('SmartPay', 'Powered by Paytm', 'paytm', 'wallet', 100, 100000, 'Instant', 'Coming soon.', 0, 5);
