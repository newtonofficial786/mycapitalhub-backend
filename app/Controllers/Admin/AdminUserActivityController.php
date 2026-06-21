<?php

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';
require_once __DIR__ . '/../../../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../../app/Middleware/AdminMiddleware.php';

class AdminUserActivityController {

    public function list() {
        authenticateAdmin();
        $data = getJsonInput();
        $db = getDb();

        $userId = $data['user_id'] ?? null;
        $activityType = $data['activity_type'] ?? null;
        $mobileSearch = $data['mobile'] ?? null;
        $page = max(1, (int)($data['page'] ?? 1));
        $limit = min(100, max(1, (int)($data['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];

        if ($userId) {
            $where .= " AND ua.user_id = ?";
            $params[] = $userId;
        }
        if ($activityType) {
            $where .= " AND ua.activity_type = ?";
            $params[] = $activityType;
        }
        if ($mobileSearch) {
            $where .= " AND u.mobile LIKE ?";
            $params[] = '%' . $mobileSearch . '%';
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM user_activities ua JOIN users u ON ua.user_id = u.id WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT ua.id, ua.user_id, ua.activity_type, ua.metadata, ua.ip_address, ua.created_at,
                   u.mobile, u.level
            FROM user_activities ua
            JOIN users u ON ua.user_id = u.id
            WHERE $where
            ORDER BY ua.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activities as &$a) {
            $a['metadata'] = json_decode($a['metadata'] ?? '{}', true);
        }

        response([
            'activities' => $activities,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
        ]);
    }

    public function userHistory() {
        authenticateAdmin();
        $data = getJsonInput();
        $db = getDb();

        $userId = (int)($data['user_id'] ?? 0);
        if (!$userId) {
            error('User ID required');
        }

        $limit = min(100, max(1, (int)($data['limit'] ?? 20)));

        $stmt = $db->prepare("
            SELECT ua.id, ua.activity_type, ua.metadata, ua.ip_address, ua.created_at,
                   u.mobile, u.level
            FROM user_activities ua
            JOIN users u ON ua.user_id = u.id
            WHERE ua.user_id = ?
            ORDER BY ua.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activities as &$a) {
            $a['metadata'] = json_decode($a['metadata'] ?? '{}', true);
        }

        response([
            'activities' => $activities,
            'user_id' => $userId,
        ]);
    }

    public function stats() {
        authenticateAdmin();
        $db = getDb();

        $today = $db->query("
            SELECT activity_type, COUNT(*) as count
            FROM user_activities
            WHERE DATE(created_at) = CURDATE()
            GROUP BY activity_type
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $last7days = $db->query("
            SELECT DATE(created_at) as day, COUNT(*) as count
            FROM user_activities
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $topUsers = $db->query("
            SELECT ua.user_id, u.mobile, COUNT(*) as activity_count
            FROM user_activities ua
            JOIN users u ON ua.user_id = u.id
            WHERE ua.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY ua.user_id
            ORDER BY activity_count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        response([
            'today' => $today,
            'last_7_days' => $last7days,
            'top_users' => $topUsers,
        ]);
    }
}
