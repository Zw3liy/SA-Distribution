<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Models;

class StockReservation
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';

    /** @var int */
    public $id;

    /** @var int */
    public $inventoryItemId;

    /**
     * A string, not yet a real FK to an `orders` table, since Orders
     * (domain #6) doesn't exist until after Inventory in the
     * implementation order (docs/specs/05-inventory.md §4). Becomes a
     * proper FK when Orders is built.
     *
     * @var string
     */
    public $orderReference;

    /** @var int */
    public $quantity;

    /** @var string */
    public $expiresAt;

    /** @var string */
    public $status;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->inventoryItemId = (int) $data['inventory_item_id'];
        $this->orderReference = (string) $data['order_reference'];
        $this->quantity = (int) $data['quantity'];
        $this->expiresAt = (string) $data['expires_at'];
        $this->status = (string) $data['status'];
        $this->createdAt = (string) $data['created_at'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) < time();
    }
}
