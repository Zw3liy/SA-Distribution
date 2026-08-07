<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Domains\Warehouse\Events\OrderShipped;
use App\Domains\Warehouse\Exceptions\PickListNotFoundException;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\Shipment;
use App\Domains\Warehouse\Repositories\PickListRepositoryInterface;
use App\Domains\Warehouse\Repositories\ShipmentRepositoryInterface;
use App\Domains\Warehouse\Services\ShipmentService;
use App\Platform\Events\EventDispatcher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The outbound leg (docs/specs/07-warehouse.md §5/§2): a shipment may
 * only be created for a fully picked AND packed pick list; creating one
 * moves the pick list to 'shipped' and publishes OrderShipped -- the
 * event-driven handoff Orders consumes to transition the order
 * (§9/§10).
 */
final class ShipmentServiceTest extends TestCase
{
    private function makePickList(string $status = PickList::STATUS_PACKED): PickList
    {
        return new PickList([
            'id' => 9,
            'order_id' => 55,
            'warehouse_id' => 1,
            'status' => $status,
            'created_at' => '2026-08-07 10:00:00',
            'updated_at' => '2026-08-07 10:00:00',
        ]);
    }

    private function makeShipment(): Shipment
    {
        return new Shipment([
            'id' => 1,
            'order_id' => 55,
            'pick_list_id' => 9,
            'carrier' => 'The Courier Guy',
            'tracking_number' => 'TCG-123456',
            'created_by_user_id' => 7,
            'shipped_at' => '2026-08-07 12:00:00',
        ]);
    }

    private function makeService(
        ShipmentRepositoryInterface $shipmentRepository,
        PickListRepositoryInterface $pickListRepository,
        ?EventDispatcher $dispatcher = null
    ): ShipmentService {
        return new ShipmentService($shipmentRepository, $pickListRepository, $dispatcher);
    }

    public function testCreateForRequiresPackedPickList(): void
    {
        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(9)->willReturn($this->makePickList(PickList::STATUS_PICKED));

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->expects($this->never())->method('create');

        $service = $this->makeService($shipmentRepository, $pickListRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('picked and packed');

        $service->createFor(9, 'DHL', null, 7);
    }

    public function testCreateForThrowsWhenPickListDoesNotExist(): void
    {
        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->willReturn(null);

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->expects($this->never())->method('create');

        $service = $this->makeService($shipmentRepository, $pickListRepository);

        $this->expectException(PickListNotFoundException::class);
        $service->createFor(999, 'DHL', null, 7);
    }

    public function testCreateForRequiresACarrier(): void
    {
        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(9)->willReturn($this->makePickList());

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->expects($this->never())->method('create');

        $service = $this->makeService($shipmentRepository, $pickListRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carrier');

        $service->createFor(9, '   ', null, 7);
    }

    public function testCreateForHappyPathPersistsMovesPickListAndDispatchesOrderShipped(): void
    {
        $pickList = $this->makePickList();

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(9)->willReturn($pickList);
        $pickListRepository->expects($this->once())->method('updateStatus')->with(9, PickList::STATUS_SHIPPED);

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->method('findForPickList')->with(9)->willReturn(null);
        $shipmentRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['order_id'] === 55
                    && $data['pick_list_id'] === 9
                    && $data['carrier'] === 'The Courier Guy'
                    && $data['tracking_number'] === 'TCG-123456'
                    && $data['created_by_user_id'] === 7;
            }))
            ->willReturn($this->makeShipment());

        $captured = null;
        $dispatcher = new EventDispatcher($this->createMock(\App\Logging\Logger::class));
        $dispatcher->subscribe(OrderShipped::class, function (OrderShipped $event) use (&$captured): void {
            $captured = $event;
        });

        $service = $this->makeService($shipmentRepository, $pickListRepository, $dispatcher);
        $shipment = $service->createFor(9, 'The Courier Guy', 'TCG-123456', 7);

        $this->assertSame(1, $shipment->id);
        $this->assertNotNull($captured, 'OrderShipped must be dispatched.');
        $this->assertSame(55, $captured->orderId);
        $this->assertSame(9, $captured->pickListId);
        $this->assertSame('The Courier Guy', $captured->carrier);
        $this->assertSame('TCG-123456', $captured->trackingNumber);
        $this->assertSame(7, $captured->actorUserId);
    }

    public function testCreateForIsIdempotentForExistingShipment(): void
    {
        $existing = $this->makeShipment();

        $pickListRepository = $this->createMock(PickListRepositoryInterface::class);
        $pickListRepository->method('findById')->with(9)->willReturn($this->makePickList());

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->method('findForPickList')->with(9)->willReturn($existing);
        $shipmentRepository->expects($this->never())->method('create');

        $service = $this->makeService($shipmentRepository, $pickListRepository);
        $shipment = $service->createFor(9, 'DHL', null, 7);

        $this->assertSame($existing, $shipment);
    }

    public function testQueryMethodsDelegateToRepository(): void
    {
        $shipment = $this->makeShipment();

        $shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $shipmentRepository->method('findById')->with(1)->willReturn($shipment);
        $shipmentRepository->method('findByOrder')->with(55)->willReturn($shipment);
        $shipmentRepository->method('listAllForAdmin')->with(25, 0)->willReturn([$shipment]);
        $shipmentRepository->method('countAllForAdmin')->willReturn(1);

        $service = $this->makeService($shipmentRepository, $this->createMock(PickListRepositoryInterface::class));

        $this->assertSame($shipment, $service->findById(1));
        $this->assertSame($shipment, $service->findByOrder(55));
        $this->assertSame([$shipment], $service->listAllForAdmin(25, 0));
        $this->assertSame(1, $service->countAllForAdmin());
    }
}
