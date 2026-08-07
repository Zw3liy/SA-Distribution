<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Events;

/**
 * Documents the public contract of "a pick list was generated"
 * (docs/specs/07-warehouse.md §9). Dispatched synchronously by
 * PickListService::generateFor() after the pick list persists.
 */
final class PickListGenerated
{
    /** @var int */
    public $pickListId;

    /** @var int */
    public $orderId;

    public function __construct(int $pickListId, int $orderId)
    {
        $this->pickListId = $pickListId;
        $this->orderId = $orderId;
    }
}
