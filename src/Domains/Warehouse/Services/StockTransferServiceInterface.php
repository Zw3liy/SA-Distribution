<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Warehouse\Models\StockTransfer;

interface StockTransferServiceInterface
{
    /**
     * Creates an 'in_transit' transfer. Nothing moves until complete().
     *
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function initiate(int $fromWarehouseId, int $toWarehouseId, array $items, int $actorUserId): StockTransfer;

    /**
     * Applies the atomic source/destination adjustment pair
     * (docs/specs/07-warehouse.md §2/§14): a single database transaction
     * covers both Inventory adjustments and the status update, so a
     * failure partway through leaves nothing applied.
     *
     * @throws \App\Domains\Warehouse\Exceptions\TransferAlreadyCompletedException
     */
    public function complete(int $transferId): void;

    public function findById(int $id): ?StockTransfer;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
