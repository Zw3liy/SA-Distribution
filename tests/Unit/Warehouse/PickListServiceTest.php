<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Services\OrderServiceInterface;
use App\Domains\Warehouse\Exceptions\PickListAlreadyCompleteException;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\PickListItem;
use App\Domains\Warehouse\Repositories\PickListRepositoryInterface;
use App\Domains\Warehouse\Services\PickListService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The fulfillment trigger (docs/specs/07-warehouse.md §2/§5/§8):
 * pick-list generation from an order's line items (aggregated per
 * product, idempotent per order), picking with reservation consumption
 * via InventoryServiceInterface, auto-completion when the last line is
 * picked, and packing gated on full picking.
 */
final class PickListServiceTest extends TestCase
{
    private function makeOrder(array $overrides = [], array $items = []): Order
    {
        $order = new Order(array_merge([
            'id' => 55,
            'order_number' => 'SDO-TEST-0001',
            'customer_id' => 10,
            'user_id' => 20,
            'status' => Order::STATUS_FULFILLING,
            'subtotal' => 200.0,
            'tax_total' => 30.0,
            'grand_total' => 230.0,
            'shipping_address_id' => 31,
            'billing_address_id' => 32,
            'placed_at' => '2026-08-07 10:00:00',
            'updated_at' => '2026-08-07 10:00:00',
        ], $overrides));

        if ($items !== []) {
            $order->items = $items;
        }

        return $order;
    }

    private function makeOrderItem(int $productId, int $quantity, ?int $reservationId = null): OrderItem
    {
        return new OrderItem([
            'id' => $productId,
            'order_id' => 55,
            'product_id' => $productId,
            'sku' => 'SKU-' . $productId,
            'name_snapshot' => 'Product ' . $productId,
            'quantity' => $quantity,
            'unit_price_snapshot' => 100.0,
            'line_total' => 100.0 * $quantity,
            'inventory_reservation_id' => $reservationId,
        ]);
    }

    private function makePickList(array $overrides = [], array $items = []): PickList
    {
        $pickList = new PickList(array_merge([
            'id' => 1,
            'order_id' => 55,
            'warehouse_id' => 1,
            'status' => PickList::STATUS_OPEN,
            'created_at' => '2026-08-07 10:00:00',
            'updated_at' => '2026-08-07 10:00:00',
        ], $overrides));

        if ($items !== []) {
            $pickList->items = $items;
        }

        return $pickList;
    }

    private function makePickListItem(int $id, ?string $pickedAt = null): PickListItem
    {
        return new PickListItem([
            'id' => $id,
            'pick_list_id' => 1,
            'product_id' => 100 + $id,
            'quantity' => 2,
            'picked_at' => $pickedAt,
            'created_at' => '2026-08-07 10:00:00',
        ]);
    }

    private function makeService(
        PickListRepositoryInterface $pickListRepository,
        ?OrderServiceInterface $orderService = null,
        ?WarehouseRepositoryInterface $warehouseRepository = null,
        ?InventoryServiceInterface $inventoryService = null
    ): PickListService {
        return new PickListService(
            $pickListRepository,
            $orderService ?? $this->createMock(OrderServiceInterface::class),
            $warehouseRepository ?? $this->createMock(WarehouseRepositoryInterface::class),
            $inventoryService ?? $this->createMock(InventoryServiceInterface::class)
        );
    }

    public function testGenerateForAggregatesOrderItemsIntoOneLinePerProduct(): void
    {
        $order = $this->makeOrder([], [
            $this->makeOrderItem(101, 2, 501),
            $this->makeOrderItem(101, 3, 502), // same product twice
            $this->makeOrderItem(102, 1, 503),
        ]);

        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('findById')->with(55)->willReturn($order);

        $warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouseRepository->method('findDefault')->willReturn(
            new Warehouse(['id' => 1, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => 1])
        );

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findByOrder')->with(55)->willReturn(null);
        $pickListRepository->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['order_id'] === 55
                        && $data['warehouse_id'] === 1
                        && $data['status'] === PickList::STATUS_OPEN;
                }),
                $this->callback(function (array $items): bool {
                    $byProduct = [];
                    foreach ($items as $item) {
                        $byProduct[$item['product_id']] = $item['quantity'];
                    }

                    return $byProduct === [101 => 5, 102 => 1];
                })
            )
            ->willReturn($this->makePickList());

        $service = $this->makeService($pickListRepository, $orderService, $warehouseRepository);
        $pickList = $service->generateFor(55);

        $this->assertSame(1, $pickList->id);
        $this->assertSame(55, $pickList->orderId);
    }

    public function testGenerateForIsIdempotentWhenPickListAlreadyExists(): void
    {
        $existing = $this->makePickList();

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findByOrder')->with(55)->willReturn($existing);
        $pickListRepository->expects($this->never())->method('create');

        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->expects($this->never())->method('findById');

        $service = $this->makeService($pickListRepository, $orderService);
        $pickList = $service->generateFor(55);

        $this->assertSame($existing, $pickList);
    }

    public function testGenerateForThrowsWhenOrderDoesNotExist(): void
    {
        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findByOrder')->willReturn(null);

        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('findById')->willReturn(null);

        $service = $this->makeService($pickListRepository, $orderService);

        $this->expectException(InvalidArgumentException::class);
        $service->generateFor(999);
    }

    public function testGenerateForThrowsWhenNoDefaultWarehouse(): void
    {
        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('findById')->willReturn($this->makeOrder());

        $warehouseRepository = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouseRepository->method('findDefault')->willReturn(null);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findByOrder')->willReturn(null);

        $service = $this->makeService($pickListRepository, $orderService, $warehouseRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active warehouse');

        $service->generateFor(55);
    }

    public function testMarkPickedConsumesReservationAndTransitionsToPicking(): void
    {
        $item = $this->makePickListItem(1);
        $pickList = $this->makePickList(['status' => PickList::STATUS_OPEN], [
            $this->makePickListItem(1),
            $this->makePickListItem(2),
        ]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findItemById')->with(1)->willReturn($item);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->once())->method('markItemPicked')->with(1);
        $pickListRepository->expects($this->once())->method('updateStatus')->with(1, PickList::STATUS_PICKING);

        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('findById')->with(55)->willReturn($this->makeOrder([], [
            $this->makeOrderItem(101, 2, 501),
        ]));

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->once())->method('consumeReservation')->with(501);

        $service = $this->makeService($pickListRepository, $orderService, null, $inventoryService);
        $service->markPicked(1, 7);

        $this->assertTrue(true);
    }

    public function testMarkPickedAutoCompletesWhenLastLinePicked(): void
    {
        $picked = $this->makePickListItem(1, '2026-08-07 10:05:00');
        $item = $this->makePickListItem(2);
        $pickList = $this->makePickList(['status' => PickList::STATUS_PICKING], [$picked, $item]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findItemById')->with(2)->willReturn($item);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->once())->method('markItemPicked')->with(2);
        $pickListRepository->expects($this->once())->method('updateStatus')->with(1, PickList::STATUS_PICKED);

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->once())->method('consumeReservation');

        $service = $this->makeService($pickListRepository, null, null, $inventoryService);
        $service->markPicked(2, 7);

        $this->assertTrue(true);
    }

    public function testMarkPickedOnAlreadyCompletePickListThrows(): void
    {
        $item = $this->makePickListItem(1);
        $pickList = $this->makePickList(['status' => PickList::STATUS_PICKED], [
            $this->makePickListItem(1, '2026-08-07 10:05:00'),
        ]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findItemById')->with(1)->willReturn($item);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->never())->method('markItemPicked');

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->never())->method('consumeReservation');

        $service = $this->makeService($pickListRepository, null, null, $inventoryService);

        $this->expectException(PickListAlreadyCompleteException::class);
        $service->markPicked(1, 7);
    }

    public function testMarkPickedOnAlreadyPickedItemIsNoOp(): void
    {
        $item = $this->makePickListItem(1, '2026-08-07 10:05:00');
        $pickList = $this->makePickList(['status' => PickList::STATUS_PICKING], [$item]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findItemById')->with(1)->willReturn($item);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->never())->method('markItemPicked');

        $service = $this->makeService($pickListRepository);

        $service->markPicked(1, 7);

        $this->assertTrue(true);
    }

    public function testMarkPickedSkipsNullReservationLines(): void
    {
        $item = $this->makePickListItem(1);
        $pickList = $this->makePickList(['status' => PickList::STATUS_OPEN], [$item]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findItemById')->with(1)->willReturn($item);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->method('markItemPicked');
        $pickListRepository->method('updateStatus');

        $orderService = $this->createMock(OrderServiceInterface::class);
        $orderService->method('findById')->willReturn($this->makeOrder([], [
            $this->makeOrderItem(101, 2, null), // legacy order line without a reservation
        ]));

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->never())->method('consumeReservation');

        $service = $this->makeService($pickListRepository, $orderService, null, $inventoryService);
        $service->markPicked(1, 7);

        $this->assertTrue(true);
    }

    public function testMarkPackedRequiresAllLinesPicked(): void
    {
        $pickList = $this->makePickList(['status' => PickList::STATUS_PICKING], [
            $this->makePickListItem(1, '2026-08-07 10:05:00'),
            $this->makePickListItem(2), // not picked yet
        ]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->never())->method('createPackingSlip');

        $service = $this->makeService($pickListRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->markPacked(1, 7);
    }

    public function testMarkPackedCreatesSlipAndTransitionsToPacked(): void
    {
        $pickList = $this->makePickList(['status' => PickList::STATUS_PICKED], [
            $this->makePickListItem(1, '2026-08-07 10:05:00'),
        ]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->expects($this->once())->method('createPackingSlip')->with(1, 7);
        $pickListRepository->expects($this->once())->method('updateStatus')->with(1, PickList::STATUS_PACKED);

        $service = $this->makeService($pickListRepository);
        $service->markPacked(1, 7);

        $this->assertTrue(true);
    }

    public function testMarkPackedOnAlreadyPackedListThrows(): void
    {
        $pickList = $this->makePickList(['status' => PickList::STATUS_PACKED], [
            $this->makePickListItem(1, '2026-08-07 10:05:00'),
        ]);

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);

        $service = $this->makeService($pickListRepository);

        $this->expectException(PickListAlreadyCompleteException::class);
        $service->markPacked(1, 7);
    }

    public function testQueryMethodsDelegateToRepository(): void
    {
        $pickList = $this->makePickList();

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(1)->willReturn($pickList);
        $pickListRepository->method('findByOrder')->with(55)->willReturn($pickList);
        $pickListRepository->method('listAllForAdmin')->with(25, 0)->willReturn([$pickList]);
        $pickListRepository->method('countAllForAdmin')->willReturn(3);

        $service = $this->makeService($pickListRepository);

        $this->assertSame($pickList, $service->findById(1));
        $this->assertSame($pickList, $service->findByOrder(55));
        $this->assertSame([$pickList], $service->listAllForAdmin(25, 0));
        $this->assertSame(3, $service->countAllForAdmin());
    }
}
