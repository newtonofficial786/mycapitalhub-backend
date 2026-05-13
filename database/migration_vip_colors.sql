ALTER TABLE vip_packages ADD COLUMN color_from VARCHAR(7) DEFAULT '#6b7280' AFTER wait_minutes;
ALTER TABLE vip_packages ADD COLUMN color_to VARCHAR(7) DEFAULT '#1f2937' AFTER color_from;
