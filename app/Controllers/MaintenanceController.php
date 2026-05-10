<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';

class MaintenanceController
{
    private $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    public function getStatus()
    {
        $stmt = $this->db->prepare("SELECT * FROM maintenance_settings WHERE active = 1 LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();

        if (!$settings || $settings['mode'] === 'off') {
            response(['maintenance' => false]);
            return;
        }

        $isInMaintenanceWindow = false;

        if ($settings['mode'] === 'temporary') {
            $now = date('Y-m-d H:i:s');
            $start = $settings['start_time'];
            $end = $settings['end_time'];

            if ($start && $end) {
                $isInMaintenanceWindow = ($now >= $start && $now <= $end);
            } elseif ($start && !$end) {
                $isInMaintenanceWindow = ($now >= $start);
            } elseif (!$start && $end) {
                $isInMaintenanceWindow = ($now <= $end);
            }
        }

        if ($settings['mode'] === 'permanent' || ($settings['mode'] === 'temporary' && $isInMaintenanceWindow)) {
            response([
                'maintenance' => true,
                'mode' => $settings['mode'],
                'title' => $settings['title'] ?: 'Under Maintenance',
                'message' => $settings['message'] ?: 'We are performing scheduled maintenance. Please try again later.',
                'sub_message' => $settings['sub_message'],
                'allow_login' => (bool)$settings['allow_login'],
            ]);
            return;
        }

        response(['maintenance' => false]);
    }

    public function getSettings()
    {
        $stmt = $this->db->query("SELECT * FROM maintenance_settings WHERE active = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            $settings = [
                'id' => null,
                'mode' => 'off',
                'title' => 'Under Maintenance',
                'message' => '',
                'sub_message' => '',
                'start_time' => null,
                'end_time' => null,
                'allow_login' => 1,
                'active' => 1,
            ];
        }

        response($settings);
    }

    public function save()
    {
        $data = getJsonInput();

        $mode = $data['mode'] ?? 'off';
        $title = $data['title'] ?? 'Under Maintenance';
        $message = $data['message'] ?? '';
        $subMessage = $data['sub_message'] ?? '';
        $startTime = $data['start_time'] ?: null;
        $endTime = $data['end_time'] ?: null;
        $allowLogin = isset($data['allow_login']) ? ($data['allow_login'] ? 1 : 0) : 1;

        $stmt = $this->db->prepare("SELECT id FROM maintenance_settings WHERE active = 1 LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE maintenance_settings SET
                    mode = ?,
                    title = ?,
                    message = ?,
                    sub_message = ?,
                    start_time = ?,
                    end_time = ?,
                    allow_login = ?
                WHERE active = 1 LIMIT 1
            ");
            $stmt->execute([$mode, $title, $message, $subMessage, $startTime, $endTime, $allowLogin]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO maintenance_settings (mode, title, message, sub_message, start_time, end_time, allow_login)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$mode, $title, $message, $subMessage, $startTime, $endTime, $allowLogin]);
        }

        response(null, 'Maintenance settings saved');
    }
}
