<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Warehouse\Models\PickList;

interface PickListServiceInterface
{
    /**
     * Generates (or returns the existing) pick list for an order, with
     * one line per product aggregating the order's line quantities.
     * Triggered by the OrderStatusChanged subscriber on paid->fulfilling
     * (docs/specs/07-warehouse.md §10).
     */
    public function generateFor(int $orderId): PickList;

    /**
     * Marks a single pick-list line picked and consumes the matching
     * StockReservation via InventoryServiceInterface (spec §2). When the
     * last outstanding line is picked, the pick list auto-completes to
     * 'picked' and OrderPicked is dispatched.
     *
     * @throws \App\Domains\Warehouse\Exceptions\PickListAlreadyCompleteException
     */
    public function markPicked(int $pickListItemId, int $actorUserId): void;

    /**
     * Records the packing slip and moves the pick list to 'packed'.
     * Rejects unless every line is already picked (spec §2).
     *
     * @throws \App\Domains\Warehouse\Exceptions\PickListAlreadyCompleteException
     */
    public function markPacked(int $pickListId, int $actorUserId): void;

    public function findById(int $id): ?PickList;

    public function findByOrder(int $orderId): ?PickList;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
