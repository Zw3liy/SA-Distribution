<?php
declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Domains\Catalog\Exceptions\DuplicateSkuException;
use App\Domains\Catalog\Exceptions\InvalidPricingException;
use App\Domains\Catalog\Exceptions\ProductNotFoundException;
use App\Domains\Catalog\Exceptions\SlugImmutableException;
use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use App\Domains\Catalog\Services\ProductService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private function makeRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'Test Widget',
            'slug' => 'test-widget',
            'sku' => 'WID-001',
            'price' => '100.00',
            'sale_price' => null,
            'stock' => 10,
            'is_featured' => 0,
            'is_new' => 0,
            'is_on_sale' => 0,
            'is_active' => 1,
            'short_description' => 'A widget.',
            'description' => 'A longer description of the widget.',
            'attributes_json' => null,
            'tax_class_id' => null,
            'category_id' => 1,
            'category_name' => 'Widgets',
            'brand_id' => 1,
            'brand_name' => 'Acme',
            'thumbnail' => null,
            'created_at' => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public function testCreateRejectsSalePriceGreaterThanOrEqualToPrice(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySku')->willReturn(null);
        $repo->expects($this->never())->method('insert');

        $service = new ProductService($repo);

        $this->expectException(InvalidPricingException::class);
        $service->create([
            'sku' => 'WID-002', 'name' => 'X', 'slug' => 'x', 'short_description' => 'x', 'description' => 'x',
            'category_id' => 1, 'brand_id' => 1, 'price' => 100.00, 'sale_price' => 100.00,
        ]);
    }

    public function testCreateRejectsNegativePrice(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySku')->willReturn(null);

        $service = new ProductService($repo);

        $this->expectException(InvalidPricingException::class);
        $service->create([
            'sku' => 'WID-002', 'name' => 'X', 'slug' => 'x', 'short_description' => 'x', 'description' => 'x',
            'category_id' => 1, 'brand_id' => 1, 'price' => -5.00,
        ]);
    }

    public function testCreateAcceptsValidSalePriceBelowPrice(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySku')->willReturn(null);
        $repo->expects($this->once())->method('insert')->willReturn(42);
        $repo->method('getProductById')->willReturn($this->makeRow(['id' => 42, 'sale_price' => '80.00']));

        $service = new ProductService($repo);
        $product = $service->create([
            'sku' => 'WID-002', 'name' => 'X', 'slug' => 'x', 'short_description' => 'x', 'description' => 'x',
            'category_id' => 1, 'brand_id' => 1, 'price' => 100.00, 'sale_price' => 80.00,
        ]);

        $this->assertSame(42, $product->id);
        $this->assertSame(80.0, $product->salePrice);
    }

    public function testCreateRejectsDuplicateSku(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySku')->willReturn($this->makeRow());
        $repo->expects($this->never())->method('insert');

        $service = new ProductService($repo);

        $this->expectException(DuplicateSkuException::class);
        $service->create([
            'sku' => 'WID-001', 'name' => 'X', 'slug' => 'x', 'short_description' => 'x', 'description' => 'x',
            'category_id' => 1, 'brand_id' => 1, 'price' => 100.00,
        ]);
    }

    public function testCreateRejectsNonArrayAttributesJson(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySku')->willReturn(null);
        $repo->expects($this->never())->method('insert');

        $service = new ProductService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->create([
            'sku' => 'WID-002', 'name' => 'X', 'slug' => 'x', 'short_description' => 'x', 'description' => 'x',
            'category_id' => 1, 'brand_id' => 1, 'price' => 100.00, 'attributes_json' => 'not-an-array',
        ]);
    }

    public function testUpdateThrowsProductNotFoundForMissingProduct(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn(null);

        $service = new ProductService($repo);

        $this->expectException(ProductNotFoundException::class);
        $service->update(999, ['price' => 50.00]);
    }

    /**
     * The slug-immutability rule (docs/specs/03-catalog.md §2): once a
     * slug has appeared in a customer-facing transactional document, it
     * cannot be changed.
     */
    public function testUpdateThrowsSlugImmutableWhenSlugChangedWithTransactionHistory(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn($this->makeRow());
        $repo->method('slugHasTransactionHistory')->with('test-widget')->willReturn(true);
        $repo->expects($this->never())->method('updateFields');

        $service = new ProductService($repo);

        $this->expectException(SlugImmutableException::class);
        $service->update(1, ['slug' => 'new-slug']);
    }

    public function testUpdateAllowsSlugChangeWhenNoTransactionHistory(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn($this->makeRow());
        $repo->method('slugHasTransactionHistory')->willReturn(false);
        $repo->expects($this->once())->method('updateFields')->with(1, ['slug' => 'new-slug']);

        $service = new ProductService($repo);
        $service->update(1, ['slug' => 'new-slug']);

        $this->assertTrue(true);
    }

    public function testUpdateRejectsDuplicateSkuFromAnotherProduct(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn($this->makeRow());
        $repo->method('findBySku')->willReturn($this->makeRow(['id' => 999, 'sku' => 'OTHER-SKU']));
        $repo->expects($this->never())->method('updateFields');

        $service = new ProductService($repo);

        $this->expectException(DuplicateSkuException::class);
        $service->update(1, ['sku' => 'OTHER-SKU']);
    }

    public function testDeactivateSetsIsActiveFalseWithoutHardDeleting(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn($this->makeRow());
        $repo->expects($this->once())->method('updateFields')->with(1, ['is_active' => 0]);

        $service = new ProductService($repo);
        $service->deactivate(1);

        $this->assertTrue(true);
    }

    public function testDeactivateThrowsProductNotFoundForMissingProduct(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('getProductById')->willReturn(null);

        $service = new ProductService($repo);

        $this->expectException(ProductNotFoundException::class);
        $service->deactivate(999);
    }
}
