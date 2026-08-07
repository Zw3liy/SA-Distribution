<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Repositories;

use App\Domains\Warehouse\Models\GoodsReceipt;

interface GoodsReceiptRepositoryInterface
{
    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     */
    public function create(array $data, array $items): GoodsReceipt;

    /** Loaded with its GoodsReceiptItems, per GoodsReceipt::$items. */
    public function findById(int $id): ?GoodsReceipt;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;
}
