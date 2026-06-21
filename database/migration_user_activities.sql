-- User Activities tracking table
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('app_open', 'login', 'register', 'logout', 'page_view', 'recharge_initiated', 'recharge_success', 'recharge_failed', 'recharge_pending', 'withdraw_initiated', 'withdraw_success', 'withdraw_failed', 'withdraw_pending', 'product_purchase', 'vip_purchase', 'income_claim', 'game_bet', 'game_win', 'game_loss', 'profile_update', 'bank_update', 'pin_verify', 'referral_share', 'other') NOT NULL,
    metadata JSON DEFAULT NULL COMMENT 'Extra info like amount, page name, game type',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activities_user_id (user_id),
    INDEX idx_user_activities_type (activity_type),
    INDEX idx_user_activities_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
