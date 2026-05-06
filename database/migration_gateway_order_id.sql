ALTER TABLE recharges ADD COLUMN gateway_order_id VARCHAR(100) NULL;
ALTER TABLE recharges ADD COLUMN payment_url TEXT NULL;
ALTER TABLE recharges ADD COLUMN result_url TEXT NULL;
