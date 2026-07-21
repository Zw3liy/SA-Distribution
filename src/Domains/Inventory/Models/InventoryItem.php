<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Models;

class InventoryItem
{
    /** @var int */
    public $id;

    /** @var int */
    public $productId;

    /** @var int */
    public $warehouseId;

    /** @var int */
    public $quantityOnHand;

    /** @var int */
    public $quantityReserved;

    /** @var int */
    public $reorderThreshold;

    /** @var string */
    public $updatedAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->productId = (int) $data['product_id'];
        $this->warehouseId = (int) $data['warehouse_id'];
        $this->quantityOnHand = (int) $data['quantity_on_hand'];
        $this->quantityReserved = (int) $data['quantity_reserved'];
        $this->reorderThreshold = (int) ($data['reorder_threshold'] ?? 0);
        $this->updatedAt = (string) $data['updated_at'];
    }

    /**
     * The only quantity ever shown to customers or checked at checkout
     * (docs/specs/05-inventory.md §2/§16) -- quantity_on_hand and
     * quantity_reserved are never exposed past the service layer to any
     * customer-facing code path.
     */
    public function quantityAvailable(): int
    {
        return $this->quantityOnHand - $this->quantityReserved;
    }

    public function isBelowReorderThreshold(): bool
    {
        return $this->quantityAvailable() < $this->reorderThreshold;
    }
}
