<?php

declare(strict_types=1);

namespace Tests\Integration\Warehouse;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Repositories\OrderRepository;
use App\Domains\Warehouse\Models\PickList;
use App\Domains\Warehouse\Models\PickListItem;
use App\Domains\Warehouse\Repositories\PickListRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_*_domain.sql and 2026_08_07_warehouse_domain.sql
 * migrations applied.
 */
final class PickListRepositoryTest extends TestCase
{
    private PDO $db;
    private PickListRepository $repository;
    private OrderRepository $orderRepository;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;
    private int $userId = 0;
    private int $customerId = 0;
    private int $addressId = 0;
    private int $orderId = 0;
    private int $warehouseId = 0;

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
        $this->repository = new PickListRepository($this->db);
        $this->orderRepository = new OrderRepository($this->db);

        $this->cleanup();
        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM shipments WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'REPO-TEST-WH-%')");
        $this->db->exec("DELETE FROM packing_slips WHERE pick_list_id IN (SELECT id FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'REPO-TEST-WH-%'))");
        $this->db->exec("DELETE FROM pick_list_items WHERE pick_list_id IN (SELECT id FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'REPO-TEST-WH-%'))");
        $this->db->exec("DELETE FROM pick_lists WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'REPO-TEST-WH-%')");
        $this->db->exec("DELETE FROM orders WHERE order_number LIKE 'REPO-TEST-WH-%'");
        $this->db->exec("DELETE FROM addresses WHERE label LIKE 'Repo Test WH%'");
        $this->db->exec("DELETE FROM customers WHERE id = {$this->customerId}");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'repo-test-wh-%@example.com'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'REPO-TEST-WH-%')");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-WH-%'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-wh-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-wh-brand'");

        $this->categoryId = $this->brandId = $this->productId = 0;
        $this->userId = $this->customerId = $this->addressId = $this->orderId = 0;
    }

    private function createFixtures(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test WH Category', 'repo-test-wh-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test WH Brand', 'repo-test-wh-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('REPO-TEST-WH-001', 'Repo Test WH Widget', 'repo-test-wh-widget', 'x', 'x', :category_id, :brand_id, 100, 10, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-wh-1@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO customers (account_type, company_name, created_at, updated_at) VALUES ('b2c', NULL, NOW(), NOW())");
        $this->customerId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES (:customer_id, 'Repo Test WH Address', 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $stmt->execute(['customer_id' => $this->customerId]);
        $this->addressId = (int) $this->db->lastInsertId();

        $order = $this->orderRepository->create([
            'order_number' => 'REPO-TEST-WH-ORD-1',
            'customer_id' => $this->customerId,
            'user_id' => $this->userId,
            'status' => Order::STATUS_FULFILLING,
            'subtotal' => 200.0,
            'tax_total' => 30.0,
            'grand_total' => 230.0,
            'shipping_address_id' => $this->addressId,
            'billing_address_id' => $this->addressId,
        ], [[
            'product_id' => $this->productId,
            'sku' => 'REPO-TEST-WH-001',
            'name_snapshot' => 'Repo Test WH Widget',
            'quantity' => 2,
            'unit_price_snapshot' => 100.0,
            'line_total' => 200.0,
        ]]);
        $this->orderId = $order->id;
    }

    private function pickListData(): array
    {
        return [
            'order_id' => $this->orderId,
            'warehouse_id' => $this->warehouseId,
            'status' => PickList::STATUS_OPEN,
        ];
    }

    private function pickListItems(): array
    {
        return [
            ['product_id' => $this->productId, 'quantity' => 2],
            ['product_id' => $this->productId + 9999, 'quantity' => 1], // non-existent product would violate the FK; only used when valid
        ];
    }

    public function testCreateAndFindByIdRoundTripsWithItems(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $this->assertGreaterThan(0, $pickList->id);
        $this->assertSame($this->orderId, $pickList->orderId);
        $this->assertSame($this->warehouseId, $pickList->warehouseId);
        $this->assertSame(PickList::STATUS_OPEN, $pickList->status);

        $reloaded = $this->repository->findById($pickList->id);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->items);
        $this->assertInstanceOf(PickListItem::class, $reloaded->items[0]);
        $this->assertSame($this->productId, $reloaded->items[0]->productId);
        $this->assertSame(2, $reloaded->items[0]->quantity);
        $this->assertNull($reloaded->items[0]->pickedAt);
    }

    public function testFindByOrderReturnsNullWhenNoPickListExists(): void
    {
        $this->assertNull($this->repository->findByOrder($this->orderId));
    }

    public function testFindByOrderReturnsThePickListForTheOrder(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $found = $this->repository->findByOrder($this->orderId);

        $this->assertNotNull($found);
        $this->assertSame($pickList->id, $found->id);
    }

    public function testCreateRollsBackWhenAnItemInsertFails(): void
    {
        $before = (int) $this->db->query('SELECT COUNT(*) FROM pick_lists')->fetchColumn();

        try {
            $this->repository->create($this->pickListData(), $this->pickListItems()); // second item has a bad product FK
            $this->fail('Expected a PDO exception from the FK violation.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }

        $after = (int) $this->db->query('SELECT COUNT(*) FROM pick_lists')->fetchColumn();
        $this->assertSame($before, $after, 'The failed create must not leave a partial pick list row.');
    }

    public function testUpdateStatusPersists(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $this->repository->updateStatus($pickList->id, PickList::STATUS_PICKED);

        $reloaded = $this->repository->findById($pickList->id);
        $this->assertSame(PickList::STATUS_PICKED, $reloaded->status);
    }

    public function testMarkItemPickedPersistsPickedAt(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $this->repository->markItemPicked($pickList->items[0]->id);

        $reloaded = $this->repository->findById($pickList->id);
        $this->assertNotNull($reloaded->items[0]->pickedAt);
    }

    public function testCreatePackingSlipPersistsAndIsUniquePerPickList(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $slip = $this->repository->createPackingSlip($pickList->id, $this->userId);

        $this->assertGreaterThan(0, $slip->id);
        $this->assertSame($pickList->id, $slip->pickListId);
        $this->assertSame($this->userId, $slip->packedByUserId);

        try {
            $this->repository->createPackingSlip($pickList->id, $this->userId);
            $this->fail('Expected a duplicate-key error for a second packing slip on the same pick list.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('Duplicate entry', $exception->getMessage());
        }
    }

    public function testAdminListingAndCountCoverAllPickLists(): void
    {
        $pickList = $this->repository->create($this->pickListData(), [
            ['product_id' => $this->productId, 'quantity' => 2],
        ]);

        $all = $this->repository->listAllForAdmin(25, 0);
        $ids = array_map(static fn (PickList $p): int => $p->id, $all);

        $this->assertContains($pickList->id, $ids);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM pick_lists')->fetchColumn(), $this->repository->countAllForAdmin());
    }
}
