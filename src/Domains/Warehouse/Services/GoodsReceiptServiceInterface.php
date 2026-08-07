<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Warehouse\Models\GoodsReceipt;

interface GoodsReceiptServiceInterface
{
    /**
     * Records an inbound receipt and increases on-hand stock at the
     * receiving warehouse via InventoryServiceInterface::adjust() with
     * reason 'goods_receipt' (docs/specs/07-warehouse.md §2).
     *
     * $purchaseOrderId is nullable until Suppliers (domain #8) exists.
     * $allowOverReceipt is the §8 staff override: without it, receiving
     * more than the referenced purchase order allows is rejected (the
     * ordered-quantity comparison becomes live when #8 lands).
     *
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function receive(
        ?int $purchaseOrderId,
        int $warehouseId,
        array $items,
        int $actorUserId,
        bool $allowOverReceipt = false
    ): GoodsReceipt;

    public function findById(int $id): ?GoodsReceipt;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
