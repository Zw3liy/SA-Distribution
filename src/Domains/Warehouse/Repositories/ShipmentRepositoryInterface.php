<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\Shipment;

interface ShipmentRepositoryInterface
{
    public function create(array $data): Shipment;

    public function findById(int $id): ?Shipment;

    public function findByOrder(int $orderId): ?Shipment;

    public function findForPickList(int $pickListId): ?Shipment;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
