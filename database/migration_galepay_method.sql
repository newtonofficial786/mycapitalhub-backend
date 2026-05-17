INSERT INTO payment_methods (name, subtitle, backend_key, icon_name, min_amount, max_amount, processing_time, instructions, active, sort_order) VALUES
('GalePay', 'Powered by GalePay (UPI/IMPS)', 'galepay', 'upi', 100, 100000, 'Instant', 'You will be redirected to the payment gateway to complete your payment.', 1, 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);
