<?php
declare(strict_types=1);

namespace App\Domains\Orders\Events;

/**
 * Documents the public contract of "an order was placed"
 * (docs/specs/06-orders.md §9). Not dispatched through a real event bus
 * -- this platform has none (see Catalog's Event DTOs for the same
 * situation) -- kept as an immutable DTO for when Finance (invoice
 * generation), Warehouse (fulfillment), CRM, and Analytics need a real
 * subscription.
 */
final class OrderPlaced
{
    /** @var int */
    public $orderId;

    /** @var int */
    public $customerId;

    /** @var float */
    public $grandTotal;

    public function __construct(int $orderId, int $customerId, float $grandTotal)
    {
        $this->orderId = $orderId;
        $this->customerId = $customerId;
        $this->grandTotal = $grandTotal;
    }
}
