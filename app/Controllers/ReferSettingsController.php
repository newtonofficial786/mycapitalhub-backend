<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../Helpers.php';

class ReferSettingsController {
    public function getSettings() {
        $db = getDb();
        $stmt = $db->query("SELECT * FROM refer_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();
        
        if ($settings && isset($settings['commission_percentage'])) {
            $settings['commission_percentage'] = floatval($settings['commission_percentage']);
        }
        
        response($settings);
    }
    
    public function updateSettings() {
        authenticate();
        $data = getJsonInput();
        
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE refer_settings SET 
                title = ?,
                description = ?,
                tiers = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'] ?? 'Invite your friends',
            $data['description'] ?? '',
            json_encode($data['tiers'] ?? []),
            $data['id'] ?? 1
        ]);
        
        response(null, 'Settings updated');
    }
}