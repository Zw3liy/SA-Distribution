<?php
declare(strict_types=1);

class ProductService
{
    /** @var ProductRepository */
    private $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getProducts(array $filters, string $sort, int $limit, int $offset): array
    {
        $rows = $this->repository->getProducts($filters, $sort, $limit, $offset);
        return array_map([$this, 'mapProduct'], $rows);
    }

    public function getProductsCount(array $filters): int
    {
        return $this->repository->countProducts($filters);
    }

    public function getProductBySlug(string $slug): ?Product
    {
        $row = $this->repository->getProductBySlug($slug);

        if ($row === null) {
            return null;
        }

        return $this->mapProduct($row);
    }

    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    public function getBrands(): array
    {
        return $this->repository->getBrands();
    }

    public function getProductImages(int $productId): array
    {
        return $this->repository->getProductImages($productId);
    }

    public function getRelatedProducts(int $productId, int $categoryId, int $brandId, int $limit = 4): array
    {
        $rows = $this->repository->getRelatedProducts($productId, $categoryId, $brandId, $limit);
        return array_map([$this, 'mapProduct'], $rows);
    }

    public function trackProductView(Product $product, int $limit = 4): void
    {
        if (!isset($_SESSION['recently_viewed_products'])) {
            $_SESSION['recently_viewed_products'] = [];
        }

        $items = array_filter($_SESSION['recently_viewed_products'], function ($item) use ($product) {
            return $item['id'] !== $product->id;
        });

        array_unshift($items, [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'thumbnail' => $product->thumbnail,
            'price' => $product->price,
            'sale_price' => $product->salePrice,
            'stock' => $product->stock,
            'category_name' => $product->categoryName,
            'brand_name' => $product->brandName,
        ]);

        $_SESSION['recently_viewed_products'] = array_slice($items, 0, $limit);
    }

    public function getRecentlyViewedProducts(int $limit = 4): array
    {
        if (!isset($_SESSION['recently_viewed_products']) || !is_array($_SESSION['recently_viewed_products'])) {
            return [];
        }

        return array_slice($_SESSION['recently_viewed_products'], 0, $limit);
    }

    private function mapProduct(array $row): Product
    {
        return new Product(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['sku'],
            (float) $row['price'],
            $row['sale_price'] !== null ? (float) $row['sale_price'] : null,
            (int) $row['stock'],
            (bool) $row['is_featured'],
            (bool) $row['is_new'],
            (bool) $row['is_on_sale'],
            (string) $row['short_description'],
            (string) $row['description'],
            (int) $row['category_id'],
            (string) $row['category_name'],
            (int) $row['brand_id'],
            (string) $row['brand_name'],
            $row['thumbnail'] ? sprintf('images/%s', $row['thumbnail']) : null,
            (string) $row['created_at']
        );
    }
}
