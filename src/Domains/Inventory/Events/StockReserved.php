<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Events;

/**
 * Documents the public contract of "stock was reserved" per
 * docs/specs/05-inventory.md §9. Not dispatched through a real event
 * bus -- this platform has none (see Catalog's Event DTOs for the same
 * situation) -- kept as an immutable DTO for when Orders (domain #6)
 * or a future bus needs a real subscriber.
 */
final class StockReserved
{
    /** @var int */
    public $reservationId;

    /** @var int */
    public $inventoryItemId;

    /** @var int */
    public $quantity;

    /** @var string */
    public $orderReference;

    public function __construct(int $reservationId, int $inventoryItemId, int $quantity, string $orderReference)
    {
        $this->reservationId = $reservationId;
        $this->inventoryItemId = $inventoryItemId;
        $this->quantity = $quantity;
        $this->orderReference = $orderReference;
    }
}
