<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\GoodsReceipt;
use App\Domains\Warehouse\Models\GoodsReceiptItem;
use PDO;
use Throwable;

class GoodsReceiptRepository implements GoodsReceiptRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, array $items): GoodsReceipt
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO goods_receipts (purchase_order_id, warehouse_id, received_by_user_id, reference, received_at)
                 VALUES (:purchase_order_id, :warehouse_id, :received_by_user_id, :reference, NOW())'
            );
            $stmt->execute([
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'received_by_user_id' => $data['received_by_user_id'],
                'reference' => $data['reference'] ?? null,
            ]);

            $receiptId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO goods_receipt_items (goods_receipt_id, product_id, quantity)
                 VALUES (:goods_receipt_id, :product_id, :quantity)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'goods_receipt_id' => $receiptId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $receipt = $this->findById($receiptId);
        if ($receipt === null) {
            throw new \RuntimeException('Goods receipt could not be reloaded after creation.');
        }

        return $receipt;
    }

    public function findById(int $id): ?GoodsReceipt
    {
        $stmt = $this->db->prepare('SELECT * FROM goods_receipts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        $receipt = new GoodsReceipt($data);
        $receipt->items = $this->loadItems($id);

        return $receipt;
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM goods_receipts ORDER BY received_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): GoodsReceipt {
            return new GoodsReceipt($row);
        }, $stmt->fetchAll());
    }

    public function countAllForAdmin(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM goods_receipts')->fetchColumn();
    }

    private function loadItems(int $receiptId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM goods_receipt_items WHERE goods_receipt_id = :goods_receipt_id ORDER BY id ASC');
        $stmt->execute(['goods_receipt_id' => $receiptId]);

        return array_map(static function (array $row): GoodsReceiptItem {
            return new GoodsReceiptItem($row);
        }, $stmt->fetchAll());
    }
}
