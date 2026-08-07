<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Services\OrderServiceInterface;
use App\Domains\Warehouse\Events\OrderPicked;
use App\Domains\Warehouse\Events\PickListGenerated;
use App\Domains\Warehouse\Exceptions\PickListAlreadyCompleteException;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\PickListItem;
use App\Domains\Warehouse\Repositories\PickListRepositoryInterface;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;
use RuntimeException;

/**
 * The fulfillment trigger (docs/specs/07-warehouse.md §2/§5): generates
 * pick lists from orders, executes picking, and consumes the matching
 * Inventory reservations via InventoryServiceInterface -- never a direct
 * writer of inventory_items.
 */
class PickListService implements PickListServiceInterface
{
    /** @var PickListRepositoryInterface */
    private $pickListRepository;

    /** @var OrderServiceInterface */
    private $orderService;

    /** @var WarehouseRepositoryInterface */
    private $warehouseRepository;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    /** @var EventDispatcher|null */
    private $eventDispatcher;

    public function __construct(
        PickListRepositoryInterface $pickListRepository,
        OrderServiceInterface $orderService,
        WarehouseRepositoryInterface $warehouseRepository,
        InventoryServiceInterface $inventoryService,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->pickListRepository = $pickListRepository;
        $this->orderService = $orderService;
        $this->warehouseRepository = $warehouseRepository;
        $this->inventoryService = $inventoryService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function generateFor(int $orderId): PickList
    {
        // Idempotent: the event may fire at most once per order (a
        // transition to fulfilling is a one-way door), but re-running
        // must never create a second pick list -- the unique key on
        // pick_lists.order_id enforces the same rule at the DB level.
        $existing = $this->pickListRepository->findByOrder($orderId);
        if ($existing !== null) {
            return $existing;
        }

        $order = $this->orderService->findById($orderId);
        if ($order === null) {
            throw new InvalidArgumentException(sprintf('Order #%d not found; cannot generate a pick list.', $orderId));
        }

        $warehouse = $this->warehouseRepository->findDefault();
        if ($warehouse === null) {
            throw new RuntimeException('No active warehouse is configured; cannot generate a pick list.');
        }

        // One line per product, aggregating quantities (the cart merges
        // lines by product, but aggregation here is the contract, not an
        // assumption about the source data).
        $quantities = [];
        foreach ($order->items as $item) {
            $quantities[$item->productId] = ($quantities[$item->productId] ?? 0) + $item->quantity;
        }

        $items = [];
        foreach ($quantities as $productId => $quantity) {
            $items[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        $pickList = $this->pickListRepository->create([
            'order_id' => $orderId,
            'warehouse_id' => $warehouse->id,
            'status' => PickList::STATUS_OPEN,
        ], $items);

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new PickListGenerated($pickList->id, $orderId));
        }

        return $pickList;
    }

    public function markPicked(int $pickListItemId, int $actorUserId): void
    {
        $item = $this->pickListRepository->findItemById($pickListItemId);
        if ($item === null) {
            throw new InvalidArgumentException(sprintf('Pick list item #%d not found.', $pickListItemId));
        }

        $pickList = $this->pickListRepository->findById($item->pickListId);
        if ($pickList === null) {
            throw new RuntimeException('Pick list could not be loaded for item #' . $pickListItemId . '.');
        }

        if (in_array($pickList->status, [PickList::STATUS_PICKED, PickList::STATUS_PACKED, PickList::STATUS_SHIPPED, PickList::STATUS_CANCELLED], true)) {
            throw new PickListAlreadyCompleteException(sprintf(
                'Cannot pick item on pick list #%d: it is already "%s".',
                $pickList->id,
                $pickList->status
            ));
        }

        // Double-click / duplicate-submit protection: an already-picked
        // line is a no-op, not an error.
        if ($item->pickedAt !== null) {
            return;
        }

        $this->pickListRepository->markItemPicked($pickListItemId);

        // Consume the matching Inventory reservation (spec §2): Warehouse
        // calls the service interface; consumeReservation is idempotent
        // for already-consumed reservations (Inventory's guarantee).
        $order = $this->orderService->findById($pickList->orderId);
        if ($order !== null) {
            foreach ($order->items as $orderItem) {
                if ($orderItem->productId === $item->productId && $orderItem->inventoryReservationId !== null) {
                    $this->inventoryService->consumeReservation($orderItem->inventoryReservationId);
                }
            }
        }

        if ($this->allItemsPicked($pickList)) {
            $this->pickListRepository->updateStatus($pickList->id, PickList::STATUS_PICKED);
            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(new OrderPicked($pickList->orderId, $pickList->id));
            }
        } elseif ($pickList->status === PickList::STATUS_OPEN) {
            $this->pickListRepository->updateStatus($pickList->id, PickList::STATUS_PICKING);
        }
    }

    public function markPacked(int $pickListId, int $actorUserId): void
    {
        $pickList = $this->pickListRepository->findById($pickListId);
        if ($pickList === null) {
            throw new InvalidArgumentException(sprintf('Pick list #%d not found.', $pickListId));
        }

        if (in_array($pickList->status, [PickList::STATUS_PACKED, PickList::STATUS_SHIPPED, PickList::STATUS_CANCELLED], true)) {
            throw new PickListAlreadyCompleteException(sprintf(
                'Cannot pack pick list #%d: it is already "%s".',
                $pickList->id,
                $pickList->status
            ));
        }

        if (!$this->allItemsPicked($pickList)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot pack pick list #%d: not every line is picked yet (spec §2: a shipment requires picked AND packed).',
                $pickList->id
            ));
        }

        $this->pickListRepository->createPackingSlip($pickListId, $actorUserId);
        $this->pickListRepository->updateStatus($pickListId, PickList::STATUS_PACKED);
    }

    public function findById(int $id): ?PickList
    {
        return $this->pickListRepository->findById($id);
    }

    public function findByOrder(int $orderId): ?PickList
    {
        return $this->pickListRepository->findByOrder($orderId);
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        return $this->pickListRepository->listAllForAdmin($limit, $offset);
    }

    public function countAllForAdmin(): int
    {
        return $this->pickListRepository->countAllForAdmin();
    }

    private function allItemsPicked(PickList $pickList): bool
    {
        if (empty($pickList->items)) {
            return false;
        }

        foreach ($pickList->items as $item) {
            if ($item->pickedAt === null) {
                return false;
            }
        }

        return true;
    }
}
