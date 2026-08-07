<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Warehouse\Events\StockTransferCompleted;
use App\Domains\Warehouse\Exceptions\TransferAlreadyCompletedException;
use App\Domains\Warehouse\Exceptions\TransferNotFoundException;
use App\Domains\Warehouse\Models\StockTransfer;
use App\Domains\Warehouse\Models\StockTransferItem;
use App\Domains\Warehouse\Repositories\StockTransferRepositoryInterface;
use App\Domains\Warehouse\Services\StockTransferService;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Inter-warehouse movement (docs/specs/07-warehouse.md §5/§14). The
 * critical invariant: complete() applies the atomic source/destination
 * pair -- both Inventory legs plus the status update inside one
 * transaction, so a failure partway leaves NOTHING applied.
 */
final class StockTransferServiceTest extends TestCase
{
    private function makeTransfer(string $status = StockTransfer::STATUS_IN_TRANSIT, array $items = []): StockTransfer
    {
        $transfer = new StockTransfer([
            'id' => 4,
            'from_warehouse_id' => 1,
            'to_warehouse_id' => 2,
            'status' => $status,
            'initiated_by_user_id' => 7,
            'created_at' => '2026-08-07 10:00:00',
            'completed_at' => null,
        ]);

        if ($items === []) {
            $transfer->items = [$this->makeTransferItem(1)];
        } else {
            $transfer->items = $items;
        }

        return $transfer;
    }

    private function makeTransferItem(int $productId, int $quantity = 5): StockTransferItem
    {
        return new StockTransferItem([
            'id' => $productId,
            'stock_transfer_id' => 4,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    private function makeService(
        StockTransferRepositoryInterface $transferRepository,
        ?InventoryServiceInterface $inventoryService = null,
        ?PDO $db = null,
        ?EventDispatcher $dispatcher = null
    ): StockTransferService {
        return new StockTransferService(
            $transferRepository,
            $inventoryService ?? $this->createMock(InventoryServiceInterface::class),
            $db ?? $this->createMock(PDO::class),
            $dispatcher
        );
    }

    public function testInitiateCreatesInTransitTransfer(): void
    {
        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->expects($this->once())
            ->method('create')
            ->with(
                $this->callback(function (array $data): bool {
                    return $data['from_warehouse_id'] === 1
                        && $data['to_warehouse_id'] === 2
                        && $data['status'] === StockTransfer::STATUS_IN_TRANSIT
                        && $data['initiated_by_user_id'] === 7;
                }),
                $this->callback(function (array $items): bool {
                    return $items === [['product_id' => 101, 'quantity' => 5]];
                })
            )
            ->willReturn($this->makeTransfer());

        $service = $this->makeService($transferRepository);
        $transfer = $service->initiate(1, 2, [['product_id' => 101, 'quantity' => 5]], 7);

        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->status);
    }

    public function testInitiateRejectsSameSourceAndDestination(): void
    {
        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->expects($this->never())->method('create');

        $service = $this->makeService($transferRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->initiate(1, 1, [['product_id' => 101, 'quantity' => 5]], 7);
    }

    public function testInitiateRejectsEmptyItemList(): void
    {
        $service = $this->makeService($this->createMock(StockTransferRepositoryInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $service->initiate(1, 2, [], 7);
    }

    public function testInitiateRejectsNonPositiveQuantity(): void
    {
        $service = $this->makeService($this->createMock(StockTransferRepositoryInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $service->initiate(1, 2, [['product_id' => 101, 'quantity' => 0]], 7);
    }

    /**
     * §14 happy path: both Inventory legs and the status update run
     * inside one transaction that commits, then StockTransferCompleted
     * is dispatched.
     */
    public function testCompleteAppliesBothLegsAtomicallyAndDispatches(): void
    {
        $transfer = $this->makeTransfer();

        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->method('findById')->with(4)->willReturn($transfer);
        $transferRepository->expects($this->once())->method('updateStatus')->with(4, StockTransfer::STATUS_COMPLETED);

        $calls = [];
        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->exactly(2))
            ->method('adjust')
            ->willReturnCallback(function (...$args) use (&$calls): void {
                $calls[] = $args;
            });

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $db->expects($this->never())->method('rollBack');

        $captured = null;
        $dispatcher = new EventDispatcher($this->createMock(\App\Logging\Logger::class));
        $dispatcher->subscribe(StockTransferCompleted::class, function (StockTransferCompleted $event) use (&$captured): void {
            $captured = $event;
        });

        $service = $this->makeService($transferRepository, $inventoryService, $db, $dispatcher);
        $service->complete(4);

        $this->assertSame([
            [101, 1, -5, 'stock_transfer_out', 7],
            [101, 2, 5, 'stock_transfer_in', 7],
        ], $calls, 'Source leg negative, destination leg positive, same actor.');
        $this->assertNotNull($captured);
        $this->assertSame(4, $captured->transferId);
        $this->assertSame(1, $captured->fromWarehouseId);
        $this->assertSame(2, $captured->toWarehouseId);
    }

    /**
     * §14 failure path: a failure on the destination leg must roll the
     * WHOLE transfer back -- the source leg's adjustment is undone by
     * the transaction, not left half-applied.
     */
    public function testCompleteRollsBackWhenDestinationLegFails(): void
    {
        $transfer = $this->makeTransfer();

        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->method('findById')->with(4)->willReturn($transfer);
        $transferRepository->expects($this->never())->method('updateStatus');

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->exactly(2))->method('adjust')->willReturnCallback(
            function (int $productId, int $warehouseId, int $delta, string $reason, int $actor): void {
                if ($delta > 0) {
                    throw new RuntimeException('Destination warehouse has no inventory item for product #' . $productId . '.');
                }
            }
        );

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('rollBack');
        $db->expects($this->never())->method('commit');

        $service = $this->makeService($transferRepository, $inventoryService, $db);

        $this->expectException(RuntimeException::class);
        $service->complete(4);
    }

    public function testCompleteThrowsWhenTransferAlreadyCompleted(): void
    {
        $transfer = $this->makeTransfer(StockTransfer::STATUS_COMPLETED);

        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->method('findById')->with(4)->willReturn($transfer);

        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('beginTransaction');

        $inventoryService = $this->createMock(InventoryServiceInterface::class);
        $inventoryService->expects($this->never())->method('adjust');

        $service = $this->makeService($transferRepository, $inventoryService, $db);

        $this->expectException(TransferAlreadyCompletedException::class);
        $service->complete(4);
    }

    public function testCompleteThrowsWhenTransferDoesNotExist(): void
    {
        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->method('findById')->willReturn(null);

        $service = $this->makeService($transferRepository);

        $this->expectException(TransferNotFoundException::class);
        $service->complete(999);
    }

    public function testQueryMethodsDelegateToRepository(): void
    {
        $transfer = $this->makeTransfer();

        $transferRepository = $this->createMock(StockTransferRepositoryInterface::class);
        $transferRepository->method('findById')->with(4)->willReturn($transfer);
        $transferRepository->method('listAllForAdmin')->with(20, 0)->willReturn([$transfer]);
        $transferRepository->method('countAllForAdmin')->willReturn(1);

        $service = $this->makeService($transferRepository);

        $this->assertSame($transfer, $service->findById(4));
        $this->assertSame([$transfer], $service->listAllForAdmin(20, 0));
        $this->assertSame(1, $service->countAllForAdmin());
    }
}
