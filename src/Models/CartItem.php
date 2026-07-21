<?php
declare(strict_types=1);

namespace App\Models;

class CartItem
{
    /** @var int */
    public $productId;

    /** @var string */
    public $slug;

    /** @var string */
    public $name;

    /** @var string */
    public $sku;

    /** @var float */
    public $price;

    /** @var float|null */
    public $salePrice;

    /** @var int */
    public $quantity;

    /** @var int */
    public $stock;

    /** @var string|null */
    public $thumbnail;

    public function __construct(array $data)
    {
        $this->productId = (int) $data['product_id'];
        $this->slug = (string) $data['slug'];
        $this->name = (string) $data['name'];
        $this->sku = (string) $data['sku'];
        $this->price = (float) $data['price'];
        $this->salePrice = isset($data['sale_price']) && $data['sale_price'] !== null ? (float) $data['sale_price'] : null;
        $this->quantity = max(1, (int) $data['quantity']);
        $this->stock = (int) ($data['stock'] ?? 0);
        $this->thumbnail = $data['thumbnail'] ?? null;
    }

    public function getUnitPrice(): float
    {
        return $this->salePrice ?? $this->price;
    }

    public function getLineTotal(): float
    {
        return $this->getUnitPrice() * $this->quantity;
    }
}
