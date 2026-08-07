<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use App\Domains\Orders\Models\CartItem;
use App\Domains\Orders\Repositories\CartRepositoryInterface;
use App\Domains\Orders\Services\CartService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The DB-backed cart path CheckoutService builds checkout against
 * (docs/specs/06-orders.md §19 -- moved unchanged from Phase 3's
 * CartService, namespace-only migration, plus markCartConverted()).
 * Behavior worth pinning down: stock clamping on add/update, quantity
 * floors, sale-price-aware summaries, and the converted-cart handoff.
 */
final class CartServiceTest extends TestCase
{
    private function makeService(
        ProductRepositoryInterface $productRepository,
        CartRepositoryInterface $cartRepository
    ): CartService {
        return new CartService($productRepository, $cartRepository);
    }

    private function makeProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'slug' => 'test-widget',
            'name' => 'Test Widget',
            'sku' => 'TEST-WIDGET',
            'price' => 100.0,
            'sale_price' => null,
            'stock' => 10,
            'thumbnail' => null,
        ], $overrides);
    }

    public function testGetCartItemsMapsRowsToCartItemModels(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->with('session-1')->willReturn([
            [
                'product_id' => 1,
                'slug' => 'test-widget',
                'name' => 'Test Widget',
                'sku' => 'TEST-WIDGET',
                'price' => '100.00',
                'sale_price' => '89.00',
                'quantity' => 2,
                'stock' => 10,
                'thumbnail' => 'thumb.jpg',
            ],
            [
                'product_id' => 2,
                'slug' => 'plain-widget',
                'name' => 'Plain Widget',
                'sku' => 'PLAIN-WIDGET',
                'price' => '50.00',
                'sale_price' => null,
                'quantity' => 1,
                'stock' => 5,
                'thumbnail' => null,
            ],
        ]);

        $service = $this->makeService(
            $this->createMock(ProductRepositoryInterface::class),
            $cartRepository
        );
        $items = $service->getCartItems('session-1');

        $this->assertCount(2, $items);
        $this->assertInstanceOf(CartItem::class, $items[0]);
        $this->assertSame('test-widget', $items[0]->slug);
        $this->assertSame(89.0, $items[0]->getUnitPrice(), 'Sale price must win over list price.');
        $this->assertSame(178.0, $items[0]->getLineTotal());
        $this->assertSame(50.0, $items[1]->getUnitPrice(), 'Null sale price falls back to list price.');
    }

    public function testGetCartSummaryComputesQuantitySubtotalVatAndGrandTotal(): void
    {
        $items = [
            new CartItem([
                'product_id' => 1, 'slug' => 'a', 'name' => 'A', 'sku' => 'A',
                'price' => 100.0, 'sale_price' => null, 'quantity' => 2, 'stock' => 5,
            ]),
            new CartItem([
                'product_id' => 2, 'slug' => 'b', 'name' => 'B', 'sku' => 'B',
                'price' => 50.0, 'sale_price' => 40.0, 'quantity' => 3, 'stock' => 5,
            ]),
        ];

        $service = $this->makeService(
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(CartRepositoryInterface::class)
        );
        $summary = $service->getCartSummary($items);

        $this->assertSame(5, $summary['quantity']);
        $this->assertSame(320.0, $summary['subtotal']); // 2*100 + 3*40
        $this->assertSame(48.0, $summary['vat']);
        $this->assertSame(368.0, $summary['grand_total']);
    }

    public function testAddProductToCartAddsNewItemClampedToStock(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->with('test-widget')->willReturn(
            $this->makeProduct(['stock' => 3])
        );

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([]);
        $cartRepository->expects($this->once())
            ->method('saveCartItems')
            ->with('session-1', $this->callback(function (array $items): bool {
                return count($items) === 1
                    && $items[0]['slug'] === 'test-widget'
                    && $items[0]['quantity'] === 3
                    && $items[0]['product_id'] === 1;
            }));

        $service = $this->makeService($productRepository, $cartRepository);
        $service->addProductToCart('session-1', 'test-widget', 99);

        $this->assertTrue(true);
    }

    public function testAddProductToCartIncrementsExistingItemAndClampsToStock(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->willReturn($this->makeProduct(['stock' => 5]));

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([
            ['product_id' => 1, 'slug' => 'test-widget', 'name' => 'Test Widget', 'sku' => 'TEST-WIDGET',
             'price' => 100.0, 'sale_price' => null, 'quantity' => 4, 'stock' => 5],
        ]);
        $cartRepository->expects($this->once())
            ->method('saveCartItems')
            ->with('session-1', $this->callback(function (array $items): bool {
                return $items[0]['quantity'] === 5; // 4 + 2 clamped to stock 5
            }));

        $service = $this->makeService($productRepository, $cartRepository);
        $service->addProductToCart('session-1', 'test-widget', 2);

        $this->assertTrue(true);
    }

    public function testAddProductToCartWithUnknownSlugThrows(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->willReturn(null);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects($this->never())->method('saveCartItems');

        $service = $this->makeService($productRepository, $cartRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found.');

        $service->addProductToCart('session-1', 'missing-widget', 1);
    }

    public function testAddProductToCartClampsRequestedQuantityToMinimumOne(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->willReturn($this->makeProduct(['stock' => 10]));

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([]);
        $cartRepository->expects($this->once())
            ->method('saveCartItems')
            ->with('session-1', $this->callback(function (array $items): bool {
                return $items[0]['quantity'] === 1;
            }));

        $service = $this->makeService($productRepository, $cartRepository);
        $service->addProductToCart('session-1', 'test-widget', -5);

        $this->assertTrue(true);
    }

    public function testUpdateCartItemQuantityClampsToStock(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->willReturn($this->makeProduct(['stock' => 4]));

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([
            ['product_id' => 1, 'slug' => 'test-widget', 'name' => 'Test Widget', 'sku' => 'TEST-WIDGET',
             'price' => 100.0, 'sale_price' => null, 'quantity' => 1, 'stock' => 4],
        ]);
        $cartRepository->expects($this->once())
            ->method('saveCartItems')
            ->with('session-1', $this->callback(function (array $items): bool {
                return count($items) === 1 && $items[0]['quantity'] === 4;
            }));

        $service = $this->makeService($productRepository, $cartRepository);
        $service->updateCartItemQuantity('session-1', 'test-widget', 50);

        $this->assertTrue(true);
    }

    public function testUpdateCartItemQuantityToZeroRemovesTheItem(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects($this->never())->method('getProductBySlug');

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([
            ['product_id' => 1, 'slug' => 'test-widget', 'name' => 'Test Widget', 'sku' => 'TEST-WIDGET',
             'price' => 100.0, 'sale_price' => null, 'quantity' => 2, 'stock' => 4],
        ]);
        $cartRepository->expects($this->once())
            ->method('saveCartItems')
            ->with('session-1', $this->callback(function (array $items): bool {
                return $items === [];
            }));

        $service = $this->makeService($productRepository, $cartRepository);
        $service->updateCartItemQuantity('session-1', 'test-widget', 0);

        $this->assertTrue(true);
    }

    public function testUpdateCartItemQuantityWithUnknownSlugThrows(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('getProductBySlug')->willReturn(null);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('getCartItemsBySession')->willReturn([
            ['product_id' => 1, 'slug' => 'test-widget', 'name' => 'Test Widget', 'sku' => 'TEST-WIDGET',
             'price' => 100.0, 'sale_price' => null, 'quantity' => 2, 'stock' => 4],
        ]);

        $service = $this->makeService($productRepository, $cartRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found.');

        $service->updateCartItemQuantity('session-1', 'test-widget', 3);
    }

    public function testRemoveClearAndMarkConvertedDelegateToRepository(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->expects($this->once())->method('removeCartItem')->with('s', 'slug-x');
        $cartRepository->expects($this->once())->method('clearCart')->with('s');
        $cartRepository->expects($this->once())->method('markConverted')->with('s', 77);

        $service = $this->makeService(
            $this->createMock(ProductRepositoryInterface::class),
            $cartRepository
        );

        $service->removeProductFromCart('s', 'slug-x');
        $service->clearCart('s');
        $service->markCartConverted('s', 77);

        $this->assertTrue(true);
    }
}
