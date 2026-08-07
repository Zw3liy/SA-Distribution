<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\PackingSlip;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\PickListItem;

interface PickListRepositoryInterface
{
    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function create(array $data, array $items): PickList;

    /** Loaded with its PickListItems, per PickList::$items. */
    public function findById(int $id): ?PickList;

    /** Loaded with its PickListItems. Null when the order has no pick list yet. */
    public function findByOrder(int $orderId): ?PickList;

    public function findItemById(int $itemId): ?PickListItem;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;

    public function updateStatus(int $id, string $status): void;

    public function markItemPicked(int $itemId): void;

    public function createPackingSlip(int $pickListId, int $packedByUserId): PackingSlip;
}
