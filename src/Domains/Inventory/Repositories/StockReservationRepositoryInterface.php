<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\StockReservation;
use DateTimeInterface;

interface StockReservationRepositoryInterface
{
    public function create(array $data): StockReservation;

    public function findById(int $id): ?StockReservation;

    public function markConsumed(int $id): void;

    public function markReleased(int $id): void;

    /**
     * Run by a scheduled task, not the request path
     * (docs/specs/05-inventory.md §17) -- releases every still-'active'
     * reservation whose expires_at is before $cutoff and returns the
     * count released, so the caller can log a single info-level summary
     * rather than one log line per reservation (§15).
     */
    public function expireOlderThan(DateTimeInterface $cutoff): int;
}
