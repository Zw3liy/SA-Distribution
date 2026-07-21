<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Events;

/**
 * See ProductCreated for why this is not yet dispatched anywhere.
 */
final class ProductUpdated
{
    /** @var int */
    public $productId;

    /** @var array */
    public $changedFields;

    public function __construct(int $productId, array $changedFields)
    {
        $this->productId = $productId;
        $this->changedFields = $changedFields;
    }
}
