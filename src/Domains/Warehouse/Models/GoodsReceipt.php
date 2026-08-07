<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

/**
 * Inbound stock record. purchaseOrderId is nullable: Suppliers (domain
 * #8) does not exist yet, so receipts may be taken standalone now and
 * linked to a purchase order later (docs/specs/07-warehouse.md §2/§3).
 */
class GoodsReceipt
{
    /** @var int */
    public $id;

    /** @var int|null */
    public $purchaseOrderId;

    /** @var int */
    public $warehouseId;

    /** @var int */
    public $receivedByUserId;

    /** @var string|null */
    public $reference;

    /** @var string */
    public $receivedAt;

    /** @var GoodsReceiptItem[] */
    public $items = [];

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->purchaseOrderId = isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;
        $this->warehouseId = (int) $data['warehouse_id'];
        $this->receivedByUserId = (int) $data['received_by_user_id'];
        $this->reference = $data['reference'] ?? null;
        $this->receivedAt = (string) $data['received_at'];
    }
}
