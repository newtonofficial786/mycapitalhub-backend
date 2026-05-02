-- Migration: Add 4 wallet columns to users table
-- Run this after the initial schema.sql
-- Note: IF NOT EXISTS is not supported in ALTER TABLE for MySQL/MariaDB

-- Add 4 new wallet columns to users table
ALTER TABLE users ADD COLUMN stable_wallet DECIMAL(15, 2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN vip_wallet DECIMAL(15, 2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN referral_wallet DECIMAL(15, 2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN main_wallet DECIMAL(15, 2) DEFAULT 0.00;

-- Add wallet_type column to wallet_transactions table
ALTER TABLE wallet_transactions ADD COLUMN wallet_type ENUM('main', 'stable', 'vip', 'referral') NOT NULL DEFAULT 'main';

-- Add wallet_type column to withdrawals table
ALTER TABLE withdrawals ADD COLUMN wallet_type ENUM('main', 'stable', 'vip', 'referral') NOT NULL DEFAULT 'main';

-- Migrate existing balance to main_wallet (for existing users)
UPDATE users SET main_wallet = balance, stable_wallet = total_income WHERE balance > 0 OR total_income > 0;

-- Add withdrawal window columns to withdraw_settings
ALTER TABLE withdraw_settings ADD COLUMN close_from VARCHAR(5) DEFAULT '07:00';
ALTER TABLE withdraw_settings ADD COLUMN close_to VARCHAR(5) DEFAULT '17:00';
