<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderStatusChanged;
use PHPUnit\Framework\TestCase;

/**
 * The Orders domain events are immutable DTOs documenting the public
 * contract of the domain (docs/specs/06-orders.md §9). They are not yet
 * dispatched -- the platform has no event bus (see Catalog's Event DTOs
 * for the same situation) -- but their shape is the contract Warehouse,
 * Finance, CRM and Analytics will subscribe to, so it is pinned down by
 * tests like any other public API.
 */
final class OrderEventsTest extends TestCase
{
    public function testOrderPlacedCarriesOrderCustomerAndGrandTotal(): void
    {
        $event = new OrderPlaced(55, 10, 230.0);

        $this->assertSame(55, $event->orderId);
        $this->assertSame(10, $event->customerId);
        $this->assertSame(230.0, $event->grandTotal);
    }

    public function testOrderStatusChangedCarriesTheFullTransition(): void
    {
        $event = new OrderStatusChanged(55, 'pending_payment', 'paid');

        $this->assertSame(55, $event->orderId);
        $this->assertSame('pending_payment', $event->fromStatus);
        $this->assertSame('paid', $event->toStatus);
    }

    public function testOrderCancelledCarriesOrderIdAndReason(): void
    {
        $event = new OrderCancelled(55, 'Customer request');

        $this->assertSame(55, $event->orderId);
        $this->assertSame('Customer request', $event->reason);
    }
}
