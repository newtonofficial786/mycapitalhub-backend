CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('telegram_link', 'https://t.me/Mycapitalhubsupport'),
('support_link', 'https://t.me/Mycapitalhubsupport'),
('channel_link', 'https://t.me/+dKrCPjrUIApkMDRl')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
