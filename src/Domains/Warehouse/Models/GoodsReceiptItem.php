<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

class GoodsReceiptItem
{
    /** @var int */
    public $id;

    /** @var int */
    public $goodsReceiptId;

    /** @var int */
    public $productId;

    /** @var int */
    public $quantity;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->goodsReceiptId = (int) $data['goods_receipt_id'];
        $this->productId = (int) $data['product_id'];
        $this->quantity = (int) $data['quantity'];
    }
}
