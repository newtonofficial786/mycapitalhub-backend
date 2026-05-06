CREATE TABLE IF NOT EXISTS payment_result (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_type ENUM('success', 'failed', 'error') NOT NULL,
    title VARCHAR(255) NOT NULL COMMENT 'Page heading shown to user',
    message TEXT COMMENT 'Description text shown below title',
    sub_message TEXT COMMENT 'Additional info text',
    btn_text VARCHAR(100) DEFAULT 'Back to Home' COMMENT 'Button label',
    btn_link VARCHAR(255) DEFAULT '/' COMMENT 'Button destination URL',
    icon VARCHAR(50) DEFAULT 'check' COMMENT 'Icon identifier: check, x, alert',
    bg_color VARCHAR(20) COMMENT 'Background tint class',
    icon_color VARCHAR(20) COMMENT 'Icon color class',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO payment_result (status_type, title, message, sub_message, btn_text, btn_link, icon, bg_color, icon_color) VALUES
('success', 'Payment Successful!', 'Your recharge has been completed successfully.', 'Amount will be credited to your wallet shortly.', 'Continue', '/', 'check', 'bg-green-50', 'text-green-500'),
('failed', 'Payment Failed', 'Your payment could not be completed.', 'Please try again with a different payment method.', 'Try Again', '/recharge', 'x', 'bg-red-50', 'text-red-500'),
('error', 'Something Went Wrong', 'We encountered an error processing your payment.', 'Please contact support if the issue persists.', 'Go Back', '/recharge', 'alert', 'bg-yellow-50', 'text-yellow-500');
