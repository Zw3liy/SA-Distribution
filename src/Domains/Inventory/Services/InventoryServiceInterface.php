<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\StockReservation;
use DateTimeInterface;

interface InventoryServiceInterface
{
    /** null = sum across all locations (docs/specs/05-inventory.md §5). */
    public function availableQuantity(int $productId, ?int $warehouseId = null): int;

    /** @throws \App\Domains\Inventory\Exceptions\InsufficientStockException */
    public function reserve(int $productId, int $warehouseId, int $quantity, string $orderReference): StockReservation;

    public function consumeReservation(int $reservationId): void;

    public function releaseReservation(int $reservationId): void;

    /** @throws \App\Domains\Inventory\Exceptions\InsufficientStockException */
    public function adjust(int $productId, int $warehouseId, int $delta, string $reason, int $actorUserId, bool $allowNegative = false): void;

    /**
     * Not in the spec's minimal §5 list -- added because
     * docs/specs/05-inventory.md §10 requires a zero-quantity
     * InventoryItem to exist for every product from day one, and
     * §19 requires the ProductCreated consumer to be wired. Called
     * from AdminProductController immediately after Catalog creates a
     * product (a direct synchronous call, since no event bus exists --
     * same pattern used for Customers' UserRegistered consumption).
     */
    public function initializeForProduct(int $productId): void;

    /**
     * Not in the spec's minimal §5 list -- the concrete operation
     * behind the "reservation expiry sweep" required by §17/§18 to
     * exist and be tested, run by a scheduled task (infrastructure
     * wiring for that scheduler is a DevOps/Phase-7 concern, out of
     * scope here; the capability itself is in scope per §19).
     */
    public function expireReservations(DateTimeInterface $cutoff): int;
}
