<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Models\Order;

interface OrderServiceInterface
{
    public function findById(int $id): ?Order;

    /** @throws \App\Domains\Orders\Exceptions\InvalidOrderTransitionException */
    public function transition(int $orderId, string $toStatus, ?int $actorUserId, ?string $note): void;

    public function cancel(int $orderId, string $reason): void;

    /**
     * Not in the spec's minimal §5 list -- needed for the customer
     * order-history page (docs/specs/06-orders.md §13) and the admin
     * order list (§7), both of which need paginated access beyond a
     * single findById().
     */
    public function myOrders(int $customerId, int $limit, int $offset): array;

    public function countMyOrders(int $customerId): int;

    public function listAllForAdmin(int $limit, int $offset): array;

    public function countAllForAdmin(): int;

    public function statusHistoryFor(int $orderId): array;
}
