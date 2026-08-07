<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domains\Orders\Exceptions\OrderNotFoundException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Repositories\OrderRepositoryInterface;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepositoryInterface;
use App\Domains\Orders\Services\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * OrderService enforces the strict forward state machine
 * (docs/specs/06-orders.md §2): pending_payment -> paid -> fulfilling ->
 * shipped -> delivered, cancelled reachable only from
 * pending_payment/paid, returned only from delivered. Every transition
 * writes an OrderStatusHistory row (§15) and drives the Inventory
 * reservation lifecycle (consume on fulfilling, release on cancelled).
 */
final class OrderServiceTest extends TestCase
{
    private function makeOrder(array $overrides = [], array $items = []): Order
    {
        $order = new Order(array_merge([
            'id' => 1,
            'order_number' => 'SDO-TEST-0001',
            'customer_id' => 10,
            'user_id' => 20,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'subtotal' => 100.0,
            'tax_total' => 15.0,
            'grand_total' => 115.0,
            'shipping_address_id' => 30,
            'billing_address_id' => 31,
            'placed_at' => '2026-07-21 10:00:00',
            'updated_at' => '2026-07-21 10:00:00',
        ], $overrides));

        if ($items === []) {
            $order->items = [$this->makeItem(1, 100)];
        } else {
            $order->items = $items;
        }

        return $order;
    }

    private function makeItem(int $id, ?int $reservationId): OrderItem
    {
        return new OrderItem([
            'id' => $id,
            'order_id' => 1,
            'product_id' => 100 + $id,
            'sku' => 'SKU-' . $id,
            'name_snapshot' => 'Test Product ' . $id,
            'quantity' => 1,
            'unit_price_snapshot' => 100.0,
            'line_total' => 100.0,
            'inventory_reservation_id' => $reservationId,
        ]);
    }

    private function makeService(
        OrderRepositoryInterface $orderRepository,
        ?OrderStatusHistoryRepositoryInterface $historyRepository = null,
        ?InventoryServiceInterface $inventoryService = null
    ): OrderService {
        return new OrderService(
            $orderRepository,
            $historyRepository ?? $this->createMock(OrderStatusHistoryRepositoryInterface::class),
            $inventoryService ?? $this->createMock(InventoryServiceInterface::class)
        );
    }

    public function testFindByIdDelegatesToRepository(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->expects($this->once())->method('findById')->with(7)->willReturn($order);

        $service = $this->makeService($repo);

        $this->assertSame($order, $service->findById(7));
    }

    public function testFindByIdReturnsNullForUnknownOrder(): void
    {
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $service = $this->makeService($repo);

        $this->assertNull($service->findById(999));
    }

    /**
     * docs/specs/06-orders.md §2: pending_payment -> paid is the first
     * legal step of the happy path; the status row and audit metadata
     * (actor, note) must be persisted.
     */
    public function testTransitionPendingPaymentToPaidPersistsStatusAndHistory(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->with(1)->willReturn($order);
        $repo->expects($this->once())->method('updateStatus')->with(1, Order::STATUS_PAID);

        $history = $this->createMock(OrderStatusHistoryRepositoryInterface::class);
        $history->expects($this->once())
            ->method('record')
            ->with(1, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, 42, 'Payment confirmed');

        $service = $this->makeService($repo, $history);
        $service->transition(1, Order::STATUS_PAID, 42, 'Payment confirmed');

        $this->assertTrue(true);
    }

    /**
     * docs/specs/06-orders.md §2: the full forward lifecycle, one
     * transition at a time -- no state may be skipped.
     */
    public function testFullForwardLifecycleIsAllowed(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);
        $repo->expects($this->exactly(4))->method('updateStatus');

        $service = $this->makeService($repo);

        $service->transition(1, Order::STATUS_PAID, null, null);
        $order->status = Order::STATUS_PAID;

        $service->transition(1, Order::STATUS_FULFILLING, null, null);
        $order->status = Order::STATUS_FULFILLING;

        $service->transition(1, Order::STATUS_SHIPPED, null, null);
        $order->status = Order::STATUS_SHIPPED;

        $service->transition(1, Order::STATUS_DELIVERED, null, null);

        $this->assertTrue(true);
    }

    /**
     * docs/specs/06-orders.md §2/§5: the transition to fulfilling is the
     * stock-consumption point -- every line item's reservation must be
     * consumed, not released.
     */
    public function testTransitionToFulfillingConsumesEveryReservation(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_PAID], [
            $this->makeItem(1, 501),
            $this->makeItem(2, 502),
            $this->makeItem(3, null), // line without a reservation
        ]);
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);

        $inventory = $this->createMock(InventoryServiceInterface::class);
        $inventory->expects($this->exactly(2))->method('consumeReservation')->with($this->logicalOr(501, 502));
        $inventory->expects($this->never())->method('releaseReservation');

        $service = $this->makeService($repo, null, $inventory);
        $service->transition(1, Order::STATUS_FULFILLING, null, null);

        $this->assertTrue(true);
    }

    /**
     * docs/specs/06-orders.md §2: cancellation from pending_payment/paid
     * releases the reservations instead of consuming them;
     * releaseReservation() is idempotent in Inventory, so no extra state
     * check is needed here.
     */
    public function testTransitionToCancelledReleasesEveryReservation(): void
    {
        $order = $this->makeOrder([], [
            $this->makeItem(1, 501),
            $this->makeItem(2, 502),
        ]);
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);

        $inventory = $this->createMock(InventoryServiceInterface::class);
        $inventory->expects($this->never())->method('consumeReservation');
        $inventory->expects($this->exactly(2))->method('releaseReservation');

        $service = $this->makeService($repo, null, $inventory);
        $service->transition(1, Order::STATUS_CANCELLED, 7, 'Customer request');

        $this->assertTrue(true);
    }

    public function testCancelWrapperDelegatesToTransitionWithReasonAndNoActor(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);
        $repo->expects($this->once())->method('updateStatus')->with(1, Order::STATUS_CANCELLED);

        $history = $this->createMock(OrderStatusHistoryRepositoryInterface::class);
        $history->expects($this->once())
            ->method('record')
            ->with(1, Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED, null, 'Buyer changed their mind');

        $inventory = $this->createMock(InventoryServiceInterface::class);
        $inventory->expects($this->once())->method('releaseReservation');

        $service = $this->makeService($repo, $history, $inventory);
        $service->cancel(1, 'Buyer changed their mind');

        $this->assertTrue(true);
    }

    public function testReturnedIsAllowedOnlyFromDelivered(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_DELIVERED]);
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);
        $repo->expects($this->once())->method('updateStatus')->with(1, Order::STATUS_RETURNED);

        $service = $this->makeService($repo);
        $service->transition(1, Order::STATUS_RETURNED, null, 'Damaged on arrival');

        $this->assertTrue(true);
    }

    /**
     * docs/specs/06-orders.md §2/§8: a skipped state is not legal
     * (pending_payment -> shipped), and must throw rather than silently
     * proceeding.
     */
    public function testTransitionSkippingStateThrows(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);
        $repo->expects($this->never())->method('updateStatus');

        $service = $this->makeService($repo);

        $this->expectException(InvalidOrderTransitionException::class);
        $service->transition(1, Order::STATUS_SHIPPED, null, null);
    }

    public function testBackwardTransitionThrows(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_DELIVERED]);
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);

        $service = $this->makeService($repo);

        $this->expectException(InvalidOrderTransitionException::class);
        $service->transition(1, Order::STATUS_SHIPPED, null, null);
    }

    /**
     * Terminal states (cancelled/returned) accept no further transitions
     * (docs/specs/06-orders.md §2's ALLOWED_TRANSITIONS map has empty
     * value sets for them).
     */
    public function testTerminalStatesAcceptNoFurtherTransitions(): void
    {
        foreach ([Order::STATUS_CANCELLED, Order::STATUS_RETURNED] as $terminal) {
            $order = $this->makeOrder(['status' => $terminal]);
            $repo = $this->createMock(OrderRepositoryInterface::class);
            $repo->method('findById')->willReturn($order);

            $service = $this->makeService($repo);

            try {
                $service->transition(1, Order::STATUS_PAID, null, null);
                $this->fail("Expected InvalidOrderTransitionException from {$terminal}");
            } catch (InvalidOrderTransitionException $exception) {
                $this->assertStringContainsString($terminal, $exception->getMessage());
            }
        }
    }

    public function testTransitionOnUnknownOrderThrowsOrderNotFound(): void
    {
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $service = $this->makeService($repo);

        $this->expectException(OrderNotFoundException::class);
        $service->transition(999, Order::STATUS_PAID, null, null);
    }

    public function testCancelOnUnknownOrderThrowsOrderNotFound(): void
    {
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $service = $this->makeService($repo);

        $this->expectException(OrderNotFoundException::class);
        $service->cancel(999, 'No such order');
    }

    /**
     * The invalid-transition exception message must carry the order id
     * and both statuses so staff can diagnose the failure from the admin
     * screen (docs/specs/06-orders.md §14).
     */
    public function testInvalidTransitionMessageIdentifiesOrderAndStatuses(): void
    {
        $order = $this->makeOrder();
        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findById')->willReturn($order);

        $service = $this->makeService($repo);

        try {
            $service->transition(1, Order::STATUS_DELIVERED, null, null);
            $this->fail('Expected InvalidOrderTransitionException');
        } catch (InvalidOrderTransitionException $exception) {
            $this->assertStringContainsString('#1', $exception->getMessage());
            $this->assertStringContainsString(Order::STATUS_PENDING_PAYMENT, $exception->getMessage());
            $this->assertStringContainsString(Order::STATUS_DELIVERED, $exception->getMessage());
        }
    }

    /**
     * §5 additions beyond the minimal spec interface: paginated customer
     * order history and the admin list, plus the status-history read.
     */
    public function testQueryMethodsDelegateToRepositories(): void
    {
        $orders = [$this->makeOrder()];
        $historyEntries = [['id' => 1]];

        $repo = $this->createMock(OrderRepositoryInterface::class);
        $repo->method('findByCustomer')->with(10, 5, 10)->willReturn($orders);
        $repo->method('countByCustomer')->with(10)->willReturn(3);
        $repo->method('findAllForAdmin')->with(25, 0)->willReturn($orders);
        $repo->method('countAllForAdmin')->willReturn(7);

        $history = $this->createMock(OrderStatusHistoryRepositoryInterface::class);
        $history->method('historyFor')->with(1)->willReturn($historyEntries);

        $service = $this->makeService($repo, $history);

        $this->assertSame($orders, $service->myOrders(10, 5, 10));
        $this->assertSame(3, $service->countMyOrders(10));
        $this->assertSame($orders, $service->listAllForAdmin(25, 0));
        $this->assertSame(7, $service->countAllForAdmin());
        $this->assertSame($historyEntries, $service->statusHistoryFor(1));
    }
}
