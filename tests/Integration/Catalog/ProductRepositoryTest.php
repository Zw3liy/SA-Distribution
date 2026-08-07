<?php
declare(strict_types=1);

namespace Tests\Integration\Catalog;

use App\Domains\Catalog\Repositories\ProductRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_catalog_domain.sql migration applied, reachable
 * via the DB_HOST/DB_NAME/DB_USER/DB_PASS env vars (same convention as
 * tests/Integration/Identity/UserRepositoryTest.php).
 */
final class ProductRepositoryTest extends TestCase
{
    private PDO $db;
    private ProductRepository $repository;
    private int $categoryId;
    private int $brandId;

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
        $this->repository = new ProductRepository($this->db);

        $this->cleanup();

        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test Category', 'repo-test-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test Brand', 'repo-test-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM quote_items WHERE product_slug LIKE 'repo-test-%'");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-%'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-brand'");
    }

    private function baseProductData(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'REPO-TEST-001',
            'name' => 'Repo Test Widget',
            'slug' => 'repo-test-widget',
            'short_description' => 'A test widget.',
            'description' => 'A longer description.',
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'price' => 199.99,
            'sale_price' => null,
            'stock' => 5,
            'is_active' => 1,
        ], $overrides);
    }

    public function testInsertAndFindBySkuRoundTrips(): void
    {
        $id = $this->repository->insert($this->baseProductData());
        $this->assertGreaterThan(0, $id);

        $found = $this->repository->findBySku('REPO-TEST-001');
        $this->assertNotNull($found);
        $this->assertSame('Repo Test Widget', $found['name']);
        $this->assertSame('199.99', $found['price']);
    }

    public function testGetProductsExcludesInactiveButAdminVariantIncludesThem(): void
    {
        $this->repository->insert($this->baseProductData(['sku' => 'REPO-TEST-002', 'slug' => 'repo-test-widget-2', 'is_active' => 0]));

        $storefront = $this->repository->getProducts(['search' => '', 'category_id' => $this->categoryId, 'brand_id' => null], 'newest', 10, 0);
        $admin = $this->repository->getProductsForAdmin(['search' => '', 'category_id' => $this->categoryId, 'brand_id' => null], 'newest', 10, 0);

        $this->assertCount(0, $storefront);
        $this->assertCount(1, $admin);
        $this->assertSame(0, (int) $admin[0]['is_active']);
    }

    public function testUpdateFieldsPersistsChanges(): void
    {
        $id = $this->repository->insert($this->baseProductData());

        $this->repository->updateFields($id, ['price' => 249.50, 'stock' => 3]);

        $row = $this->repository->getProductById($id);
        $this->assertSame('249.50', $row['price']);
        $this->assertSame(3, (int) $row['stock']);
    }

    public function testSlugHasTransactionHistoryReflectsQuoteItems(): void
    {
        $this->assertFalse($this->repository->slugHasTransactionHistory('repo-test-widget'));

        $this->db->exec(
            "INSERT INTO quotes (quote_number, session_id, status, company_name, contact_name, email, phone, subtotal, vat_amount, grand_total)
             VALUES ('REPO-TEST-Q1', 'repo-test-session', 'pending', 'Acme', 'Test', 'test@example.co.za', '0000000000', 100, 15, 115)"
        );
        $quoteId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO quote_items (quote_id, product_id, product_slug, product_name, product_sku, unit_price, quantity, line_total)
             VALUES ({$quoteId}, 1, 'repo-test-widget', 'Repo Test Widget', 'REPO-TEST-001', 100, 1, 100)"
        );

        $this->assertTrue($this->repository->slugHasTransactionHistory('repo-test-widget'));

        $this->db->exec("DELETE FROM quotes WHERE quote_number = 'REPO-TEST-Q1'");
    }

    public function testAttributesJsonRoundTripsThroughInsertAndRead(): void
    {
        $id = $this->repository->insert($this->baseProductData([
            'sku' => 'REPO-TEST-003',
            'slug' => 'repo-test-widget-3',
            'attributes_json' => ['color' => 'red', 'size' => 'M'],
        ]));

        $row = $this->repository->getProductById($id);
        $roundTripped = json_decode($row['attributes_json'], true);

        // Key order is not part of the round-trip contract: MySQL 5.7's
        // JSON binary format stores object keys sorted by length, while
        // MySQL 8.0+/MariaDB preserve insertion order. Compare strictly
        // but order-independently so the same test passes on all three.
        ksort($roundTripped);
        $this->assertSame(['color' => 'red', 'size' => 'M'], $roundTripped);
    }
}
