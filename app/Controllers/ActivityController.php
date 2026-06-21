<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';

class ActivityController {

    private function ensureTable($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS user_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            metadata JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ua_user (user_id),
            INDEX idx_ua_type (activity_type),
            INDEX idx_ua_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function log() {
        try {
            $user = authenticate();
            $userId = $user['id'];
            $data = getJsonInput();
            $db = getDb();

            $this->ensureTable($db);

            $activityType = $data['activity_type'] ?? 'other';
            $metadata = $data['metadata'] ?? null;
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            $stmt = $db->prepare("
                INSERT INTO user_activities (user_id, activity_type, metadata, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $activityType,
                $metadata ? json_encode($metadata) : null,
                $ipAddress,
                $userAgent,
            ]);

            response(['logged' => true]);
        } catch (Throwable $e) {
            error_log('[Activity] ERROR: ' . $e->getMessage());
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['logged' => false]);
            exit;
        }
    }
}
