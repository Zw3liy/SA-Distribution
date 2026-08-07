<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\StockTransfer;
use App\Domains\Warehouse\Models\StockTransferItem;
use PDO;
use Throwable;

class StockTransferRepository implements StockTransferRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, array $items): StockTransfer
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO stock_transfers (from_warehouse_id, to_warehouse_id, status, initiated_by_user_id, created_at)
                 VALUES (:from_warehouse_id, :to_warehouse_id, :status, :initiated_by_user_id, NOW())'
            );
            $stmt->execute([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => $data['status'],
                'initiated_by_user_id' => $data['initiated_by_user_id'],
            ]);

            $transferId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO stock_transfer_items (stock_transfer_id, product_id, quantity)
                 VALUES (:stock_transfer_id, :product_id, :quantity)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'stock_transfer_id' => $transferId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $transfer = $this->findById($transferId);
        if ($transfer === null) {
            throw new \RuntimeException('Stock transfer could not be reloaded after creation.');
        }

        return $transfer;
    }

    public function findById(int $id): ?StockTransfer
    {
        $stmt = $this->db->prepare('SELECT * FROM stock_transfers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        $transfer = new StockTransfer($data);
        $transfer->items = $this->loadItems($id);

        return $transfer;
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stock_transfers ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): StockTransfer {
            return new StockTransfer($row);
        }, $stmt->fetchAll());
    }

    public function countAllForAdmin(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM stock_transfers')->fetchColumn();
    }

    public function updateStatus(int $id, string $status): void
    {
        if ($status === StockTransfer::STATUS_COMPLETED) {
            $stmt = $this->db->prepare('UPDATE stock_transfers SET status = :status, completed_at = NOW() WHERE id = :id');
        } else {
            $stmt = $this->db->prepare('UPDATE stock_transfers SET status = :status WHERE id = :id');
        }
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    private function loadItems(int $transferId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM stock_transfer_items WHERE stock_transfer_id = :stock_transfer_id ORDER BY id ASC');
        $stmt->execute(['stock_transfer_id' => $transferId]);

        return array_map(static function (array $row): StockTransferItem {
            return new StockTransferItem($row);
        }, $stmt->fetchAll());
    }
}
