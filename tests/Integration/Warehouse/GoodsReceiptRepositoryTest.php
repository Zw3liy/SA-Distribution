<?php

declare(strict_types=1);

namespace Tests\Integration\Warehouse;

use App\Domains\Inventory\Repositories\InventoryItemRepository;
use App\Domains\Inventory\Repositories\StockMovementRepository;
use App\Domains\Inventory\Repositories\StockReservationRepository;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Warehouse\Models\GoodsReceipt;
use App\Domains\Warehouse\Models\GoodsReceiptItem;
use App\Domains\Warehouse\Repositories\GoodsReceiptRepository;
use App\Domains\Warehouse\Services\GoodsReceiptService;
use App\Logging\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus all domain migrations (incl. 2026_08_07_warehouse_domain.sql).
 *
 * Covers the receipt persistence (round trips, transactional create)
 * and the §2 stock integration: receive() increases quantity_on_hand at
 * the receiving warehouse via the real InventoryService -- the same
 * audit-trailed path used in production.
 */
final class GoodsReceiptRepositoryTest extends TestCase
{
    private const SKU = 'REPO-TEST-WH-REC-001';

    private PDO $db;
    private GoodsReceiptRepository $repository;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;
    private int $warehouseId = 0;
    private int $inventoryItemId = 0;
    private int $userId = 0;

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
        $this->repository = new GoodsReceiptRepository($this->db);

        $this->cleanup();
        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM goods_receipt_items WHERE goods_receipt_id IN (SELECT id FROM goods_receipts WHERE reference LIKE 'REPO-TEST-WH-REC-%')");
        $this->db->exec("DELETE FROM goods_receipts WHERE reference LIKE 'REPO-TEST-WH-REC-%'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku = '" . self::SKU . "')");
        $this->db->exec("DELETE FROM products WHERE sku = '" . self::SKU . "'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-wh-rec-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-wh-rec-brand'");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-wh-rec@example.com'");
    }

    private function createFixtures(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test WH REC Category', 'repo-test-wh-rec-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test WH REC Brand', 'repo-test-wh-rec-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('" . self::SKU . "', 'Repo Test WH REC Widget', 'repo-test-wh-rec-widget', 'x', 'x', :category_id, :brand_id, 100, 10, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();

        $this->db->exec(
            "INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold)
             VALUES ({$this->productId}, {$this->warehouseId}, 10, 0, 0)"
        );
        $this->inventoryItemId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-wh-rec@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();
    }

    private function receiptData(array $overrides = []): array
    {
        return array_merge([
            'purchase_order_id' => null,
            'warehouse_id' => $this->warehouseId,
            'received_by_user_id' => $this->userId,
            'reference' => 'REPO-TEST-WH-REC-1',
        ], $overrides);
    }

    public function testCreateAndFindByIdRoundTripsWithItems(): void
    {
        $receipt = $this->repository->create($this->receiptData(), [
            ['product_id' => $this->productId, 'quantity' => 25],
        ]);

        $this->assertGreaterThan(0, $receipt->id);
        $this->assertSame($this->warehouseId, $receipt->warehouseId);
        $this->assertSame($this->userId, $receipt->receivedByUserId);
        $this->assertNull($receipt->purchaseOrderId);
        $this->assertSame('REPO-TEST-WH-REC-1', $receipt->reference);
    }

    public function testCreateWithValidItemsAndPurchaseOrderIdRoundTrips(): void
    {
        $receipt = $this->repository->create($this->receiptData(['purchase_order_id' => 77]), [
            ['product_id' => $this->productId, 'quantity' => 25],
        ]);

        $reloaded = $this->repository->findById($receipt->id);

        $this->assertNotNull($reloaded);
        $this->assertSame(77, $reloaded->purchaseOrderId);
        $this->assertCount(1, $reloaded->items);
        $this->assertInstanceOf(GoodsReceiptItem::class, $reloaded->items[0]);
        $this->assertSame($this->productId, $reloaded->items[0]->productId);
        $this->assertSame(25, $reloaded->items[0]->quantity);
    }

    public function testCreateRollsBackWhenAnItemInsertFails(): void
    {
        $before = (int) $this->db->query('SELECT COUNT(*) FROM goods_receipts')->fetchColumn();

        try {
            $this->repository->create($this->receiptData(), [
                ['product_id' => $this->productId, 'quantity' => 25],
                ['product_id' => 999999, 'quantity' => 1], // violates fk_goods_receipt_items_product
            ]);
            $this->fail('Expected a PDO exception from the FK violation.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }

        $this->assertSame($before, (int) $this->db->query('SELECT COUNT(*) FROM goods_receipts')->fetchColumn());
    }

    public function testAdminListingAndCountCoverAllReceipts(): void
    {
        $receipt = $this->repository->create($this->receiptData(), [
            ['product_id' => $this->productId, 'quantity' => 25],
        ]);

        $all = $this->repository->listAllForAdmin(10, 0);
        $ids = array_map(static fn (GoodsReceipt $r): int => $r->id, $all);

        $this->assertContains($receipt->id, $ids);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM goods_receipts')->fetchColumn(), $this->repository->countAllForAdmin());
    }

    /**
     * docs/specs/07-warehouse.md §2: receiving increases on-hand stock at
     * the receiving warehouse through the real InventoryService -- the
     * same audit-trailed path (StockMovement written, products.stock
     * mirror synced) used in production.
     */
    public function testReceiveThroughServiceIncreasesOnHandStock(): void
    {
        $logFile = sys_get_temp_dir() . '/warehouse-receipt-test-' . uniqid() . '.log';
        $inventoryService = new InventoryService(
            new InventoryItemRepository($this->db),
            new StockReservationRepository($this->db),
            new StockMovementRepository($this->db),
            new WarehouseRepository($this->db),
            $this->createMock(\App\Domains\Catalog\Repositories\ProductRepositoryInterface::class),
            new Logger($logFile)
        );

        $service = new GoodsReceiptService($this->repository, $inventoryService);
        $receipt = $service->receive(null, $this->warehouseId, [
            ['product_id' => $this->productId, 'quantity' => 15],
        ], $this->userId);

        $this->assertGreaterThan(0, $receipt->id);
        $this->assertSame(25, (int) $this->db->query("SELECT quantity_on_hand FROM inventory_items WHERE id = {$this->inventoryItemId}")->fetchColumn());
        $this->assertSame(
            1,
            (int) $this->db->query("SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = {$this->inventoryItemId} AND reason = 'goods_receipt'")->fetchColumn(),
            'The receipt must write exactly one audit-trailed StockMovement.'
        );

        if (is_file($logFile)) {
            unlink($logFile);
        }
    }
}
