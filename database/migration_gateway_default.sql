ALTER TABLE payment_methods ADD COLUMN is_default TINYINT(1) DEFAULT 0 COMMENT 'Whether this is the default selected gateway';

UPDATE payment_methods SET is_default = 1 WHERE backend_key = 'watchpays' LIMIT 1;
