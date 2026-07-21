<?php
declare(strict_types=1);

namespace Tests\Integration\Inventory;

use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Repositories\InventoryItemRepository;
use App\Domains\Inventory\Repositories\StockReservationRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * docs/specs/05-inventory.md §18: "Integration: reservation expiry
 * sweep against a real test database with a mix of expired/active/
 * consumed rows, asserting only the correct subset transitions."
 */
final class StockReservationExpiryTest extends TestCase
{
    private PDO $db;
    private InventoryItemRepository $itemRepository;
    private StockReservationRepository $reservationRepository;
    private int $categoryId;
    private int $brandId;
    private int $warehouseId;
    private int $productId;
    private int $inventoryItemId;

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
        $this->itemRepository = new InventoryItemRepository($this->db);
        $this->reservationRepository = new StockReservationRepository($this->db);

        $this->cleanup();

        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test Expiry Category', 'repo-test-expiry-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test Expiry Brand', 'repo-test-expiry-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('REPO-TEST-EXP-001', 'Repo Test Expiry Widget', 'repo-test-expiry-widget', 'x', 'x', :category_id, :brand_id, 100, 0, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();

        $this->warehouseId = (int) $this->db->query("SELECT id FROM warehouses WHERE code = 'MAIN' LIMIT 1")->fetchColumn();

        $item = $this->itemRepository->upsertOnHand($this->productId, $this->warehouseId, 100);
        $this->inventoryItemId = $item->id;
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM stock_reservations WHERE order_reference LIKE 'REPO-TEST-EXP-%'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'REPO-TEST-EXP-%')");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-EXP-%'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-expiry-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-expiry-brand'");
    }

    private function insertReservation(string $orderRef, int $quantity, string $expiresAt, string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stock_reservations (inventory_item_id, order_reference, quantity, expires_at, status, created_at)
             VALUES (:inventory_item_id, :order_reference, :quantity, :expires_at, :status, NOW())'
        );
        $stmt->execute([
            'inventory_item_id' => $this->inventoryItemId,
            'order_reference' => $orderRef,
            'quantity' => $quantity,
            'expires_at' => $expiresAt,
            'status' => $status,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mixed active/expired/consumed rows -- only the expired-and-still-
     * active subset should transition, and only that subset's reserved
     * quantity should be released back to the inventory item.
     */
    public function testExpireOlderThanReleasesOnlyExpiredActiveReservations(): void
    {
        $past = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
        $future = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        // Reserve real quantity for each so quantity_reserved reflects
        // all three before the sweep runs.
        $this->itemRepository->tryReserve($this->inventoryItemId, 10); // expired-active #1
        $expiredId1 = $this->insertReservation('REPO-TEST-EXP-1', 10, $past, StockReservation::STATUS_ACTIVE);

        $this->itemRepository->tryReserve($this->inventoryItemId, 15); // expired-active #2
        $expiredId2 = $this->insertReservation('REPO-TEST-EXP-2', 15, $past, StockReservation::STATUS_ACTIVE);

        $this->itemRepository->tryReserve($this->inventoryItemId, 20); // still active, not expired
        $activeId = $this->insertReservation('REPO-TEST-EXP-3', 20, $future, StockReservation::STATUS_ACTIVE);

        $this->itemRepository->tryReserve($this->inventoryItemId, 5); // already consumed, expired timestamp but wrong status
        $consumedId = $this->insertReservation('REPO-TEST-EXP-4', 5, $past, StockReservation::STATUS_CONSUMED);

        $before = $this->itemRepository->findById($this->inventoryItemId);
        $this->assertSame(50, $before->quantityReserved, 'Fixture setup: 10+15+20+5 reserved before the sweep.');

        $releasedCount = $this->reservationRepository->expireOlderThan(new DateTimeImmutable());

        $this->assertSame(2, $releasedCount, 'Only the two expired-and-active reservations should transition.');

        $this->assertSame(StockReservation::STATUS_RELEASED, $this->reservationRepository->findById($expiredId1)->status);
        $this->assertSame(StockReservation::STATUS_RELEASED, $this->reservationRepository->findById($expiredId2)->status);
        $this->assertSame(StockReservation::STATUS_ACTIVE, $this->reservationRepository->findById($activeId)->status, 'Non-expired active reservation must be untouched.');
        $this->assertSame(StockReservation::STATUS_CONSUMED, $this->reservationRepository->findById($consumedId)->status, 'Already-consumed reservation must not be touched even though its timestamp is in the past.');

        $after = $this->itemRepository->findById($this->inventoryItemId);
        $this->assertSame(25, $after->quantityReserved, 'Only 10+15=25 should be released; the still-active 20 and already-consumed 5 remain reserved.');
    }

    public function testExpireOlderThanIsANoOpWhenNothingHasExpired(): void
    {
        $future = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
        $this->itemRepository->tryReserve($this->inventoryItemId, 10);
        $this->insertReservation('REPO-TEST-EXP-5', 10, $future, StockReservation::STATUS_ACTIVE);

        $releasedCount = $this->reservationRepository->expireOlderThan(new DateTimeImmutable());

        $this->assertSame(0, $releasedCount);
    }
}
