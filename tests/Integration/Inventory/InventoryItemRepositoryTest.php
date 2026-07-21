<?php
declare(strict_types=1);

namespace Tests\Integration\Inventory;

use App\Domains\Inventory\Repositories\InventoryItemRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_inventory_domain.sql migration applied.
 */
final class InventoryItemRepositoryTest extends TestCase
{
    private PDO $db;
    private InventoryItemRepository $repository;
    private int $categoryId;
    private int $brandId;
    private int $warehouseId;
    private int $productId;

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
        $this->repository = new InventoryItemRepository($this->db);

        $this->cleanup();

        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test Inv Category', 'repo-test-inv-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test Inv Brand', 'repo-test-inv-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('REPO-TEST-INV-001', 'Repo Test Inv Widget', 'repo-test-inv-widget', 'x', 'x', :category_id, :brand_id, 100, 0, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'REPO-TEST-INV-%')");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-INV-%'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-inv-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-inv-brand'");
    }

    public function testUpsertOnHandCreatesThenUpdates(): void
    {
        $item = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 50);
        $this->assertSame(50, $item->quantityOnHand);

        $updated = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 75);
        $this->assertSame($item->id, $updated->id);
        $this->assertSame(75, $updated->quantityOnHand);
    }

    /**
     * The concurrency-safety mechanism called out in
     * docs/specs/05-inventory.md §18: a single conditional UPDATE, not a
     * read-then-write pair. Confirms the boundary case (requesting
     * exactly what's available succeeds) and the over-request case
     * (requesting one more than available fails) against a real
     * database, not a mock.
     */
    public function testTryReserveIsAtomicAndRespectsAvailableBoundary(): void
    {
        $item = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 10);

        $this->assertTrue($this->repository->tryReserve($item->id, 10), 'Requesting exactly the available quantity must succeed.');

        $reloaded = $this->repository->findById($item->id);
        $this->assertSame(10, $reloaded->quantityReserved);
        $this->assertSame(0, $reloaded->quantityAvailable());

        $this->assertFalse($this->repository->tryReserve($item->id, 1), 'Requesting more than available must fail, not clamp.');
    }

    public function testReleaseReservedNeverGoesBelowZero(): void
    {
        $item = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 10);
        $this->repository->tryReserve($item->id, 5);

        $this->repository->releaseReserved($item->id, 100);

        $reloaded = $this->repository->findById($item->id);
        $this->assertSame(0, $reloaded->quantityReserved);
    }

    public function testTryAdjustOnHandRejectsNegativeResultByDefault(): void
    {
        $item = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 5);

        $this->assertFalse($this->repository->tryAdjustOnHand($item->id, -10, false));

        $reloaded = $this->repository->findById($item->id);
        $this->assertSame(5, $reloaded->quantityOnHand, 'On-hand quantity must be unchanged when the conditional update is rejected.');
    }

    public function testTryAdjustOnHandAllowsNegativeResultWithOverride(): void
    {
        $item = $this->repository->upsertOnHand($this->productId, $this->warehouseId, 5);

        $this->assertTrue($this->repository->tryAdjustOnHand($item->id, -10, true));

        $reloaded = $this->repository->findById($item->id);
        $this->assertSame(-5, $reloaded->quantityOnHand);
    }

    public function testSumAvailableAggregatesAcrossWarehouses(): void
    {
        $this->db->exec("INSERT INTO warehouses (name, code, is_active) VALUES ('Repo Test Inv Warehouse', 'REPO-TEST-INV-WH', 1)");
        $secondWarehouseId = (int) $this->db->lastInsertId();

        $this->repository->upsertOnHand($this->productId, $this->warehouseId, 20);
        $this->repository->upsertOnHand($this->productId, $secondWarehouseId, 30);

        $this->assertSame(50, $this->repository->sumAvailable($this->productId));

        $this->db->exec("DELETE FROM inventory_items WHERE warehouse_id = {$secondWarehouseId}");
        $this->db->exec("DELETE FROM warehouses WHERE code = 'REPO-TEST-INV-WH'");
    }
}
