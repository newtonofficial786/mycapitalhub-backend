INSERT INTO payment_methods (name, subtitle, backend_key, icon_name, min_amount, max_amount, processing_time, instructions, active, sort_order) VALUES
('ZoxPay', 'Powered by ZoxPay (UPI / Cards)', 'zoxpay', 'card', 100, 100000, 'Instant', 'You will be redirected to ZoxPay to complete your payment.', 1, 8)
ON DUPLICATE KEY UPDATE name = VALUES(name);
