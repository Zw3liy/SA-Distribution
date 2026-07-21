<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;

interface ProductServiceInterface
{
    public function getProducts(array $filters, string $sort, int $limit, int $offset): array;

    public function getProductsCount(array $filters): int;

    /**
     * Admin-listing variant that includes deactivated products
     * (docs/specs/03-catalog.md §2).
     */
    public function getProductsForAdmin(array $filters, string $sort, int $limit, int $offset): array;

    public function getProductsCountForAdmin(array $filters): int;

    public function getProductBySlug(string $slug): ?Product;

    public function getProductById(int $id): ?Product;

    public function getCategories(): array;

    public function getBrands(): array;

    public function getProductImages(int $productId): array;

    public function getRelatedProducts(int $productId, int $categoryId, int $brandId, int $limit = 4): array;

    public function trackProductView(Product $product, int $limit = 4): void;

    public function getRecentlyViewedProducts(int $limit = 4): array;

    public function create(array $data): Product;

    public function update(int $id, array $data): void;

    /**
     * Never hard-deletes a product with transaction history
     * (docs/specs/03-catalog.md §5).
     */
    public function deactivate(int $id): void;
}
