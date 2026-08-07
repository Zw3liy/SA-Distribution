<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

class StockTransferItem
{
    /** @var int */
    public $id;

    /** @var int */
    public $stockTransferId;

    /** @var int */
    public $productId;

    /** @var int */
    public $quantity;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->stockTransferId = (int) $data['stock_transfer_id'];
        $this->productId = (int) $data['product_id'];
        $this->quantity = (int) $data['quantity'];
    }
}
