<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Events;

/**
 * Published when a stock transfer completes (docs/specs/07-warehouse.md
 * §9) -- i.e. after the atomic source/destination adjustment pair has
 * been committed.
 */
final class StockTransferCompleted
{
    /** @var int */
    public $transferId;

    /** @var int */
    public $fromWarehouseId;

    /** @var int */
    public $toWarehouseId;

    public function __construct(int $transferId, int $fromWarehouseId, int $toWarehouseId)
    {
        $this->transferId = $transferId;
        $this->fromWarehouseId = $fromWarehouseId;
        $this->toWarehouseId = $toWarehouseId;
    }
}
