<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Events;

/**
 * Published when a shipment is created (docs/specs/07-warehouse.md §9).
 * Consumed by Orders: transitions the order to 'shipped', completing
 * the event-driven loop back per §10 -- not a direct call into Orders.
 *
 * actorUserId is a documented extension of the spec's §9 shape: the
 * OrderStatusHistory audit trail (§15 of the Orders spec) must attribute
 * the transition to the staff member who created the shipment.
 */
final class OrderShipped
{
    /** @var int */
    public $orderId;

    /** @var int */
    public $pickListId;

    /** @var string */
    public $carrier;

    /** @var string|null */
    public $trackingNumber;

    /** @var int */
    public $actorUserId;

    public function __construct(int $orderId, int $pickListId, string $carrier, ?string $trackingNumber, int $actorUserId)
    {
        $this->orderId = $orderId;
        $this->pickListId = $pickListId;
        $this->carrier = $carrier;
        $this->trackingNumber = $trackingNumber;
        $this->actorUserId = $actorUserId;
    }
}
