<?php

declare(strict_types=1);

namespace Tests\Integration\Orders;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\Repositories\OrderRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_orders_domain.sql migration applied.
 *
 * Covers the transactional order+items write, the customer/admin
 * paginated reads, status updates, and the inventory_reservation_id
 * back-reference that OrderService::transition() relies on
 * (docs/specs/06-orders.md §2/§3).
 */
final class OrderRepositoryTest extends TestCase
{
    private const SKU_PREFIX = 'REPO-TEST-ORD-';
    private const ORDER_PREFIX = 'REPO-TEST-ORD-';

    private PDO $db;
    private OrderRepository $repository;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;
    private int $userId = 0;
    private int $customerId = 0;
    private int $shippingAddressId = 0;
    private int $billingAddressId = 0;

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
        $this->repository = new OrderRepository($this->db);

        $this->cleanup();
        $this->createFixtureGraph();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    /**
     * Dependency-ordered cleanup: reservations and orders first (other
     * tables reference them), then addresses/customers/users, then the
     * catalog fixture rows.
     */
    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM stock_reservations WHERE order_reference LIKE 'REPO-TEST-ORD-%'");
        $this->db->exec("DELETE FROM orders WHERE order_number LIKE 'REPO-TEST-ORD-%'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'REPO-TEST-ORD-%')");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-ORD-%'");
        $this->db->exec("DELETE FROM addresses WHERE label LIKE 'Repo Test ORD%'");
        $this->db->exec("DELETE FROM customers WHERE id = {$this->customerId}");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'repo-test-ord-%@example.com'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-ord-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-ord-brand'");

        $this->categoryId = $this->brandId = $this->productId = 0;
        $this->userId = $this->customerId = $this->shippingAddressId = $this->billingAddressId = 0;
    }

    private function createFixtureGraph(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test ORD Category', 'repo-test-ord-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test ORD Brand', 'repo-test-ord-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('REPO-TEST-ORD-001', 'Repo Test ORD Widget', 'repo-test-ord-widget', 'x', 'x', :category_id, :brand_id, 100, 10, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-ord-1@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO customers (account_type, company_name, created_at, updated_at)
             VALUES ('b2c', NULL, NOW(), NOW())"
        );
        $this->customerId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES (:customer_id, :label, 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $stmt->execute(['customer_id' => $this->customerId, 'label' => 'Repo Test ORD Shipping']);
        $this->shippingAddressId = (int) $this->db->lastInsertId();
        $stmt->execute(['customer_id' => $this->customerId, 'label' => 'Repo Test ORD Billing']);
        $this->billingAddressId = (int) $this->db->lastInsertId();
    }

    private function orderData(array $overrides = []): array
    {
        return array_merge([
            'order_number' => self::ORDER_PREFIX . bin2hex(random_bytes(4)),
            'customer_id' => $this->customerId,
            'user_id' => $this->userId,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'subtotal' => 200.0,
            'tax_total' => 30.0,
            'grand_total' => 230.0,
            'shipping_address_id' => $this->shippingAddressId,
            'billing_address_id' => $this->billingAddressId,
        ], $overrides);
    }

    private function orderItems(): array
    {
        return [[
            'product_id' => $this->productId,
            'sku' => self::SKU_PREFIX . '001',
            'name_snapshot' => 'Repo Test ORD Widget',
            'quantity' => 2,
            'unit_price_snapshot' => 100.0,
            'line_total' => 200.0,
        ]];
    }

    public function testCreatePersistsOrderAndItemsTransactionally(): void
    {
        $data = $this->orderData();
        $order = $this->repository->create($data, $this->orderItems());

        $this->assertGreaterThan(0, $order->id);
        $this->assertSame($data['order_number'], $order->orderNumber);
        $this->assertSame($this->customerId, $order->customerId);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(230.0, $order->grandTotal);

        $reloaded = $this->repository->findById($order->id);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->items);
        $this->assertInstanceOf(OrderItem::class, $reloaded->items[0]);
        $this->assertSame($this->productId, $reloaded->items[0]->productId);
        $this->assertSame('Repo Test ORD Widget', $reloaded->items[0]->nameSnapshot);
        $this->assertSame(2, $reloaded->items[0]->quantity);
        $this->assertSame(200.0, $reloaded->items[0]->lineTotal);
        $this->assertNull($reloaded->items[0]->inventoryReservationId);
    }

    public function testCreateRollsBackEntireOrderWhenAnItemInsertFails(): void
    {
        $before = (int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();

        $items = $this->orderItems();
        $items[0]['product_id'] = 999999; // violates fk_order_items_product

        try {
            $this->repository->create($this->orderData(), $items);
            $this->fail('Expected a PDO exception from the FK violation.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }

        $after = (int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $this->assertSame($before, $after, 'The failed create must not leave a partial order row.');
    }

    public function testFindByIdReturnsNullForUnknownOrder(): void
    {
        $this->assertNull($this->repository->findById(999999));
    }

    public function testFindByCustomerPaginatesAndOrdersByPlacedAtDesc(): void
    {
        $first = $this->repository->create($this->orderData(['subtotal' => 100.0, 'tax_total' => 15.0, 'grand_total' => 115.0]), $this->orderItems());
        usleep(1100000); // ensure distinct placed_at timestamps (NOW() has 1s precision)
        $second = $this->repository->create($this->orderData(['subtotal' => 50.0, 'tax_total' => 7.5, 'grand_total' => 57.5]), $this->orderItems());

        $page1 = $this->repository->findByCustomer($this->customerId, 1, 0);
        $page2 = $this->repository->findByCustomer($this->customerId, 1, 1);

        $this->assertCount(1, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame($second->id, $page1[0]->id, 'Newest order must come first.');
        $this->assertSame($first->id, $page2[0]->id);

        $this->assertSame(2, $this->repository->countByCustomer($this->customerId));
    }

    public function testCountByCustomerScopesToTheCustomer(): void
    {
        $this->repository->create($this->orderData(), $this->orderItems());

        $this->assertSame(1, $this->repository->countByCustomer($this->customerId));
        $this->assertSame(0, $this->repository->countByCustomer(999999));
    }

    public function testAdminListingAndCountCoverAllOrders(): void
    {
        $order = $this->repository->create($this->orderData(), $this->orderItems());

        $all = $this->repository->findAllForAdmin(25, 0);
        $ids = array_map(static fn (Order $o): int => $o->id, $all);

        $this->assertContains($order->id, $ids);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn(), $this->repository->countAllForAdmin());
    }

    public function testUpdateStatusPersists(): void
    {
        $order = $this->repository->create($this->orderData(), $this->orderItems());

        $this->repository->updateStatus($order->id, Order::STATUS_PAID);

        $reloaded = $this->repository->findById($order->id);
        $this->assertSame(Order::STATUS_PAID, $reloaded->status);
    }

    /**
     * The inventory_reservation_id back-reference (§3, documented
     * extension) is what lets OrderService::transition() consume or
     * release the correct StockReservation per line item -- it must
     * survive the write/read round trip.
     */
    public function testCreateStoresInventoryReservationIdOnItem(): void
    {
        $warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();
        $this->assertGreaterThan(0, $warehouseId, 'The inventory migration must have seeded the MAIN warehouse.');

        $this->db->exec(
            "INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold)
             VALUES ({$this->productId}, {$warehouseId}, 10, 2, 0)"
        );
        $inventoryItemId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO stock_reservations (inventory_item_id, order_reference, quantity, expires_at, status)
             VALUES ({$inventoryItemId}, 'REPO-TEST-ORD-RES-1', 2, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 'active')"
        );
        $reservationId = (int) $this->db->lastInsertId();

        $items = $this->orderItems();
        $items[0]['inventory_reservation_id'] = $reservationId;

        $order = $this->repository->create($this->orderData(), $items);
        $reloaded = $this->repository->findById($order->id);

        $this->assertSame($reservationId, $reloaded->items[0]->inventoryReservationId);
    }
}
