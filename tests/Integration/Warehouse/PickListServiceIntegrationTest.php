<?php

declare(strict_types=1);

namespace Tests\Integration\Warehouse;

use App\Domains\Inventory\Repositories\InventoryItemRepository;
use App\Domains\Inventory\Repositories\StockMovementRepository;
use App\Domains\Inventory\Repositories\StockReservationRepository;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Repositories\OrderRepository;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepository;
use App\Domains\Orders\Services\OrderService;
use App\Domains\Warehouse\Events\OrderShipped;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Repositories\PickListRepository;
use App\Domains\Warehouse\Repositories\ShipmentRepository;
use App\Domains\Warehouse\Services\PickListService;
use App\Domains\Warehouse\Services\ShipmentService;
use App\Logging\Logger;
use App\Platform\Events\EventDispatcher;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus all domain migrations (incl. 2026_08_07_warehouse_domain.sql).
 *
 * The end-to-end bidirectional event loop (docs/specs/07-warehouse.md
 * §10/§19): OrderStatusChanged(paid->fulfilling) generates the pick
 * list; picking consumes the reservation; packing gates on full
 * picking; OrderShipped completes the loop back into Orders by
 * transitioning the order to 'shipped'. This is the integration test of
 * the event dispatcher itself, not just the domain's logic.
 */
final class PickListServiceIntegrationTest extends TestCase
{
    private const ORDER_NUMBER = 'REPO-TEST-WH-FLOW-ORD-1';
    private const SKU = 'REPO-TEST-WH-FLOW-001';

    private PDO $db;
    private EventDispatcher $dispatcher;
    private OrderService $orderService;
    private PickListService $pickListService;
    private ShipmentService $shipmentService;
    private InventoryService $inventoryService;

    private string $logFile;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;
    private int $warehouseId = 0;
    private int $inventoryItemId = 0;
    private int $reservationId = 0;
    private int $userId = 0;
    private int $customerId = 0;
    private int $addressId = 0;
    private int $orderId = 0;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'sa_business';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $this->db = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $this->cleanup();
        $this->createFixtures();

        $this->logFile = sys_get_temp_dir() . '/warehouse-flow-test-' . uniqid() . '.log';

        // Real service graph, wired exactly as the Kernel wires it.
        $this->dispatcher = new EventDispatcher(new Logger($this->logFile));

        $this->inventoryService = new InventoryService(
            new InventoryItemRepository($this->db),
            new StockReservationRepository($this->db),
            new StockMovementRepository($this->db),
            new WarehouseRepository($this->db),
            $this->createMock(\App\Domains\Catalog\Repositories\ProductRepositoryInterface::class),
            new Logger($this->logFile)
        );

        $this->orderService = new OrderService(
            new OrderRepository($this->db),
            new OrderStatusHistoryRepository($this->db),
            $this->inventoryService,
            $this->dispatcher
        );

        $this->pickListService = new PickListService(
            new PickListRepository($this->db),
            $this->orderService,
            new WarehouseRepository($this->db),
            $this->inventoryService,
            $this->dispatcher
        );

        $this->shipmentService = new ShipmentService(
            new ShipmentRepository($this->db),
            new PickListRepository($this->db),
            $this->dispatcher
        );

        // Kernel-parity subscriptions (§10).
        $this->dispatcher->subscribe(OrderStatusChanged::class, function (OrderStatusChanged $event): void {
            if ($event->toStatus === Order::STATUS_FULFILLING) {
                $this->pickListService->generateFor($event->orderId);
            }
        });
        $this->dispatcher->subscribe(OrderShipped::class, function (OrderShipped $event): void {
            $order = $this->orderService->findById($event->orderId);
            if ($order !== null && $order->status === Order::STATUS_FULFILLING) {
                $this->orderService->transition($event->orderId, Order::STATUS_SHIPPED, $event->actorUserId, 'Shipment created (' . $event->carrier . ').');
            }
        });
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM shipments WHERE order_id IN (SELECT id FROM orders WHERE order_number = '" . self::ORDER_NUMBER . "')");
        $this->db->exec("DELETE FROM packing_slips WHERE pick_list_id IN (SELECT id FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number = '" . self::ORDER_NUMBER . "'))");
        $this->db->exec("DELETE FROM pick_list_items WHERE pick_list_id IN (SELECT id FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number = '" . self::ORDER_NUMBER . "'))");
        $this->db->exec("DELETE FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number = '" . self::ORDER_NUMBER . "')");
        $this->db->exec("DELETE FROM orders WHERE order_number = '" . self::ORDER_NUMBER . "'");
        $this->db->exec("DELETE FROM addresses WHERE label = 'Repo Test WH Flow Address'");
        $this->db->exec("DELETE FROM customers WHERE id = {$this->customerId}");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-wh-flow@example.com'");
        $this->db->exec("DELETE FROM stock_reservations WHERE order_reference = '" . self::ORDER_NUMBER . "'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku = '" . self::SKU . "')");
        $this->db->exec("DELETE FROM products WHERE sku = '" . self::SKU . "'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-wh-flow-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-wh-flow-brand'");

        $this->categoryId = $this->brandId = $this->productId = 0;
        $this->warehouseId = $this->inventoryItemId = $this->reservationId = 0;
        $this->userId = $this->customerId = $this->addressId = $this->orderId = 0;
    }

    private function createFixtures(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test WH Flow Category', 'repo-test-wh-flow-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test WH Flow Brand', 'repo-test-wh-flow-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('" . self::SKU . "', 'Repo Test WH Flow Widget', 'repo-test-wh-flow-widget', 'x', 'x', :category_id, :brand_id, 100, 10, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();
        $this->assertGreaterThan(0, $this->warehouseId);

        $this->db->exec(
            "INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold)
             VALUES ({$this->productId}, {$this->warehouseId}, 10, 0, 0)"
        );
        $this->inventoryItemId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO stock_reservations (inventory_item_id, order_reference, quantity, expires_at, status)
             VALUES ({$this->inventoryItemId}, '" . self::ORDER_NUMBER . "', 2, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 'active')"
        );
        $this->reservationId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-wh-flow@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO customers (account_type, company_name, created_at, updated_at) VALUES ('b2c', NULL, NOW(), NOW())");
        $this->customerId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES (:customer_id, 'Repo Test WH Flow Address', 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $stmt->execute(['customer_id' => $this->customerId]);
        $this->addressId = (int) $this->db->lastInsertId();

        $order = (new OrderRepository($this->db))->create([
            'order_number' => self::ORDER_NUMBER,
            'customer_id' => $this->customerId,
            'user_id' => $this->userId,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'subtotal' => 200.0,
            'tax_total' => 30.0,
            'grand_total' => 230.0,
            'shipping_address_id' => $this->addressId,
            'billing_address_id' => $this->addressId,
        ], [[
            'product_id' => $this->productId,
            'sku' => self::SKU,
            'name_snapshot' => 'Repo Test WH Flow Widget',
            'quantity' => 2,
            'unit_price_snapshot' => 100.0,
            'line_total' => 200.0,
            'inventory_reservation_id' => $this->reservationId,
        ]]);
        $this->orderId = $order->id;
    }

    private function onHand(): int
    {
        return (int) $this->db->query("SELECT quantity_on_hand FROM inventory_items WHERE id = {$this->inventoryItemId}")->fetchColumn();
    }

    private function reservationStatus(): string
    {
        return (string) $this->db->query("SELECT status FROM stock_reservations WHERE id = {$this->reservationId}")->fetchColumn();
    }

    private function movementCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = {$this->inventoryItemId}")->fetchColumn();
    }

    /**
     * The full loop: paid -> fulfilling (pick list generated, reservation
     * consumed by Orders) -> picked (no double deduction) -> packed ->
     * shipped (OrderShipped transitions the order back in Orders).
     */
    public function testFullFulfilmentLoopEndToEnd(): void
    {
        // 1. paid: no pick list yet (generation fires on fulfilling).
        $this->orderService->transition($this->orderId, Order::STATUS_PAID, $this->userId, 'Paid');
        $this->assertNull($this->pickListService->findByOrder($this->orderId));
        $this->assertSame(10, $this->onHand());
        $this->assertSame('active', $this->reservationStatus());

        // 2. fulfilling: pick list generated via the event subscriber.
        $this->orderService->transition($this->orderId, Order::STATUS_FULFILLING, $this->userId, 'Fulfilment starts');

        $pickList = $this->pickListService->findByOrder($this->orderId);
        $this->assertNotNull($pickList, 'The OrderStatusChanged subscriber must generate the pick list.');
        $this->assertSame($this->warehouseId, $pickList->warehouseId);
        $this->assertSame(PickList::STATUS_OPEN, $pickList->status);
        $this->assertCount(1, $pickList->items);
        $this->assertSame($this->productId, $pickList->items[0]->productId);
        $this->assertSame(2, $pickList->items[0]->quantity);

        // Orders consumed the reservation on the fulfilling transition.
        $this->assertSame(8, $this->onHand());
        $this->assertSame('consumed', $this->reservationStatus());
        $movementsAfterFulfilling = $this->movementCount();

        // 3. pick: consuming the same reservation again must be a no-op
        // (idempotency guard) -- no double deduction, no duplicate
        // movement; the pick list auto-completes to 'picked'.
        $this->pickListService->markPicked($pickList->items[0]->id, $this->userId);

        $this->assertSame(8, $this->onHand(), 'Picking must not deduct on-hand a second time.');
        $this->assertSame('consumed', $this->reservationStatus());
        $this->assertSame($movementsAfterFulfilling, $this->movementCount(), 'No duplicate StockMovement may be written.');
        $this->assertSame(PickList::STATUS_PICKED, $this->pickListService->findById($pickList->id)->status);

        // 4. pack: requires all lines picked.
        $this->pickListService->markPacked($pickList->id, $this->userId);
        $this->assertSame(PickList::STATUS_PACKED, $this->pickListService->findById($pickList->id)->status);

        // 5. ship: OrderShipped completes the loop -- the order itself
        // transitions to 'shipped' via the subscriber.
        $shipment = $this->shipmentService->createFor($pickList->id, 'The Courier Guy', 'TCG-FLOW-1', $this->userId);

        $this->assertSame($this->orderId, $shipment->orderId);
        $this->assertSame('The Courier Guy', $shipment->carrier);
        $this->assertSame(PickList::STATUS_SHIPPED, $this->pickListService->findById($pickList->id)->status);

        $order = $this->orderService->findById($this->orderId);
        $this->assertSame(Order::STATUS_SHIPPED, $order->status, 'OrderShipped must transition the order to shipped.');

        $history = $this->orderService->statusHistoryFor($this->orderId);
        $shippedEntry = null;
        foreach ($history as $entry) {
            if ($entry->toStatus === Order::STATUS_SHIPPED) {
                $shippedEntry = $entry;
            }
        }
        $this->assertNotNull($shippedEntry, 'The shipped transition must be recorded in order status history.');
        $this->assertSame($this->userId, $shippedEntry->actorUserId);
        $this->assertStringContainsString('Shipment created', $shippedEntry->note);
    }

    public function testPickListGenerationIsIdempotentForTheSameOrder(): void
    {
        $this->orderService->transition($this->orderId, Order::STATUS_PAID, $this->userId, null);
        $this->orderService->transition($this->orderId, Order::STATUS_FULFILLING, $this->userId, null);

        $first = $this->pickListService->generateFor($this->orderId);
        $second = $this->pickListService->generateFor($this->orderId);

        $this->assertSame($first->id, $second->id, 'generateFor must return the existing pick list, never create a second one.');
        $this->assertSame(
            1,
            (int) $this->db->query("SELECT COUNT(*) FROM pick_lists WHERE order_id = {$this->orderId}")->fetchColumn()
        );
    }
}
