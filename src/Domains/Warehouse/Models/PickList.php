<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

/**
 * Generated automatically when an order transitions to 'fulfilling'
 * (docs/specs/07-warehouse.md §2/§10 -- the OrderStatusChanged event).
 * Status progression: open -> picking -> picked -> packed -> shipped,
 * with cancelled as the abort path.
 */
class PickList
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PICKING = 'picking';
    public const STATUS_PICKED = 'picked';
    public const STATUS_PACKED = 'packed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var int */
    public $id;

    /** @var int */
    public $orderId;

    /** @var int */
    public $warehouseId;

    /** @var string */
    public $status;

    /** @var string */
    public $createdAt;

    /** @var string */
    public $updatedAt;

    /** @var PickListItem[] */
    public $items = [];

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->orderId = (int) $data['order_id'];
        $this->warehouseId = (int) $data['warehouse_id'];
        $this->status = (string) $data['status'];
        $this->createdAt = (string) $data['created_at'];
        $this->updatedAt = (string) $data['updated_at'];
    }
}
