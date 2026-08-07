<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\PackingSlip;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\PickListItem;
use PDO;
use Throwable;

/**
 * Owns pick_lists, pick_list_items, and packing_slips -- the three
 * tables are one aggregate (docs/specs/07-warehouse.md §3/§6), so a
 * single repository keeps the create-with-items transaction in one
 * place, mirroring Orders' OrderRepository::create() pattern.
 */
class PickListRepository implements PickListRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, array $items): PickList
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO pick_lists (order_id, warehouse_id, status, created_at, updated_at)
                 VALUES (:order_id, :warehouse_id, :status, NOW(), NOW())'
            );
            $stmt->execute([
                'order_id' => $data['order_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => $data['status'],
            ]);

            $pickListId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO pick_list_items (pick_list_id, product_id, quantity, created_at)
                 VALUES (:pick_list_id, :product_id, :quantity, NOW())'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'pick_list_id' => $pickListId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $pickList = $this->findById($pickListId);
        if ($pickList === null) {
            throw new \RuntimeException('Pick list could not be reloaded after creation.');
        }

        return $pickList;
    }

    public function findById(int $id): ?PickList
    {
        $stmt = $this->db->prepare('SELECT * FROM pick_lists WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        $pickList = new PickList($data);
        $pickList->items = $this->loadItems($id);

        return $pickList;
    }

    public function findByOrder(int $orderId): ?PickList
    {
        $stmt = $this->db->prepare('SELECT * FROM pick_lists WHERE order_id = :order_id LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        $pickList = new PickList($data);
        $pickList->items = $this->loadItems($pickList->id);

        return $pickList;
    }

    public function findItemById(int $itemId): ?PickListItem
    {
        $stmt = $this->db->prepare('SELECT * FROM pick_list_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $data = $stmt->fetch();

        return $data ? new PickListItem($data) : null;
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pick_lists ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): PickList {
            return new PickList($row);
        }, $stmt->fetchAll());
    }

    public function countAllForAdmin(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM pick_lists')->fetchColumn();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE pick_lists SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function markItemPicked(int $itemId): void
    {
        $stmt = $this->db->prepare('UPDATE pick_list_items SET picked_at = NOW() WHERE id = :id AND picked_at IS NULL');
        $stmt->execute(['id' => $itemId]);
    }

    public function createPackingSlip(int $pickListId, int $packedByUserId): PackingSlip
    {
        $stmt = $this->db->prepare(
            'INSERT INTO packing_slips (pick_list_id, packed_by_user_id, packed_at)
             VALUES (:pick_list_id, :packed_by_user_id, NOW())'
        );
        $stmt->execute(['pick_list_id' => $pickListId, 'packed_by_user_id' => $packedByUserId]);

        $id = (int) $this->db->lastInsertId();
        $slip = $this->db->query('SELECT * FROM packing_slips WHERE id = ' . $id)->fetch();

        return new PackingSlip($slip);
    }

    private function loadItems(int $pickListId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pick_list_items WHERE pick_list_id = :pick_list_id ORDER BY id ASC');
        $stmt->execute(['pick_list_id' => $pickListId]);

        return array_map(static function (array $row): PickListItem {
            return new PickListItem($row);
        }, $stmt->fetchAll());
    }
}
