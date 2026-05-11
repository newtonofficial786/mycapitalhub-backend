<?php

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../app/Helpers.php';

class RechargeSettingsController
{
    private $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    public function index()
    {
        $stmt = $this->db->prepare("SELECT amount FROM recharge_settings WHERE active = 1 ORDER BY `order` ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        response(['amounts' => array_map(fn($r) => (int)$r['amount'], $rows)]);
    }

    public function adminList()
    {
        $stmt = $this->db->prepare("SELECT id, `order`, amount FROM recharge_settings WHERE active = 1 ORDER BY `order` ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        response(['amounts' => array_map(fn($r) => ['id' => (int)$r['id'], 'amount' => (int)$r['amount']], $rows)]);
    }

    public function save()
    {
        $data = getJsonInput();
        $action = $data['action'] ?? '';

        if ($action === 'reorder') {
            $items = $data['items'] ?? [];
            foreach ($items as $i => $item) {
                $stmt = $this->db->prepare("UPDATE recharge_settings SET `order` = ? WHERE id = ?");
                $stmt->execute([$i + 1, $item['id']]);
            }
            response(null, 'Reordered');
            return;
        }

        if ($action === 'delete') {
            $stmt = $this->db->prepare("UPDATE recharge_settings SET active = 0 WHERE id = ?");
            $stmt->execute([$data['id']]);
            response(null, 'Deleted');
            return;
        }

        if ($action === 'add') {
            $amount = (int)($data['amount'] ?? 0);
            if ($amount <= 0) {
                error('Amount must be greater than 0');
                return;
            }
            $stmt = $this->db->query("SELECT MAX(`order`) as max_order FROM recharge_settings WHERE active = 1");
            $maxOrder = (int)($stmt->fetch()['max_order'] ?? 0);
            $stmt = $this->db->prepare("INSERT INTO recharge_settings (`order`, amount) VALUES (?, ?)");
            $stmt->execute([$maxOrder + 1, $amount]);
            response(['id' => $this->db->lastInsertId()], 'Amount added');
            return;
        }

        if ($action === 'update') {
            $amount = (int)($data['amount'] ?? 0);
            if ($amount <= 0) {
                error('Amount must be greater than 0');
                return;
            }
            $stmt = $this->db->prepare("UPDATE recharge_settings SET amount = ? WHERE id = ?");
            $stmt->execute([$amount, $data['id']]);
            response(null, 'Updated');
            return;
        }

        error('Invalid action');
    }
}