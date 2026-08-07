<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Warehouse\Events\StockTransferCompleted;
use App\Domains\Warehouse\Exceptions\TransferAlreadyCompletedException;
use App\Domains\Warehouse\Exceptions\TransferNotFoundException;
use App\Domains\Warehouse\Models\StockTransfer;
use App\Domains\Warehouse\Repositories\StockTransferRepositoryInterface;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Inter-warehouse movement (docs/specs/07-warehouse.md §5/§14). The
 * correctness-critical operation is complete(): the negative source
 * adjustment, the positive destination adjustment, and the status
 * update must succeed or fail together -- "an atomic pair, never one
 * without the other" (§2).
 *
 * The PDO dependency is the one deliberate exception to the
 * service-layer-never-touches-PDO convention, mirroring the documented
 * precedent of CheckoutService depending on Inventory's
 * WarehouseRepositoryInterface: no service-level method exists for
 * "open a transaction spanning two domains' repositories", and every
 * repository in this codebase shares the single PDO instance bound in
 * the Kernel, so a transaction begun here genuinely covers
 * InventoryItemRepository's writes. This is exactly the mechanism §14
 * calls out, and it is integration-tested against a real database.
 */
class StockTransferService implements StockTransferServiceInterface
{
    /** @var StockTransferRepositoryInterface */
    private $transferRepository;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    /** @var PDO */
    private $db;

    /** @var EventDispatcher|null */
    private $eventDispatcher;

    public function __construct(
        StockTransferRepositoryInterface $transferRepository,
        InventoryServiceInterface $inventoryService,
        PDO $db,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->transferRepository = $transferRepository;
        $this->inventoryService = $inventoryService;
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function initiate(int $fromWarehouseId, int $toWarehouseId, array $items, int $actorUserId): StockTransfer
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must be different.');
        }

        if (empty($items)) {
            throw new InvalidArgumentException('A stock transfer needs at least one line item.');
        }

        foreach ($items as $item) {
            if ((int) $item['quantity'] <= 0) {
                throw new InvalidArgumentException(sprintf(
                    'Transfer quantity for product #%d must be greater than zero.',
                    (int) $item['product_id']
                ));
            }
        }

        return $this->transferRepository->create([
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'status' => StockTransfer::STATUS_IN_TRANSIT,
            'initiated_by_user_id' => $actorUserId,
        ], $items);
    }

    public function complete(int $transferId): void
    {
        $transfer = $this->transferRepository->findById($transferId);
        if ($transfer === null) {
            throw new TransferNotFoundException(sprintf('Stock transfer #%d not found.', $transferId));
        }

        if ($transfer->status !== StockTransfer::STATUS_IN_TRANSIT) {
            throw new TransferAlreadyCompletedException(sprintf(
                'Stock transfer #%d is already "%s"; it cannot be completed again.',
                $transferId,
                $transfer->status
            ));
        }

        $this->db->beginTransaction();

        try {
            foreach ($transfer->items as $item) {
                // Source: negative leg. allowNegative=false -- you cannot
                // transfer stock you do not physically have; if another
                // operation consumed it first, this throws
                // InsufficientStockException and the whole transfer
                // rolls back.
                $this->inventoryService->adjust(
                    $item->productId,
                    $transfer->fromWarehouseId,
                    -$item->quantity,
                    'stock_transfer_out',
                    $transfer->initiatedByUserId
                );

                // Destination: positive leg.
                $this->inventoryService->adjust(
                    $item->productId,
                    $transfer->toWarehouseId,
                    $item->quantity,
                    'stock_transfer_in',
                    $transfer->initiatedByUserId
                );
            }

            $this->transferRepository->updateStatus($transferId, StockTransfer::STATUS_COMPLETED);

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new StockTransferCompleted(
                $transferId,
                $transfer->fromWarehouseId,
                $transfer->toWarehouseId
            ));
        }
    }

    public function findById(int $id): ?StockTransfer
    {
        return $this->transferRepository->findById($id);
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        return $this->transferRepository->listAllForAdmin($limit, $offset);
    }

    public function countAllForAdmin(): int
    {
        return $this->transferRepository->countAllForAdmin();
    }
}
