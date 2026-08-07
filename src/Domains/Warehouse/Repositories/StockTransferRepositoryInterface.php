<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\StockTransfer;

interface StockTransferRepositoryInterface
{
    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function create(array $data, array $items): StockTransfer;

    /** Loaded with its StockTransferItems, per StockTransfer::$items. */
    public function findById(int $id): ?StockTransfer;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;

    /** Sets completed_at when the transfer reaches 'completed'. */
    public function updateStatus(int $id, string $status): void;
}
