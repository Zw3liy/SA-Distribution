<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\InventoryItem;

interface InventoryItemRepositoryInterface
{
    public function findFor(int $productId, int $warehouseId): ?InventoryItem;

    public function findById(int $id): ?InventoryItem;

    public function sumAvailable(int $productId): int;

    /**
     * Creates the item if it doesn't exist yet (used when a Product is
     * created -- docs/specs/05-inventory.md §10), or sets
     * quantity_on_hand directly if it does. Not audit-trailed itself
     * (no StockMovement) -- InventoryService is responsible for writing
     * a movement row for any call site that represents a real quantity
     * change rather than initial zero-state creation.
     */
    public function upsertOnHand(int $productId, int $warehouseId, int $quantityOnHand): InventoryItem;

    /**
     * Atomically increments quantity_reserved, but only if doing so
     * would not exceed quantity_on_hand -- i.e. a single conditional
     * UPDATE ... WHERE quantity_on_hand - quantity_reserved >= :quantity,
     * not a read-then-write pair. This is the concurrency-safety
     * mechanism called out in docs/specs/05-inventory.md §18: two
     * simultaneous reserve() calls for the last unit of stock cannot
     * both succeed, because the database itself enforces the
     * conditional row match, not application-level logic. Returns
     * false (0 rows affected) if there was insufficient available
     * stock, letting the service layer distinguish that case
     * atomically rather than via a race-prone check-then-act.
     */
    public function tryReserve(int $inventoryItemId, int $quantity): bool;

    public function releaseReserved(int $inventoryItemId, int $quantity): void;

    /**
     * Same atomic-conditional-update approach as tryReserve(): applies
     * $delta to quantity_on_hand only if the result would not go
     * negative, unless $allowNegative is true. Returns false if the
     * conditional update matched no row (i.e. would have gone
     * negative and allowNegative was false).
     */
    public function tryAdjustOnHand(int $inventoryItemId, int $delta, bool $allowNegative): bool;

    /**
     * Used only by consumeReservation(), where stock permanently leaves
     * on-hand (reservation -> real deduction) -- unlike tryAdjustOnHand,
     * this always succeeds for a valid reservation since the quantity
     * was already proven available at reserve() time.
     */
    public function deductOnHand(int $inventoryItemId, int $quantity): void;

    /**
     * Admin stock-list screen (docs/specs/05-inventory.md §13),
     * per-warehouse, with product name/SKU joined in for display and an
     * optional search term.
     */
    public function listForWarehouse(int $warehouseId, string $search, int $limit, int $offset): array;

    public function countForWarehouse(int $warehouseId, string $search): int;
}
