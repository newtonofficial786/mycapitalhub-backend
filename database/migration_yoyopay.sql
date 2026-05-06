-- Add YoYoPay integration columns to recharges table
ALTER TABLE recharges ADD COLUMN IF NOT EXISTS yoyopay_order_id VARCHAR(100) NULL;
ALTER TABLE recharges ADD COLUMN IF NOT EXISTS payment_url TEXT NULL;

-- Add callback URL to user_bank_details for withdrawals (if not already present)
ALTER TABLE withdrawals ADD COLUMN IF NOT EXISTS yoyopay_order_id VARCHAR(100) NULL;
