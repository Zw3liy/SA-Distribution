<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Domains\Warehouse\Events\GoodsReceived;
use App\Domains\Warehouse\Events\OrderPicked;
use App\Domains\Warehouse\Events\OrderShipped;
use App\Domains\Warehouse\Events\PickListGenerated;
use App\Domains\Warehouse\Events\StockTransferCompleted;
use PHPUnit\Framework\TestCase;

/**
 * The Warehouse event DTOs are the subscription contracts for the
 * synchronous in-process bus (docs/specs/07-warehouse.md §9) -- the
 * same contract-pinning treatment the Orders event DTOs receive.
 */
final class WarehouseEventsTest extends TestCase
{
    public function testPickListGeneratedCarriesPickListAndOrder(): void
    {
        $event = new PickListGenerated(9, 55);

        $this->assertSame(9, $event->pickListId);
        $this->assertSame(55, $event->orderId);
    }

    public function testOrderPickedCarriesOrderAndPickList(): void
    {
        $event = new OrderPicked(55, 9);

        $this->assertSame(55, $event->orderId);
        $this->assertSame(9, $event->pickListId);
    }

    public function testOrderShippedCarriesTheFullHandoffPayload(): void
    {
        $event = new OrderShipped(55, 9, 'The Courier Guy', 'TCG-123456', 7);

        $this->assertSame(55, $event->orderId);
        $this->assertSame(9, $event->pickListId);
        $this->assertSame('The Courier Guy', $event->carrier);
        $this->assertSame('TCG-123456', $event->trackingNumber);
        $this->assertSame(7, $event->actorUserId);
    }

    public function testGoodsReceivedCarriesReceiptWarehouseAndPurchaseOrder(): void
    {
        $event = new GoodsReceived(3, 1, 88);

        $this->assertSame(3, $event->goodsReceiptId);
        $this->assertSame(1, $event->warehouseId);
        $this->assertSame(88, $event->purchaseOrderId);
    }

    public function testStockTransferCompletedCarriesBothWarehouses(): void
    {
        $event = new StockTransferCompleted(4, 1, 2);

        $this->assertSame(4, $event->transferId);
        $this->assertSame(1, $event->fromWarehouseId);
        $this->assertSame(2, $event->toWarehouseId);
    }
}
