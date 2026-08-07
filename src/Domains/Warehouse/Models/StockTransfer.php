<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

/**
 * Inter-warehouse stock movement. Completing a transfer applies an
 * atomic pair of Inventory adjustments -- negative at the source,
 * positive at the destination, never one without the other
 * (docs/specs/07-warehouse.md §2/§14).
 */
class StockTransfer
{
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var int */
    public $id;

    /** @var int */
    public $fromWarehouseId;

    /** @var int */
    public $toWarehouseId;

    /** @var string */
    public $status;

    /** @var int */
    public $initiatedByUserId;

    /** @var string */
    public $createdAt;

    /** @var string|null */
    public $completedAt;

    /** @var StockTransferItem[] */
    public $items = [];

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->fromWarehouseId = (int) $data['from_warehouse_id'];
        $this->toWarehouseId = (int) $data['to_warehouse_id'];
        $this->status = (string) $data['status'];
        $this->initiatedByUserId = (int) $data['initiated_by_user_id'];
        $this->createdAt = (string) $data['created_at'];
        $this->completedAt = $data['completed_at'] ?? null;
    }
}
