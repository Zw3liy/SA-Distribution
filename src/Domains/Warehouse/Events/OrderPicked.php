<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Events;

/**
 * Published when the last line of a pick list is marked picked
 * (docs/specs/07-warehouse.md §9). Dispatched synchronously by
 * PickListService::markPicked().
 */
final class OrderPicked
{
    /** @var int */
    public $orderId;

    /** @var int */
    public $pickListId;

    public function __construct(int $orderId, int $pickListId)
    {
        $this->orderId = $orderId;
        $this->pickListId = $pickListId;
    }
}
