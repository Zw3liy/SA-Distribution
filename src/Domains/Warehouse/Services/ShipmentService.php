<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Services;

use App\Domains\Warehouse\Events\OrderShipped;
use App\Domains\Warehouse\Exceptions\PickListNotFoundException;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\Shipment;
use App\Domains\Warehouse\Repositories\PickListRepositoryInterface;
use App\Domains\Warehouse\Repositories\ShipmentRepositoryInterface;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * The outbound leg (docs/specs/07-warehouse.md §5): a shipment may only
 * be created once every line on its pick list is picked AND packed
 * (§2). Publishing OrderShipped is the event-driven handoff back to
 * Orders (spec §10) -- the subscriber in the Kernel transitions the
 * order to 'shipped'; this service never calls into Orders directly.
 */
class ShipmentService implements ShipmentServiceInterface
{
    /** @var ShipmentRepositoryInterface */
    private $shipmentRepository;

    /** @var PickListRepositoryInterface */
    private $pickListRepository;

    /** @var EventDispatcher|null */
    private $eventDispatcher;

    public function __construct(
        ShipmentRepositoryInterface $shipmentRepository,
        PickListRepositoryInterface $pickListRepository,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->pickListRepository = $pickListRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function createFor(int $pickListId, string $carrier, ?string $trackingNumber, int $actorUserId): Shipment
    {
        $pickList = $this->pickListRepository->findById($pickListId);
        if ($pickList === null) {
            throw new PickListNotFoundException(sprintf('Pick list #%d not found; cannot create a shipment.', $pickListId));
        }

        if (trim($carrier) === '') {
            throw new InvalidArgumentException('A carrier is required to create a shipment.');
        }

        if ($pickList->status !== PickList::STATUS_PACKED) {
            throw new InvalidArgumentException(sprintf(
                'Cannot ship pick list #%d: it must be fully picked and packed first (current status: "%s").',
                $pickList->id,
                $pickList->status
            ));
        }

        // Idempotent: one shipment per pick list (unique key enforces it
        // at the DB level too).
        $existing = $this->shipmentRepository->findForPickList($pickListId);
        if ($existing !== null) {
            return $existing;
        }

        $shipment = $this->shipmentRepository->create([
            'order_id' => $pickList->orderId,
            'pick_list_id' => $pickListId,
            'carrier' => trim($carrier),
            'tracking_number' => $trackingNumber !== null ? trim($trackingNumber) : null,
            'created_by_user_id' => $actorUserId,
        ]);

        $this->pickListRepository->updateStatus($pickListId, PickList::STATUS_SHIPPED);

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new OrderShipped(
                $pickList->orderId,
                $pickListId,
                $shipment->carrier,
                $shipment->trackingNumber,
                $actorUserId
            ));
        }

        return $shipment;
    }

    public function findById(int $id): ?Shipment
    {
        return $this->shipmentRepository->findById($id);
    }

    public function findByOrder(int $orderId): ?Shipment
    {
        return $this->shipmentRepository->findByOrder($orderId);
    }

    public function listAllForAdmin(int $limit, int $offset): array
    {
        return $this->shipmentRepository->listAllForAdmin($limit, $offset);
    }

    public function countAllForAdmin(): int
    {
        return $this->shipmentRepository->countAllForAdmin();
    }
}
