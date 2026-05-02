-- Add close time columns to withdraw_settings
-- Run this manually if columns don't exist

ALTER TABLE withdraw_settings ADD COLUMN close_hours INT DEFAULT 0;
ALTER TABLE withdraw_settings ADD COLUMN close_minutes INT DEFAULT 30;
