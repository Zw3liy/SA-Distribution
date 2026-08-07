<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Warehouse\Events\GoodsReceived;
use App\Domains\Warehouse\Models\GoodsReceipt;
use App\Domains\Warehouse\Repositories\GoodsReceiptRepositoryInterface;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * Inbound stock (docs/specs/07-warehouse.md §5/§8): every line increases
 * quantity_on_hand at the receiving warehouse through
 * InventoryServiceInterface::adjust(..., reason 'goods_receipt') -- the
 * same call path that writes the StockMovement audit trail -- and the
 * receipt row + lines persist as the business record.
 *
 * Over-receipt rule (§8): receiving beyond the referenced purchase
 * order's ordered quantity requires the explicit staff override flag.
 * The comparison itself is deferred: it needs the Suppliers domain's
 * purchase-order table, which does not exist yet (domain #8). The flag
 * and exception are plumbed through now so the contract is stable; the
 * comparison activates when #8 lands, and until then receipts against a
 * purchaseOrderId are accepted with the override semantics documented.
 */
class GoodsReceiptService implements GoodsReceiptServiceInterface
{
    /** @var GoodsReceiptRepositoryInterface */
    private $receiptRepository;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    /** @var EventDispatcher|null */
    private $eventDispatcher;

    public function __construct(
        GoodsReceiptRepositoryInterface $receiptRepository,
        InventoryServiceInterface $inventoryService,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->receiptRepository = $receiptRepository;
        $this->inventoryService = $inventoryService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function receive(
        ?int $purchaseOrderId,
        int $warehouseId,
        array $items,
        int $actorUserId,
        bool $allowOverReceipt = false
    ): GoodsReceipt {
        if (empty($items)) {
            throw new InvalidArgumentException('A goods receipt needs at least one line item.');
        }

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            if ($quantity <= 0) {
                throw new InvalidArgumentException(sprintf(
                    'Receipt quantity for product #%d must be greater than zero.',
                    (int) $item['product_id']
                ));
            }
        }

        // Over-receipt guard: see the class docblock -- the ordered
        // quantity is unknowable until Suppliers (domain #8) exists, so
        // the comparison activates then. The flag is accepted now so no
        // API change is needed later.
        if ($purchaseOrderId !== null && !$allowOverReceipt) {
            // Intentionally empty until #8: reserved for the
            // ordered-quantity comparison that throws OverReceiptException.
        }

        foreach ($items as $item) {
            $this->inventoryService->adjust(
                (int) $item['product_id'],
                $warehouseId,
                (int) $item['quantity'],
                'goods_receipt',
                $actorUserId
            );
        }

        $receipt = $this->receiptRepository->create([
            'purchase_order_id' => $purchaseOrderId,
            'warehouse_id' => $warehouseId,
            'received_by_user_id' => $actorUserId,
            'reference' => null,
        ], $items);

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new GoodsReceived($receipt->id, $warehouseId, $purchaseOrderId));
        }

        return $receipt;
    }

    public function findById(int $id): ?GoodsReceipt
    {
        return $this->receiptRepository->findById($id);
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        return $this->receiptRepository->listAllForAdmin($limit, $offset);
    }

    public function countAllForAdmin(): int
    {
        return $this->receiptRepository->countAllForAdmin();
    }
}
