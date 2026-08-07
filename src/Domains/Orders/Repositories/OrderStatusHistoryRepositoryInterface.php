<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

interface OrderStatusHistoryRepositoryInterface
{
    public function record(int $orderId, string $from, string $to, ?int $actorUserId, ?string $note): void;

    public function historyFor(int $orderId): array;
}
