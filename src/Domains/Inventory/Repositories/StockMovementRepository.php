<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\StockMovement;
use PDO;

class StockMovementRepository implements StockMovementRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function record(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stock_movements (inventory_item_id, delta, reason, reference_type, reference_id, actor_user_id, created_at)
             VALUES (:inventory_item_id, :delta, :reason, :reference_type, :reference_id, :actor_user_id, NOW())'
        );
        $stmt->execute([
            'inventory_item_id' => $data['inventory_item_id'],
            'delta' => $data['delta'],
            'reason' => $data['reason'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'actor_user_id' => $data['actor_user_id'] ?? null,
        ]);
    }

    public function historyFor(int $inventoryItemId, int $limit): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stock_movements WHERE inventory_item_id = :inventory_item_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':inventory_item_id', $inventoryItemId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): StockMovement {
            return new StockMovement($row);
        }, $stmt->fetchAll());
    }
}
