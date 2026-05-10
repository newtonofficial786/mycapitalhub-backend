-- Maintenance Settings Table
CREATE TABLE IF NOT EXISTS maintenance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mode ENUM('off', 'temporary', 'permanent') DEFAULT 'off',
    message TEXT DEFAULT NULL,
    title VARCHAR(255) DEFAULT 'Under Maintenance',
    sub_message TEXT DEFAULT NULL,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    allow_login TINYINT(1) DEFAULT 1,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO maintenance_settings (mode, title, message, active) VALUES ('off', 'Under Maintenance', 'We are performing scheduled maintenance. Please try again later.', 1);
