-- Migration: User Level System based on Total Recharge
-- Run each block separately. If columns already exist, ignore the error.

-- 1. Create level settings table
CREATE TABLE IF NOT EXISTS user_level_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level INT NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    min_recharge DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    icon VARCHAR(50) DEFAULT NULL,
    color VARCHAR(20) DEFAULT NULL,
    benefits TEXT DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed default levels
INSERT IGNORE INTO user_level_settings (level, name, min_recharge, icon, color, benefits) VALUES
(0, 'Bronze', 0, '🥉', '#cd7f32', 'Basic access'),
(1, 'Silver', 2000, '🥈', '#c0c0c0', 'Silver benefits'),
(2, 'Gold', 10000, '🥇', '#ffd700', 'Gold benefits'),
(3, 'Platinum', 50000, '💎', '#e5e4e2', 'Platinum benefits'),
(4, 'Diamond', 200000, '👑', '#b9f2ff', 'Diamond benefits');

-- 3. Add columns to users (safe to ignore if "Duplicate column name" error)
ALTER TABLE users ADD COLUMN level INT DEFAULT 0;
ALTER TABLE users ADD COLUMN total_recharge DECIMAL(15, 2) DEFAULT 0.00;
