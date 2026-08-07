<?php

declare(strict_types=1);

namespace Tests\Integration\Warehouse;

use App\Domains\Inventory\Exceptions\InventoryItemNotFoundException;
use App\Domains\Inventory\Repositories\InventoryItemRepository;
use App\Domains\Inventory\Repositories\StockMovementRepository;
use App\Domains\Inventory\Repositories\StockReservationRepository;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Warehouse\Models\StockTransfer;
use App\Domains\Warehouse\Models\StockTransferItem;
use App\Domains\Warehouse\Repositories\StockTransferRepository;
use App\Domains\Warehouse\Services\StockTransferService;
use App\Logging\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus all domain migrations (incl. 2026_08_07_warehouse_domain.sql).
 *
 * Covers transfer persistence and the §2/§14 atomic pair: completing a
 * transfer applies the negative source leg and positive destination leg
 * inside ONE database transaction -- a failure partway must leave
 * nothing applied, verified here against a real database.
 */
final class StockTransferRepositoryTest extends TestCase
{
    private const SKU = 'REPO-TEST-WH-TRF-001';

    private PDO $db;
    private StockTransferRepository $repository;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;
    private int $sourceWarehouseId = 0;
    private int $destinationWarehouseId = 0;
    private int $sourceInventoryItemId = 0;
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
        $this->repository = new StockTransferRepository($this->db);

        $this->cleanup();
        $this->createFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM stock_transfer_items WHERE stock_transfer_id IN (SELECT id FROM stock_transfers WHERE initiated_by_user_id = {$this->userId})");
        $this->db->exec("DELETE FROM stock_transfers WHERE initiated_by_user_id = {$this->userId}");
        $this->db->exec("DELETE FROM warehouses WHERE code = 'REPO-TEST-WH-TRF-2'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku = '" . self::SKU . "')");
        $this->db->exec("DELETE FROM products WHERE sku = '" . self::SKU . "'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-wh-trf-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-wh-trf-brand'");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-wh-trf@example.com'");
    }

    private function createFixtures(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test WH TRF Category', 'repo-test-wh-trf-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test WH TRF Brand', 'repo-test-wh-trf-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('" . self::SKU . "', 'Repo Test WH TRF Widget', 'repo-test-wh-trf-widget', 'x', 'x', :category_id, :brand_id, 100, 10, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->sourceWarehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();
        $this->db->exec("INSERT INTO warehouses (name, code, is_active) VALUES ('Repo Test WH TRF 2', 'REPO-TEST-WH-TRF-2', 1)");
        $this->destinationWarehouseId = (int) $this->db->lastInsertId();

        // Source has stock; destination deliberately has NO inventory
        // item for this product (used by the atomicity test).
        $this->db->exec(
            "INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold)
             VALUES ({$this->productId}, {$this->sourceWarehouseId}, 10, 0, 0)"
        );
        $this->sourceInventoryItemId = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-wh-trf@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();
    }

    private function transferData(): array
    {
        return [
            'from_warehouse_id' => $this->sourceWarehouseId,
            'to_warehouse_id' => $this->destinationWarehouseId,
            'status' => StockTransfer::STATUS_IN_TRANSIT,
            'initiated_by_user_id' => $this->userId,
        ];
    }

    private function makeService(): StockTransferService
    {
        $logFile = sys_get_temp_dir() . '/warehouse-transfer-test-' . uniqid() . '.log';
        $inventoryService = new InventoryService(
            new InventoryItemRepository($this->db),
            new StockReservationRepository($this->db),
            new StockMovementRepository($this->db),
            new WarehouseRepository($this->db),
            $this->createMock(\App\Domains\Catalog\Repositories\ProductRepositoryInterface::class),
            new Logger($logFile)
        );

        return new StockTransferService($this->repository, $inventoryService, $this->db);
    }

    public function testCreateAndFindByIdRoundTripsWithItems(): void
    {
        $transfer = $this->repository->create($this->transferData(), [
            ['product_id' => $this->productId, 'quantity' => 4],
        ]);

        $this->assertGreaterThan(0, $transfer->id);
        $this->assertSame($this->sourceWarehouseId, $transfer->fromWarehouseId);
        $this->assertSame($this->destinationWarehouseId, $transfer->toWarehouseId);
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->status);
        $this->assertNull($transfer->completedAt);

        $reloaded = $this->repository->findById($transfer->id);
        $this->assertNotNull($reloaded);
        $this->assertCount(1, $reloaded->items);
        $this->assertInstanceOf(StockTransferItem::class, $reloaded->items[0]);
        $this->assertSame($this->productId, $reloaded->items[0]->productId);
        $this->assertSame(4, $reloaded->items[0]->quantity);
    }

    public function testUpdateStatusToCompletedSetsCompletedAt(): void
    {
        $transfer = $this->repository->create($this->transferData(), [
            ['product_id' => $this->productId, 'quantity' => 4],
        ]);

        $this->repository->updateStatus($transfer->id, StockTransfer::STATUS_COMPLETED);

        $reloaded = $this->repository->findById($transfer->id);
        $this->assertSame(StockTransfer::STATUS_COMPLETED, $reloaded->status);
        $this->assertNotNull($reloaded->completedAt);
    }

    public function testAdminListingAndCountCoverAllTransfers(): void
    {
        $transfer = $this->repository->create($this->transferData(), [
            ['product_id' => $this->productId, 'quantity' => 4],
        ]);

        $all = $this->repository->listAllForAdmin(20, 0);
        $ids = array_map(static fn (StockTransfer $t): int => $t->id, $all);

        $this->assertContains($transfer->id, $ids);
        $this->assertSame((int) $this->db->query('SELECT COUNT(*) FROM stock_transfers')->fetchColumn(), $this->repository->countAllForAdmin());
    }

    /**
     * §14 against a real database: the destination warehouse has no
     * inventory item for the product, so the positive leg throws. The
     * whole transfer must roll back -- source quantity unchanged, no
     * movements written, transfer still in_transit.
     */
    public function testCompleteRollsBackEntirelyWhenDestinationLegFails(): void
    {
        $transfer = $this->repository->create($this->transferData(), [
            ['product_id' => $this->productId, 'quantity' => 4],
        ]);

        $service = $this->makeService();

        try {
            $service->complete($transfer->id);
            $this->fail('Expected InventoryItemNotFoundException from the missing destination inventory item.');
        } catch (InventoryItemNotFoundException $exception) {
            $this->assertStringContainsString('No inventory item', $exception->getMessage());
        }

        $reloaded = $this->repository->findById($transfer->id);
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $reloaded->status, 'The transfer must remain in_transit after a failed completion.');

        $source = (int) $this->db->query("SELECT quantity_on_hand FROM inventory_items WHERE id = {$this->sourceInventoryItemId}")->fetchColumn();
        $this->assertSame(10, $source, 'The source leg must be rolled back -- nothing may be half-applied.');

        $movements = (int) $this->db->query(
            "SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = {$this->sourceInventoryItemId}"
        )->fetchColumn();
        $this->assertSame(0, $movements, 'No StockMovement may survive a rolled-back transfer.');
    }

    /**
     * §14 happy path against a real database: both legs apply and the
     * transfer completes, with exactly two audit-trailed movements.
     */
    public function testCompleteAppliesBothLegsAndPersistsMovements(): void
    {
        // Give the destination warehouse an inventory item so both legs succeed.
        $this->db->exec(
            "INSERT INTO inventory_items (product_id, warehouse_id, quantity_on_hand, quantity_reserved, reorder_threshold)
             VALUES ({$this->productId}, {$this->destinationWarehouseId}, 0, 0, 0)"
        );
        $destinationInventoryItemId = (int) $this->db->lastInsertId();

        $transfer = $this->repository->create($this->transferData(), [
            ['product_id' => $this->productId, 'quantity' => 4],
        ]);

        $service = $this->makeService();
        $service->complete($transfer->id);

        $reloaded = $this->repository->findById($transfer->id);
        $this->assertSame(StockTransfer::STATUS_COMPLETED, $reloaded->status);
        $this->assertNotNull($reloaded->completedAt);

        $this->assertSame(6, (int) $this->db->query("SELECT quantity_on_hand FROM inventory_items WHERE id = {$this->sourceInventoryItemId}")->fetchColumn());
        $this->assertSame(4, (int) $this->db->query("SELECT quantity_on_hand FROM inventory_items WHERE id = {$destinationInventoryItemId}")->fetchColumn());

        $sourceMovements = (int) $this->db->query(
            "SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = {$this->sourceInventoryItemId} AND reason = 'stock_transfer_out'"
        )->fetchColumn();
        $destinationMovements = (int) $this->db->query(
            "SELECT COUNT(*) FROM stock_movements WHERE inventory_item_id = {$destinationInventoryItemId} AND reason = 'stock_transfer_in'"
        )->fetchColumn();
        $this->assertSame(1, $sourceMovements, 'Exactly one audit-trailed source movement.');
        $this->assertSame(1, $destinationMovements, 'Exactly one audit-trailed destination movement.');

        $this->db->exec("DELETE FROM inventory_items WHERE id = {$destinationInventoryItemId}");
    }
}
