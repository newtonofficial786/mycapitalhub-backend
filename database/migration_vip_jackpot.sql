-- Migration: VIP Jackpot System
-- Run this to add jackpot fields to VIP packages and user VIP records

-- Add jackpot fields to vip_packages
ALTER TABLE vip_packages ADD COLUMN reward_amount DECIMAL(15, 2) DEFAULT 0.00 AFTER daily_income;
ALTER TABLE vip_packages ADD COLUMN wait_minutes INT DEFAULT 60 AFTER reward_amount;

-- Add jackpot tracking fields to user_vip
ALTER TABLE user_vip ADD COLUMN purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE user_vip ADD COLUMN claimable_at TIMESTAMP NULL;
ALTER TABLE user_vip ADD COLUMN is_claimed TINYINT(1) DEFAULT 0;
