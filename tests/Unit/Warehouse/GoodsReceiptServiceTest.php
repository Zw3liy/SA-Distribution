<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Warehouse\Events\GoodsReceived;
use App\Domains\Warehouse\Models\GoodsReceipt;
use App\Domains\Warehouse\Repositories\GoodsReceiptRepositoryInterface;
use App\Domains\Warehouse\Services\GoodsReceiptService;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Inbound stock (docs/specs/07-warehouse.md §5/§8): every line goes
 * through InventoryServiceInterface::adjust() with reason
 * 'goods_receipt' (the audit-trailed path), quantities must be positive,
 * and the receipt row + lines persist as the business record.
 */
final class GoodsReceiptServiceTest extends TestCase
{
    private function makeReceipt(): GoodsReceipt
    {
        return new GoodsReceipt([
            'id' => 3,
            'purchase_order_id' => null,
            'warehouse_id' => 1,
            'received_by_user_id' => 7,
            'reference' => null,
            'received_at' => '2026-08-07 12:00:00',
        ]);
    }

    private function makeService(
        GoodsReceiptRepositoryInterface $receiptRepository,
        ?InventoryServiceInterface $inventoryService = null,
        ?EventDispatcher $dispatcher = null
    ): GoodsReceiptService {
        return new GoodsReceiptService(
            $receiptRepository,
            $inventoryService ?? $this->createMock(InventoryServiceInterface::class),
            $dispatcher
        );
    }

    public function testReceiveHappyPathAdjustsStockAndPersistsReceipt(): void
    {
        $calls = [];
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->exactly(2))
            ->method('adjust')
            ->willReturnCallback(function (...$args) use (&$calls): void {
                $calls[] = $args;
            });

        $receiptRepository = $this->createMock(GoodsReceiptRepositoryInterface::class);
        $receiptRepository->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['warehouse_id'] === 1
                        && $data['received_by_user_id'] === 7
                        && $data['purchase_order_id'] === null;
                }),
                $this->callback(function (array $items): bool {
                    return $items === [
                        ['product_id' => 101, 'quantity' => 10],
                        ['product_id' => 102, 'quantity' => 5],
                    ];
                })
            )
            ->willReturn($this->makeReceipt());

        $service = $this->makeService($receiptRepository, $inventoryService);
        $receipt = $service->receive(null, 1, [
            ['product_id' => 101, 'quantity' => 10],
            ['product_id' => 102, 'quantity' => 5],
        ], 7);

        $this->assertSame(3, $receipt->id);
        $this->assertSame([
            [101, 1, 10, 'goods_receipt', 7],
            [102, 1, 5, 'goods_receipt', 7],
        ], $calls, 'Every line must hit Inventory adjust with reason goods_receipt.');
    }

    public function testReceiveRejectsEmptyItemList(): void
    {
        $receiptRepository = $this->createMock(GoodsReceiptRepositoryInterface::class);
        $receiptRepository->expects($this->never())->method('create');

        $service = $this->makeService($receiptRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->receive(null, 1, [], 7);
    }

    public function testReceiveRejectsZeroQuantity(): void
    {
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->never())->method('adjust');

        $receiptRepository = $this->createMock(GoodsReceiptRepositoryInterface::class);
        $receiptRepository->expects($this->never())->method('create');

        $service = $this->makeService($receiptRepository, $inventoryService);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $service->receive(null, 1, [['product_id' => 101, 'quantity' => 0]], 7);
    }

    public function testReceiveRejectsNegativeQuantity(): void
    {
        $service = $this->makeService($this->createMock(GoodsReceiptRepositoryInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $service->receive(null, 1, [['product_id' => 101, 'quantity' => -3]], 7);
    }

    public function testReceiveDispatchesGoodsReceived(): void
    {
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->method('adjust');

        $receiptRepository = $this->createMock(GoodsReceiptRepositoryInterface::class);
        $receiptRepository->method('create')->willReturn($this->makeReceipt());

        $captured = null;
        $dispatcher = new EventDispatcher($this->createMock(\App\Logging\Logger::class));
        $dispatcher->subscribe(GoodsReceived::class, function (GoodsReceived $event) use (&$captured): void {
            $captured = $event;
        });

        $service = $this->makeService($receiptRepository, $inventoryService, $dispatcher);
        $service->receive(88, 1, [['product_id' => 101, 'quantity' => 2]], 7);

        $this->assertNotNull($captured);
        $this->assertSame(3, $captured->goodsReceiptId);
        $this->assertSame(1, $captured->warehouseId);
        $this->assertSame(88, $captured->purchaseOrderId);
    }

    public function testQueryMethodsDelegateToRepository(): void
    {
        $receipt = $this->makeReceipt();

        $receiptRepository = $this->createMock(GoodsReceiptRepositoryInterface::class);
        $receiptRepository->method('findById')->with(3)->willReturn($receipt);
        $receiptRepository->method('listAllForAdmin')->with(10, 0)->willReturn([$receipt]);
        $receiptRepository->method('countAllForAdmin')->willReturn(1);

        $service = $this->makeService($receiptRepository);

        $this->assertSame($receipt, $service->findById(3));
        $this->assertSame([$receipt], $service->listAllForAdmin(10, 0));
        $this->assertSame(1, $service->countAllForAdmin());
    }
}
