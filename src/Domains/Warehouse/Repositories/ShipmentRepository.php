<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\Shipment;
use PDO;

class ShipmentRepository implements ShipmentRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): Shipment
    {
        $stmt = $this->db->prepare(
            'INSERT INTO shipments (order_id, pick_list_id, carrier, tracking_number, created_by_user_id, shipped_at)
             VALUES (:order_id, :pick_list_id, :carrier, :tracking_number, :created_by_user_id, NOW())'
        );
        $stmt->execute([
            'order_id' => $data['order_id'],
            'pick_list_id' => $data['pick_list_id'],
            'carrier' => $data['carrier'],
            'tracking_number' => $data['tracking_number'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $shipment = $this->findById($id);
        if ($shipment === null) {
            throw new \RuntimeException('Shipment could not be reloaded after creation.');
        }

        return $shipment;
    }

    public function findById(int $id): ?Shipment
    {
        $stmt = $this->db->prepare('SELECT * FROM shipments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new Shipment($data) : null;
    }

    public function findByOrder(int $orderId): ?Shipment
    {
        $stmt = $this->db->prepare('SELECT * FROM shipments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $data = $stmt->fetch();

        return $data ? new Shipment($data) : null;
    }

    public function findForPickList(int $pickListId): ?Shipment
    {
        $stmt = $this->db->prepare('SELECT * FROM shipments WHERE pick_list_id = :pick_list_id LIMIT 1');
        $stmt->execute(['pick_list_id' => $pickListId]);
        $data = $stmt->fetch();

        return $data ? new Shipment($data) : null;
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM shipments ORDER BY shipped_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): Shipment {
            return new Shipment($row);
        }, $stmt->fetchAll());
    }

    public function countAllForAdmin(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM shipments')->fetchColumn();
    }
}
