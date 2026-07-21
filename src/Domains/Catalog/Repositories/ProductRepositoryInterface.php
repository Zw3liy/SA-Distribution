<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Repositories;

interface ProductRepositoryInterface
{
    public function getProducts(array $filters, string $sort, int $limit, int $offset): array;

    public function countProducts(array $filters): int;

    /**
     * Admin-listing variant of getProducts() that does not filter by
     * is_active -- staff must be able to see deactivated products
     * (docs/specs/03-catalog.md §2).
     */
    public function getProductsForAdmin(array $filters, string $sort, int $limit, int $offset): array;

    public function countProductsForAdmin(array $filters): int;

    public function getCategories(): array;

    public function getBrands(): array;

    public function getProductBySlug(string $slug): ?array;

    public function getProductById(int $id): ?array;

    public function getProductImages(int $productId): array;

    public function getRelatedProducts(int $productId, int $categoryId, int $brandId, int $limit = 4): array;

    public function findBySku(string $sku): ?array;

    public function insert(array $data): int;

    public function updateFields(int $id, array $data): void;

    /**
     * Whether this slug has ever appeared in a customer-facing
     * transactional document. No `orders` table exists yet (Orders is
     * domain #6, not yet built) -- this checks `quote_items`, the
     * closest real transactional history that exists today, and will
     * be extended to also check `order_items` once Orders lands
     * (docs/specs/03-catalog.md §2).
     */
    public function slugHasTransactionHistory(string $slug): bool;
}
