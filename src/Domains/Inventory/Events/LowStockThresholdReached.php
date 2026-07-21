<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Events;

/**
 * Fires (conceptually -- not dispatched, see StockReserved's docblock)
 * when quantity_available crosses below reorder_threshold. Intended
 * future consumers per docs/specs/05-inventory.md §9: Suppliers (domain
 * #8, auto-PO-suggestion) and Administration/Analytics (alerting).
 */
final class LowStockThresholdReached
{
    /** @var int */
    public $inventoryItemId;

    /** @var int */
    public $productId;

    /** @var int */
    public $quantityAvailable;

    /** @var int */
    public $reorderThreshold;

    public function __construct(int $inventoryItemId, int $productId, int $quantityAvailable, int $reorderThreshold)
    {
        $this->inventoryItemId = $inventoryItemId;
        $this->productId = $productId;
        $this->quantityAvailable = $quantityAvailable;
        $this->reorderThreshold = $reorderThreshold;
    }
}
