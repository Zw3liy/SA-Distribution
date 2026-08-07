<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Events;

/**
 * Published when a goods receipt is recorded (docs/specs/07-warehouse.md
 * §9). Dispatched synchronously by GoodsReceiptService::receive().
 */
final class GoodsReceived
{
    /** @var int */
    public $goodsReceiptId;

    /** @var int */
    public $warehouseId;

    /** @var int|null */
    public $purchaseOrderId;

    public function __construct(int $goodsReceiptId, int $warehouseId, ?int $purchaseOrderId)
    {
        $this->goodsReceiptId = $goodsReceiptId;
        $this->warehouseId = $warehouseId;
        $this->purchaseOrderId = $purchaseOrderId;
    }
}
