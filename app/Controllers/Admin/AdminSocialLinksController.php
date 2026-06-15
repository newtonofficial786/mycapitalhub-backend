<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminSocialLinksController {

    public function getAll() {
        authenticateAdmin();

        $db = getDb();
        $rows = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

        response([
            'telegram_link' => $rows['telegram_link'] ?? '',
            'support_link'  => $rows['support_link']  ?? '',
            'channel_link'  => $rows['channel_link']  ?? '',
        ]);
    }

    public function update() {
        authenticateAdmin();
        $data = getJsonInput();

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        $links = [
            'telegram_link' => $data['telegram_link'] ?? '',
            'support_link'  => $data['support_link']  ?? '',
            'channel_link'  => $data['channel_link']  ?? '',
        ];

        foreach ($links as $key => $value) {
            $stmt->execute([$key, trim($value)]);
        }

        response(null, 'Social links updated');
    }

    public function getPublic() {
        $db = getDb();
        $rows = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('telegram_link','support_link','channel_link')")->fetchAll(PDO::FETCH_KEY_PAIR);

        response([
            'telegram_link' => $rows['telegram_link'] ?? '',
            'support_link'  => $rows['support_link']  ?? '',
            'channel_link'  => $rows['channel_link']  ?? '',
        ]);
    }
}
