<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

interface StockMovementRepositoryInterface
{
    public function record(array $data): void;

    public function historyFor(int $inventoryItemId, int $limit): array;
}
