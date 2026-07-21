<?php
declare(strict_types=1);

namespace Tests\Unit\Inventory;

use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InventoryItemNotFoundException;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Repositories\InventoryItemRepositoryInterface;
use App\Domains\Inventory\Repositories\StockMovementRepositoryInterface;
use App\Domains\Inventory\Repositories\StockReservationRepositoryInterface;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryService;
use App\Logging\Logger;
use PHPUnit\Framework\TestCase;

final class InventoryServiceTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/inventory-service-test-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function makeItem(array $overrides = []): InventoryItem
    {
        return new InventoryItem(array_merge([
            'id' => 1,
            'product_id' => 10,
            'warehouse_id' => 1,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
            'reorder_threshold' => 5,
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides));
    }

    private function makeService(
        InventoryItemRepositoryInterface $itemRepository,
        ?StockReservationRepositoryInterface $reservationRepository = null,
        ?StockMovementRepositoryInterface $movementRepository = null,
        ?WarehouseRepositoryInterface $warehouseRepository = null,
        ?ProductRepositoryInterface $productRepository = null
    ): InventoryService {
        return new InventoryService(
            $itemRepository,
            $reservationRepository ?? $this->createMock(StockReservationRepositoryInterface::class),
            $movementRepository ?? $this->createMock(StockMovementRepositoryInterface::class),
            $warehouseRepository ?? $this->createMock(WarehouseRepositoryInterface::class),
            $productRepository ?? $this->createMock(ProductRepositoryInterface::class),
            new Logger($this->logFile)
        );
    }

    /**
     * docs/specs/05-inventory.md §18: sufficient-stock case.
     */
    public function testReserveSucceedsWhenStockIsSufficient(): void
    {
        $item = $this->makeItem(); // available = 80
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->with(10, 1)->willReturn($item);
        $itemRepo->expects($this->once())->method('tryReserve')->with(1, 50)->willReturn(true);

        $reservationRepo = $this->createMock(StockReservationRepositoryInterface::class);
        $reservationRepo->expects($this->once())->method('create')->willReturn(new StockReservation([
            'id' => 1, 'inventory_item_id' => 1, 'order_reference' => 'ORDER-1', 'quantity' => 50,
            'expires_at' => '2026-01-01 00:30:00', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00',
        ]));

        $service = $this->makeService($itemRepo, $reservationRepo);
        $reservation = $service->reserve(10, 1, 50, 'ORDER-1');

        $this->assertSame(50, $reservation->quantity);
    }

    /**
     * docs/specs/05-inventory.md §18: insufficient-stock case -- must
     * not silently clamp, must throw.
     */
    public function testReserveThrowsInsufficientStockWhenTryReserveFails(): void
    {
        $item = $this->makeItem(); // available = 80
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn($item);
        $itemRepo->method('tryReserve')->willReturn(false); // simulates the atomic conditional UPDATE matching 0 rows

        $reservationRepo = $this->createMock(StockReservationRepositoryInterface::class);
        $reservationRepo->expects($this->never())->method('create');

        $service = $this->makeService($itemRepo, $reservationRepo);

        $this->expectException(InsufficientStockException::class);
        $service->reserve(10, 1, 999, 'ORDER-2');
    }

    /**
     * docs/specs/05-inventory.md §18: exactly-at-boundary quantity --
     * requesting precisely quantity_available must succeed, not be
     * treated as "insufficient" off-by-one.
     */
    public function testReserveSucceedsAtExactAvailableBoundary(): void
    {
        $item = $this->makeItem(); // on_hand=100, reserved=20, available=80
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn($item);
        $itemRepo->expects($this->once())->method('tryReserve')->with(1, 80)->willReturn(true);

        $reservationRepo = $this->createMock(StockReservationRepositoryInterface::class);
        $reservationRepo->method('create')->willReturn(new StockReservation([
            'id' => 2, 'inventory_item_id' => 1, 'order_reference' => 'ORDER-3', 'quantity' => 80,
            'expires_at' => '2026-01-01 00:30:00', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00',
        ]));

        $service = $this->makeService($itemRepo, $reservationRepo);
        $reservation = $service->reserve(10, 1, 80, 'ORDER-3');

        $this->assertSame(80, $reservation->quantity);
    }

    public function testReserveThrowsInventoryItemNotFoundWhenNoItemExists(): void
    {
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn(null);

        $service = $this->makeService($itemRepo);

        $this->expectException(InventoryItemNotFoundException::class);
        $service->reserve(999, 1, 1, 'ORDER-4');
    }

    /**
     * docs/specs/05-inventory.md §8: negative-result rejection -- default
     * behavior must reject, not silently clamp to zero.
     */
    public function testAdjustThrowsInsufficientStockWhenResultWouldGoNegative(): void
    {
        $item = $this->makeItem();
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn($item);
        $itemRepo->method('tryAdjustOnHand')->with(1, -500, false)->willReturn(false);

        $movementRepo = $this->createMock(StockMovementRepositoryInterface::class);
        $movementRepo->expects($this->never())->method('record');

        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $productRepo->expects($this->never())->method('updateFields');

        $service = $this->makeService($itemRepo, null, $movementRepo, null, $productRepo);

        $this->expectException(InsufficientStockException::class);
        $service->adjust(10, 1, -500, 'Stock count correction', 7);
    }

    /**
     * docs/specs/05-inventory.md §8: the explicit allowNegative override
     * path -- a known reconciliation edge case (e.g. a prior over-sell)
     * that must succeed when the flag is passed.
     */
    public function testAdjustSucceedsWithAllowNegativeOverride(): void
    {
        $item = $this->makeItem();
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn($item);
        $itemRepo->expects($this->once())->method('tryAdjustOnHand')->with(1, -500, true)->willReturn(true);

        $movementRepo = $this->createMock(StockMovementRepositoryInterface::class);
        $movementRepo->expects($this->once())->method('record')->with($this->callback(function (array $data) {
            return $data['inventory_item_id'] === 1 && $data['delta'] === -500 && $data['reason'] === 'Over-sell reconciliation';
        }));

        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $itemRepo->method('sumAvailable')->willReturn(0);
        $productRepo->expects($this->once())->method('updateFields')->with(10, ['stock' => 0]);

        $service = $this->makeService($itemRepo, null, $movementRepo, null, $productRepo);
        $service->adjust(10, 1, -500, 'Over-sell reconciliation', 7, true);

        $this->assertTrue(true);
    }

    public function testAdjustWritesMovementAndSyncsStockMirrorOnSuccess(): void
    {
        $item = $this->makeItem();
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn($item);
        $itemRepo->method('tryAdjustOnHand')->willReturn(true);
        $itemRepo->method('sumAvailable')->with(10)->willReturn(130);

        $movementRepo = $this->createMock(StockMovementRepositoryInterface::class);
        $movementRepo->expects($this->once())->method('record');

        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $productRepo->expects($this->once())->method('updateFields')->with(10, ['stock' => 130]);

        $service = $this->makeService($itemRepo, null, $movementRepo, null, $productRepo);
        $service->adjust(10, 1, 50, 'Received PO #1234', 7);

        $this->assertTrue(true);
    }

    public function testAdjustThrowsInventoryItemNotFoundWhenNoItemExists(): void
    {
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->willReturn(null);

        $service = $this->makeService($itemRepo);

        $this->expectException(InventoryItemNotFoundException::class);
        $service->adjust(999, 1, 10, 'Test', 7);
    }

    public function testAvailableQuantityWithWarehouseReturnsSingleItemAvailability(): void
    {
        $item = $this->makeItem(); // available = 80
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('findFor')->with(10, 1)->willReturn($item);

        $service = $this->makeService($itemRepo);

        $this->assertSame(80, $service->availableQuantity(10, 1));
    }

    public function testAvailableQuantityWithoutWarehouseSumsAcrossLocations(): void
    {
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->method('sumAvailable')->with(10)->willReturn(230);

        $service = $this->makeService($itemRepo);

        $this->assertSame(230, $service->availableQuantity(10));
    }

    public function testInitializeForProductCreatesZeroQuantityItemInDefaultWarehouse(): void
    {
        $itemRepo = $this->createMock(InventoryItemRepositoryInterface::class);
        $itemRepo->expects($this->once())->method('upsertOnHand')->with(10, 1, 0)->willReturn($this->makeItem(['quantity_on_hand' => 0, 'quantity_reserved' => 0]));
        $itemRepo->method('sumAvailable')->willReturn(0);

        $warehouseRepo = $this->createMock(WarehouseRepositoryInterface::class);
        $warehouseRepo->method('findDefault')->willReturn(new Warehouse(['id' => 1, 'name' => 'Main Warehouse', 'code' => 'MAIN', 'is_active' => 1]));

        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $productRepo->expects($this->once())->method('updateFields')->with(10, ['stock' => 0]);

        $service = $this->makeService($itemRepo, null, null, $warehouseRepo, $productRepo);
        $service->initializeForProduct(10);

        $this->assertTrue(true);
    }
}
