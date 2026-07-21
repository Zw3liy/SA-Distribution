<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Events;

/**
 * See ProductCreated for why this is not yet dispatched anywhere.
 */
final class ProductDeactivated
{
    /** @var int */
    public $productId;

    public function __construct(int $productId)
    {
        $this->productId = $productId;
    }
}
