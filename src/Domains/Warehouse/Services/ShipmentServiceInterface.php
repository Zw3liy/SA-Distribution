<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Warehouse\Models\Shipment;

interface ShipmentServiceInterface
{
    /**
     * Creates the shipment for a fully picked AND packed pick list,
     * moves the pick list to 'shipped', and publishes OrderShipped --
     * which Orders consumes to transition the order to 'shipped'
     * (docs/specs/07-warehouse.md §2/§9/§10).
     *
     * @throws \App\Domains\Warehouse\Exceptions\PickListNotFoundException
     * @throws \InvalidArgumentException when the pick list is not packed
     */
    public function createFor(int $pickListId, string $carrier, ?string $trackingNumber, int $actorUserId): Shipment;

    public function findById(int $id): ?Shipment;

    public function findByOrder(int $orderId): ?Shipment;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
