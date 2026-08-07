<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Services;

use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InventoryItemNotFoundException;
use App\Domains\Inventory\Exceptions\ReservationExpiredException;
use App\Domains\Inventory\Exceptions\ReservationNotFoundException;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Repositories\InventoryItemRepositoryInterface;
use App\Domains\Inventory\Repositories\StockMovementRepositoryInterface;
use App\Domains\Inventory\Repositories\StockReservationRepositoryInterface;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Logging\Logger;
use DateTimeImmutable;
use DateTimeInterface;

class InventoryService implements InventoryServiceInterface
{
    /**
     * Reservations expire 30 minutes after creation if checkout doesn't
     * complete (docs/specs/05-inventory.md §2) -- configurable window,
     * hardcoded here since no config surface for it exists yet and no
     * spec section calls for one; documented so it's easy to find and
     * promote to config/app.php later.
     */
    private const RESERVATION_WINDOW_MINUTES = 30;

    /** @var InventoryItemRepositoryInterface */
    private $itemRepository;

    /** @var StockReservationRepositoryInterface */
    private $reservationRepository;

    /** @var StockMovementRepositoryInterface */
    private $movementRepository;

    /** @var WarehouseRepositoryInterface */
    private $warehouseRepository;

    /**
     * Cross-domain dependency on Catalog's own repository interface, not
     * a new Inventory-owned abstraction -- the same precedent already
     * established by CartService depending directly on Catalog's
     * ProductRepositoryInterface (see src/Http/Kernel.php's binding
     * comment). Used solely to keep the products.stock read-only mirror
     * in sync (docs/specs/05-inventory.md §2's explicit compatibility
     * rule) whenever quantity_available changes for a product.
     *
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /** @var Logger */
    private $logger;

    public function __construct(
        InventoryItemRepositoryInterface $itemRepository,
        StockReservationRepositoryInterface $reservationRepository,
        StockMovementRepositoryInterface $movementRepository,
        WarehouseRepositoryInterface $warehouseRepository,
        ProductRepositoryInterface $productRepository,
        Logger $logger
    ) {
        $this->itemRepository = $itemRepository;
        $this->reservationRepository = $reservationRepository;
        $this->movementRepository = $movementRepository;
        $this->warehouseRepository = $warehouseRepository;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    public function availableQuantity(int $productId, ?int $warehouseId = null): int
    {
        if ($warehouseId === null) {
            return $this->itemRepository->sumAvailable($productId);
        }

        $item = $this->itemRepository->findFor($productId, $warehouseId);

        return $item === null ? 0 : $item->quantityAvailable();
    }

    /**
     * Concurrency safety (docs/specs/05-inventory.md §18) comes from
     * InventoryItemRepository::tryReserve()'s single conditional UPDATE,
     * not from any locking done here -- this method just interprets the
     * atomic result and never silently clamps the requested quantity.
     */
    public function reserve(int $productId, int $warehouseId, int $quantity, string $orderReference): StockReservation
    {
        $item = $this->itemRepository->findFor($productId, $warehouseId);
        if ($item === null) {
            throw new InventoryItemNotFoundException(sprintf('No inventory item for product #%d at warehouse #%d.', $productId, $warehouseId));
        }

        $reserved = $this->itemRepository->tryReserve($item->id, $quantity);
        if (!$reserved) {
            throw new InsufficientStockException(sprintf(
                'Requested quantity %d exceeds available stock for product #%d at warehouse #%d.',
                $quantity,
                $productId,
                $warehouseId
            ));
        }

        $expiresAt = (new DateTimeImmutable())->modify('+' . self::RESERVATION_WINDOW_MINUTES . ' minutes');

        return $this->reservationRepository->create([
            'inventory_item_id' => $item->id,
            'order_reference' => $orderReference,
            'quantity' => $quantity,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'status' => StockReservation::STATUS_ACTIVE,
        ]);
    }

    /**
     * Converts a reservation into a real, permanent deduction from
     * quantity_on_hand -- this is the one reservation-lifecycle
     * transition that also writes a StockMovement, since it's the point
     * where stock actually leaves (docs/specs/05-inventory.md §2).
     */
    public function consumeReservation(int $reservationId): void
    {
        $reservation = $this->getReservationOrFail($reservationId);

        // Idempotency guard (same guarantee releaseReservation already
        // provides): a reservation is consumed at most once. Two
        // legitimate callers exist by design -- Orders consumes on the
        // paid->fulfilling transition (docs/specs/06-orders.md §2) and
        // Warehouse's pick flow consumes on picking
        // (docs/specs/07-warehouse.md §2) -- and the event-driven wiring
        // means both may legitimately fire for the same reservation.
        // Without this guard the second call would deduct on-hand a
        // second time and write a duplicate StockMovement. Released
        // reservations (cancelled orders) are equally untouchable.
        if ($reservation->status !== StockReservation::STATUS_ACTIVE) {
            return;
        }

        $this->itemRepository->deductOnHand($reservation->inventoryItemId, $reservation->quantity);
        $this->movementRepository->record([
            'inventory_item_id' => $reservation->inventoryItemId,
            'delta' => -$reservation->quantity,
            'reason' => 'reservation_consumed',
            'reference_type' => 'order',
            'reference_id' => $reservation->orderReference,
            'actor_user_id' => null,
        ]);
        $this->reservationRepository->markConsumed($reservation->id);

        $this->syncStockMirror($this->productIdForItem($reservation->inventoryItemId));
    }

    public function releaseReservation(int $reservationId): void
    {
        $reservation = $this->getReservationOrFail($reservationId, $allowAlreadyExpiredCheck = false);

        if ($reservation->status !== StockReservation::STATUS_ACTIVE) {
            // Already consumed or released -- idempotent no-op rather
            // than an error, since a caller (e.g. an order-cancellation
            // path) racing the expiry sweep is a normal, expected
            // occurrence, not a bug to surface.
            return;
        }

        $this->itemRepository->releaseReserved($reservation->inventoryItemId, $reservation->quantity);
        $this->reservationRepository->markReleased($reservation->id);
    }

    /**
     * Rejects a delta that would take quantity_on_hand negative unless
     * $allowNegative is explicitly passed (docs/specs/05-inventory.md
     * §8) -- default is to reject, since silently allowing negative
     * stock hides real operational problems ("never write quick
     * fixes").
     */
    public function adjust(int $productId, int $warehouseId, int $delta, string $reason, int $actorUserId, bool $allowNegative = false): void
    {
        $item = $this->itemRepository->findFor($productId, $warehouseId);
        if ($item === null) {
            throw new InventoryItemNotFoundException(sprintf('No inventory item for product #%d at warehouse #%d.', $productId, $warehouseId));
        }

        $applied = $this->itemRepository->tryAdjustOnHand($item->id, $delta, $allowNegative);
        if (!$applied) {
            throw new InsufficientStockException(sprintf(
                'Adjustment of %d would take on-hand stock negative for product #%d at warehouse #%d (pass allowNegative to override).',
                $delta,
                $productId,
                $warehouseId
            ));
        }

        // The repository's on-hand mutation and this movement write are
        // never called independently of one another anywhere in this
        // codebase -- InventoryItemRepository is not exposed outside
        // this service, so every real quantity change is guaranteed to
        // produce a StockMovement (docs/specs/05-inventory.md §2), even
        // though the invariant is enforced here at the service layer
        // rather than by threading a "reason" parameter through the
        // repository's own method signature.
        $this->movementRepository->record([
            'inventory_item_id' => $item->id,
            'delta' => $delta,
            'reason' => $reason,
            'reference_type' => 'manual_adjustment',
            'reference_id' => null,
            'actor_user_id' => $actorUserId,
        ]);

        $this->syncStockMirror($productId);
    }

    public function initializeForProduct(int $productId): void
    {
        $warehouse = $this->warehouseRepository->findDefault();
        if ($warehouse === null) {
            $this->logger->warning('Cannot initialize inventory item: no active default warehouse exists.', ['product_id' => $productId]);

            return;
        }

        $this->itemRepository->upsertOnHand($productId, $warehouse->id, 0);
        $this->syncStockMirror($productId);
    }

    public function expireReservations(DateTimeInterface $cutoff): int
    {
        $count = $this->reservationRepository->expireOlderThan($cutoff);

        if ($count > 0) {
            // Logged once with a count, not per-reservation, to avoid
            // log spam from a scheduled sweep (docs/specs/05-inventory.md
            // §15).
            $this->logger->info('Reservation expiry sweep released expired reservations.', ['count' => $count, 'cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        }

        return $count;
    }

    private function getReservationOrFail(int $reservationId, bool $allowAlreadyExpiredCheck = true): StockReservation
    {
        $reservation = $this->reservationRepository->findById($reservationId);
        if ($reservation === null) {
            throw new ReservationNotFoundException(sprintf('Reservation #%d not found.', $reservationId));
        }

        if ($allowAlreadyExpiredCheck && $reservation->status === StockReservation::STATUS_ACTIVE && $reservation->isExpired()) {
            throw new ReservationExpiredException(sprintf('Reservation #%d has already expired.', $reservationId));
        }

        return $reservation;
    }

    private function syncStockMirror(int $productId): void
    {
        $available = $this->itemRepository->sumAvailable($productId);
        $this->productRepository->updateFields($productId, ['stock' => max(0, $available)]);
    }

    private function productIdForItem(int $inventoryItemId): int
    {
        $item = $this->itemRepository->findById($inventoryItemId);

        return $item === null ? 0 : $item->productId;
    }
}
