<?php

declare(strict_types=1);

namespace Tests\Integration\Orders;

use App\Domains\Orders\Repositories\CartRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_orders_domain.sql migration applied.
 *
 * Covers the Phase 3 cart persistence (moved unchanged into Orders per
 * docs/specs/06-orders.md §19) plus the new converted-cart semantics:
 * markConverted() links the cart to its order and getCartIdBySession()
 * excludes converted carts so a post-checkout add-to-cart starts a
 * fresh row (§2/§13).
 */
final class CartRepositoryTest extends TestCase
{
    private PDO $db;
    private CartRepository $repository;
    private int $categoryId = 0;
    private int $brandId = 0;
    private int $productId = 0;

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
        $this->repository = new CartRepository($this->db);

        $this->cleanup();
        $this->createProduct();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        // Dependency-ordered: orders before the users/customers/addresses
        // they reference, and the conversion-test fixtures by marker.
        $this->db->exec("DELETE FROM cart WHERE session_id LIKE 'repo-test-cart-%'");
        $this->db->exec("DELETE FROM orders WHERE order_number = 'REPO-TEST-CART-ORD-1'");
        $this->db->exec("DELETE FROM addresses WHERE label = 'Repo Test CART Address'");
        $this->db->exec("DELETE FROM customers WHERE company_name = 'REPO-TEST-CART'");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-cart-user@example.com'");
        $this->db->exec("DELETE FROM inventory_items WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'REPO-TEST-CART-%')");
        $this->db->exec("DELETE FROM products WHERE sku LIKE 'REPO-TEST-CART-%'");
        $this->db->exec("DELETE FROM categories WHERE slug = 'repo-test-cart-category'");
        $this->db->exec("DELETE FROM brands WHERE slug = 'repo-test-cart-brand'");
    }

    private function createProduct(): void
    {
        $this->db->exec("INSERT INTO categories (name, slug, is_active) VALUES ('Repo Test CART Category', 'repo-test-cart-category', 1)");
        $this->categoryId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO brands (name, slug, is_active) VALUES ('Repo Test CART Brand', 'repo-test-cart-brand', 1)");
        $this->brandId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO products (sku, name, slug, short_description, description, category_id, brand_id, price, stock, is_active, created_at, updated_at)
             VALUES ('REPO-TEST-CART-001', 'Repo Test CART Widget', 'repo-test-cart-widget', 'x', 'x', :category_id, :brand_id, 100, 7, 1, NOW(), NOW())"
        );
        $stmt->execute(['category_id' => $this->categoryId, 'brand_id' => $this->brandId]);
        $this->productId = (int) $this->db->lastInsertId();
    }

    private function cartItemData(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->productId,
            'slug' => 'repo-test-cart-widget',
            'name' => 'Repo Test CART Widget',
            'sku' => 'REPO-TEST-CART-001',
            'price' => 100.0,
            'sale_price' => null,
            'quantity' => 2,
            'thumbnail' => null,
        ], $overrides);
    }

    public function testSaveCartItemsCreatesCartRowOnFirstUse(): void
    {
        $this->assertNull($this->repository->getCartIdBySession('repo-test-cart-1'));

        $this->repository->saveCartItems('repo-test-cart-1', [$this->cartItemData()]);

        $cartId = $this->repository->getCartIdBySession('repo-test-cart-1');
        $this->assertNotNull($cartId);

        $items = $this->repository->getCartItemsBySession('repo-test-cart-1');
        $this->assertCount(1, $items);
        $this->assertSame($this->productId, (int) $items[0]['product_id']);
        $this->assertSame('repo-test-cart-widget', $items[0]['slug']);
        $this->assertSame(2, (int) $items[0]['quantity']);
        $this->assertSame(7, (int) $items[0]['stock'], 'The products.stock compatibility mirror must be joined in.');
    }

    public function testSaveCartItemsReplacesExistingItems(): void
    {
        $this->repository->saveCartItems('repo-test-cart-2', [$this->cartItemData()]);
        $this->repository->saveCartItems('repo-test-cart-2', [
            $this->cartItemData(['quantity' => 5]),
            $this->cartItemData(['product_id' => $this->productId + 1, 'slug' => 'ghost-widget', 'name' => 'Ghost', 'sku' => 'GHOST', 'quantity' => 1]),
        ]);

        $items = $this->repository->getCartItemsBySession('repo-test-cart-2');
        $this->assertCount(2, $items, 'A re-save must replace, not append to, the cart items.');
        $quantities = array_map(static fn (array $i): int => (int) $i['quantity'], $items);
        sort($quantities);
        $this->assertSame([1, 5], $quantities);
    }

    public function testRemoveCartItemDeletesOnlyTheRequestedSlug(): void
    {
        $this->repository->saveCartItems('repo-test-cart-3', [
            $this->cartItemData(),
            $this->cartItemData(['product_id' => $this->productId + 1, 'slug' => 'other-widget', 'name' => 'Other', 'sku' => 'OTHER-1', 'quantity' => 1]),
        ]);

        $this->repository->removeCartItem('repo-test-cart-3', 'repo-test-cart-widget');

        $items = $this->repository->getCartItemsBySession('repo-test-cart-3');
        $this->assertCount(1, $items);
        $this->assertSame('other-widget', $items[0]['slug']);
    }

    public function testClearCartRemovesAllItemsButKeepsCartRow(): void
    {
        $this->repository->saveCartItems('repo-test-cart-4', [$this->cartItemData()]);

        $this->repository->clearCart('repo-test-cart-4');

        $this->assertSame([], $this->repository->getCartItemsBySession('repo-test-cart-4'));
        $this->assertNotNull($this->repository->getCartIdBySession('repo-test-cart-4'));
    }

    /**
     * docs/specs/06-orders.md §2/§13: after checkout the cart is marked
     * converted rather than deleted, and the next save for the same
     * session creates a brand-new cart row instead of polluting the
     * order-linked one.
     */
    public function testMarkConvertedExcludesCartAndNextSaveCreatesFreshCart(): void
    {
        $this->repository->saveCartItems('repo-test-cart-5', [$this->cartItemData()]);
        $originalCartId = $this->repository->getCartIdBySession('repo-test-cart-5');

        // Minimal orders row to satisfy the fk_cart_converted_order FK.
        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-cart-user@example.com', '0000000000', 'x', 1, 1)"
        );
        $userId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO customers (account_type, company_name, created_at, updated_at) VALUES ('b2c', 'REPO-TEST-CART', NOW(), NOW())");
        $customerId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES ({$customerId}, 'Repo Test CART Address', 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $addressId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO orders (order_number, customer_id, user_id, status, subtotal, tax_total, grand_total, shipping_address_id, billing_address_id)
             VALUES ('REPO-TEST-CART-ORD-1', {$customerId}, {$userId}, 'pending_payment', 200, 30, 230, {$addressId}, {$addressId})"
        );
        $orderId = (int) $this->db->lastInsertId();

        $this->repository->markConverted('repo-test-cart-5', $orderId);

        $this->assertNull(
            $this->repository->getCartIdBySession('repo-test-cart-5'),
            'A converted cart must no longer be returned as the active cart.'
        );

        $this->repository->saveCartItems('repo-test-cart-5', [$this->cartItemData()]);
        $newCartId = $this->repository->getCartIdBySession('repo-test-cart-5');

        $this->assertNotNull($newCartId);
        $this->assertNotSame($originalCartId, $newCartId, 'Post-checkout save must create a fresh cart row.');

        $converted = (int) $this->db->query(
            "SELECT converted_to_order_id FROM cart WHERE id = {$originalCartId}"
        )->fetchColumn();
        $this->assertSame($orderId, $converted, 'The historical cart keeps its order link.');
    }
}
