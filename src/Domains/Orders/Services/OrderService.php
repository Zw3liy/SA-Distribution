<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domains\Orders\Exceptions\OrderNotFoundException;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Repositories\OrderRepositoryInterface;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepositoryInterface;

/**
 * Enforces the strict forward state machine (docs/specs/06-orders.md
 * §2): pending_payment -> paid -> fulfilling -> shipped -> delivered,
 * cancelled reachable only from pending_payment/paid, returned reachable
 * only from delivered. No transition skips a state or moves backward
 * outside those two explicit exceptions.
 */
class OrderService implements OrderServiceInterface
{
    /**
     * Keyed by from-status, valued by the set of legal to-statuses. Any
     * pair not present here throws InvalidOrderTransitionException.
     */
    private const ALLOWED_TRANSITIONS = [
        Order::STATUS_PENDING_PAYMENT => [Order::STATUS_PAID, Order::STATUS_CANCELLED],
        Order::STATUS_PAID => [Order::STATUS_FULFILLING, Order::STATUS_CANCELLED],
        Order::STATUS_FULFILLING => [Order::STATUS_SHIPPED],
        Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
        Order::STATUS_DELIVERED => [Order::STATUS_RETURNED],
        Order::STATUS_CANCELLED => [],
        Order::STATUS_RETURNED => [],
    ];

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var OrderStatusHistoryRepositoryInterface */
    private $historyRepository;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        InventoryServiceInterface $inventoryService
    ) {
        $this->orderRepository = $orderRepository;
        $this->historyRepository = $historyRepository;
        $this->inventoryService = $inventoryService;
    }

    public function findById(int $id): ?Order
    {
        return $this->orderRepository->findById($id);
    }

    public function transition(int $orderId, string $toStatus, ?int $actorUserId, ?string $note): void
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new OrderNotFoundException(sprintf('Order #%d not found.', $orderId));
        }

        $allowed = self::ALLOWED_TRANSITIONS[$order->status] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            throw new InvalidOrderTransitionException(sprintf(
                'Cannot transition order #%d from "%s" to "%s".',
                $orderId,
                $order->status,
                $toStatus
            ));
        }

        // Stock is reserved at placement and consumed (deducted for
        // real) only on the transition to fulfilling; cancellation from
        // pending_payment/paid releases the reservation instead
        // (docs/specs/06-orders.md §2). releaseReservation() is
        // idempotent for already-consumed/released reservations
        // (Inventory's own guarantee), so no extra state check is needed
        // here beyond the state-machine gate above.
        if ($toStatus === Order::STATUS_FULFILLING) {
            foreach ($order->items as $item) {
                if ($item->inventoryReservationId !== null) {
                    $this->inventoryService->consumeReservation($item->inventoryReservationId);
                }
            }
        } elseif ($toStatus === Order::STATUS_CANCELLED) {
            foreach ($order->items as $item) {
                if ($item->inventoryReservationId !== null) {
                    $this->inventoryService->releaseReservation($item->inventoryReservationId);
                }
            }
        }

        $this->orderRepository->updateStatus($orderId, $toStatus);
        $this->historyRepository->record($orderId, $order->status, $toStatus, $actorUserId, $note);
    }

    /**
     * Convenience wrapper matching the exact §5 interface signature (no
     * actor parameter) -- intended for system/customer-initiated
     * cancellation paths where there is no staff actor to attribute.
     * Staff-initiated cancellation should call transition() directly
     * with an actor id instead, so the audit trail (§15) properly
     * attributes it.
     */
    public function cancel(int $orderId, string $reason): void
    {
        $this->transition($orderId, Order::STATUS_CANCELLED, null, $reason);
    }

    public function myOrders(int $customerId, int $limit, int $offset): array
    {
        return $this->orderRepository->findByCustomer($customerId, $limit, $offset);
    }

    public function countMyOrders(int $customerId): int
    {
        return $this->orderRepository->countByCustomer($customerId);
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        return $this->orderRepository->findAllForAdmin($limit, $offset);
    }

    public function countAllForAdmin(): int
    {
        return $this->orderRepository->countAllForAdmin();
    }

    public function statusHistoryFor(int $orderId): array
    {
        return $this->historyRepository->historyFor($orderId);
    }
}
