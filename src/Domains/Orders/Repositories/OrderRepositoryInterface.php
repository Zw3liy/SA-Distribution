<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use App\Domains\Orders\Models\Order;

interface OrderRepositoryInterface
{
    /**
     * @param array<int, array{product_id: int, sku: string, name_snapshot: string, quantity: int, unit_price_snapshot: float, line_total: float}> $items
     */
    public function create(array $data, array $items): Order;

    /** Loaded with its OrderItems, per Order::$items. */
    public function findById(int $id): ?Order;

    /** Paginated, indexed on (customer_id, placed_at) per §17. */
    public function findByCustomer(int $customerId, int $limit, int $offset): array;

    public function countByCustomer(int $customerId): int;

    public function findAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;

    public function updateStatus(int $id, string $status): void;
}
