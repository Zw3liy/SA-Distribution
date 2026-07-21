<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\InventoryItem;
use PDO;
use RuntimeException;

class InventoryItemRepository implements InventoryItemRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findFor(int $productId, int $warehouseId): ?InventoryItem
    {
        // inventory_items(product_id, warehouse_id) unique index doubles
        // as the lookup index (docs/specs/05-inventory.md §17) -- this
        // is a single indexed query, called on every product-detail page
        // view.
        $stmt = $this->db->prepare('SELECT * FROM inventory_items WHERE product_id = :product_id AND warehouse_id = :warehouse_id LIMIT 1');
        $stmt->execute(['product_id' => $productId, 'warehouse_id' => $warehouseId]);
        $data = $stmt->fetch();

        return $data ? new InventoryItem($data) : null;
    }

    public function findById(int $id): ?InventoryItem
    {
        $stmt = $this->db->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new InventoryItem($data) : null;
    }

    public function sumAvailable(int $productId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) FROM inventory_items WHERE product_id = :product_id');
        $stmt->execute(['product_id' => $productId]);

        return (int) $stmt->fetchColumn();
    }

    public function upsertOnHand(int $productId, int $warehouseId, int $quantityOnHand): InventoryItem
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold, updated_at)
             VALUES (:product_id, :warehouse_id, :quantity_on_hand, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE quantity_on_hand = VALUES(quantity_on_hand), updated_at = NOW()'
        );
        $stmt->execute([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_on_hand' => $quantityOnHand,
        ]);

        $item = $this->findFor($productId, $warehouseId);
        if ($item === null) {
            throw new RuntimeException('Inventory item could not be reloaded after upsert.');
        }

        return $item;
    }

    public function tryReserve(int $inventoryItemId, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE inventory_items
             SET quantity_reserved = quantity_reserved + :quantity, updated_at = NOW()
             WHERE id = :id AND (quantity_on_hand - quantity_reserved) >= :quantity_check'
        );
        $stmt->execute(['quantity' => $quantity, 'quantity_check' => $quantity, 'id' => $inventoryItemId]);

        return $stmt->rowCount() > 0;
    }

    public function releaseReserved(int $inventoryItemId, int $quantity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inventory_items
             SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['quantity' => $quantity, 'id' => $inventoryItemId]);
    }

    public function tryAdjustOnHand(int $inventoryItemId, int $delta, bool $allowNegative): bool
    {
        if ($allowNegative) {
            $stmt = $this->db->prepare(
                'UPDATE inventory_items SET quantity_on_hand = quantity_on_hand + :delta, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute(['delta' => $delta, 'id' => $inventoryItemId]);

            return $stmt->rowCount() > 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE inventory_items
             SET quantity_on_hand = quantity_on_hand + :delta, updated_at = NOW()
             WHERE id = :id AND (quantity_on_hand + :delta_check) >= 0'
        );
        $stmt->execute(['delta' => $delta, 'delta_check' => $delta, 'id' => $inventoryItemId]);

        return $stmt->rowCount() > 0;
    }

    public function deductOnHand(int $inventoryItemId, int $quantity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inventory_items
             SET quantity_on_hand = quantity_on_hand - :quantity, quantity_reserved = GREATEST(0, quantity_reserved - :quantity2), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['quantity' => $quantity, 'quantity2' => $quantity, 'id' => $inventoryItemId]);
    }

    public function listForWarehouse(int $warehouseId, string $search, int $limit, int $offset): array
    {
        $sql = 'SELECT ii.*, p.name AS product_name, p.sku AS product_sku
                FROM inventory_items ii
                JOIN products p ON p.id = ii.product_id
                WHERE ii.warehouse_id = :warehouse_id';
        $params = ['warehouse_id' => $warehouseId];

        if ($search !== '') {
            $sql .= ' AND (p.name LIKE :search_name OR p.sku LIKE :search_sku)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_sku'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY p.name ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForWarehouse(int $warehouseId, string $search): int
    {
        $sql = 'SELECT COUNT(*) FROM inventory_items ii JOIN products p ON p.id = ii.product_id WHERE ii.warehouse_id = :warehouse_id';
        $params = ['warehouse_id' => $warehouseId];

        if ($search !== '') {
            $sql .= ' AND (p.name LIKE :search_name OR p.sku LIKE :search_sku)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_sku'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
