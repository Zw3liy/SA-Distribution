<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Models;

class PickListItem
{
    /** @var int */
    public $id;

    /** @var int */
    public $pickListId;

    /** @var int */
    public $productId;

    /** @var int */
    public $quantity;

    /** @var string|null */
    public $pickedAt;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->pickListId = (int) $data['pick_list_id'];
        $this->productId = (int) $data['product_id'];
        $this->quantity = (int) $data['quantity'];
        $this->pickedAt = $data['picked_at'] ?? null;
        $this->createdAt = (string) $data['created_at'];
    }
}
