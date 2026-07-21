<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Events;

/**
 * Not dispatched -- see StockReserved's docblock for why.
 */
final class StockReleased
{
    /** @var int */
    public $reservationId;

    /** @var int */
    public $inventoryItemId;

    /** @var int */
    public $quantity;

    public function __construct(int $reservationId, int $inventoryItemId, int $quantity)
    {
        $this->reservationId = $reservationId;
        $this->inventoryItemId = $inventoryItemId;
        $this->quantity = $quantity;
    }
}
