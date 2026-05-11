<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../app/Helpers.php';

class ImageManagerController {
    private $uploadDir;

    public function __construct() {
        $this->uploadDir = __DIR__ . '/../../../uploads/images/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function upload() {
        header('Content-Type: application/json');

        if (!isset($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No image file provided']);
            return;
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Upload error: ' . $file['error']]);
            return;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Allowed: jpg, png, gif, webp']);
            return;
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large. Max 5MB']);
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $ext;
        $filepath = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file']);
            return;
        }

        $baseUrl = $this->getBaseUrl();
        $imageUrl = $baseUrl . '/uploads/images/' . $filename;

        $db = getDb();
        $stmt = $db->prepare("INSERT INTO media_library (filename, original_name, file_path, file_url, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $file['name'], $filepath, $imageUrl, $mimeType, $file['size']]);
        $id = $db->lastInsertId();
        $stmt->closeCursor();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'id' => $id,
            'filename' => $filename,
            'url' => $imageUrl,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $mimeType
        ]);
    }

    public function list() {
        header('Content-Type: application/json');

        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 24;
        $offset = ($page - 1) * $limit;

        $sort = $_GET['sort'] ?? 'newest';
        $allowedSorts = [
            'newest' => ['column' => 'created_at', 'direction' => 'DESC'],
            'oldest' => ['column' => 'created_at', 'direction' => 'ASC'],
            'name' => ['column' => 'original_name', 'direction' => 'ASC'],
        ];
        $sortConfig = $allowedSorts[$sort] ?? $allowedSorts['newest'];
        $orderCol = $sortConfig['column'];
        $orderDir = $sortConfig['direction'];

        $db = getDb();
        $countResult = $db->query("SELECT COUNT(*) as total FROM media_library");
        $totalRow = $countResult->fetch();
        $total = $totalRow['total'];

        $sql = "SELECT * FROM media_library ORDER BY $orderCol $orderDir LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $images = $stmt->fetchAll();
        $stmt->closeCursor();

        echo json_encode([
            'success' => true,
            'images' => $images,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
    }

    public function delete() {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? intval($input['id']) : 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM media_library WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        $stmt->closeCursor();

        if (!$image) {
            http_response_code(404);
            echo json_encode(['error' => 'Image not found']);
            return;
        }

        if (file_exists($image['file_path'])) {
            unlink($image['file_path']);
        }

        $stmt = $db->prepare("DELETE FROM media_library WHERE id = ?");
        $stmt->execute([$id]);
        $stmt->closeCursor();

        echo json_encode(['success' => true, 'message' => 'Image deleted']);
    }

    public function getDetails() {
        header('Content-Type: application/json');

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM media_library WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();
        $stmt->closeCursor();

        if (!$image) {
            http_response_code(404);
            echo json_encode(['error' => 'Image not found']);
            return;
        }

        echo json_encode([
            'success' => true,
            'image' => $image
        ]);
    }

    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'najira.in';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
        $scriptDir = str_replace('/api', '', $scriptDir);
        return rtrim($protocol . '://' . $host . $scriptDir, '/');
    }
}