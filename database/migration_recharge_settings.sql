-- Recharge amount presets table
CREATE TABLE IF NOT EXISTS recharge_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order, lower appears first',
    amount DECIMAL(10, 0) NOT NULL COMMENT 'Preset amount in rupees',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default amounts (same as current hardcoded values)
INSERT INTO recharge_settings (`order`, amount) VALUES
(1, 800),
(2, 297),
(3, 595),
(4, 1350),
(5, 1600),
(6, 2200),
(7, 700),
(8, 1200),
(9, 3300);