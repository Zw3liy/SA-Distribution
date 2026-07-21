<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Exceptions\DuplicateSkuException;
use App\Domains\Catalog\Exceptions\InvalidPricingException;
use App\Domains\Catalog\Exceptions\ProductNotFoundException;
use App\Domains\Catalog\Exceptions\SlugImmutableException;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use InvalidArgumentException;

class ProductService implements ProductServiceInterface
{
    /** @var ProductRepositoryInterface */
    private $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Original Phase 3 logic, unchanged (docs/specs/03-catalog.md §19).
     */
    public function getProducts(array $filters, string $sort, int $limit, int $offset): array
    {
        $rows = $this->repository->getProducts($filters, $sort, $limit, $offset);
        return array_map([$this, 'mapProduct'], $rows);
    }

    public function getProductsCount(array $filters): int
    {
        return $this->repository->countProducts($filters);
    }

    public function getProductsForAdmin(array $filters, string $sort, int $limit, int $offset): array
    {
        $rows = $this->repository->getProductsForAdmin($filters, $sort, $limit, $offset);
        return array_map([$this, 'mapProduct'], $rows);
    }

    public function getProductsCountForAdmin(array $filters): int
    {
        return $this->repository->countProductsForAdmin($filters);
    }

    public function getProductBySlug(string $slug): ?Product
    {
        $row = $this->repository->getProductBySlug($slug);

        if ($row === null) {
            return null;
        }

        return $this->mapProduct($row);
    }

    public function getProductById(int $id): ?Product
    {
        $row = $this->repository->getProductById($id);

        return $row === null ? null : $this->mapProduct($row);
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

    /**
     * New write path (docs/specs/03-catalog.md §5/§8) -- Phase 3 had no
     * product-management write path at all.
     */
    public function create(array $data): Product
    {
        $this->validatePricing($data);
        $this->validateAttributes($data);

        if ($this->repository->findBySku($data['sku']) !== null) {
            throw new DuplicateSkuException(sprintf('SKU "%s" already exists.', $data['sku']));
        }

        $id = $this->repository->insert($data);
        $row = $this->repository->getProductById($id);

        if ($row === null) {
            throw new ProductNotFoundException('Product could not be reloaded after creation.');
        }

        return $this->mapProduct($row);
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->repository->getProductById($id);
        if ($existing === null) {
            throw new ProductNotFoundException(sprintf('Product #%d not found.', $id));
        }

        if (isset($data['slug']) && $data['slug'] !== $existing['slug'] && $this->repository->slugHasTransactionHistory($existing['slug'])) {
            throw new SlugImmutableException('This product\'s slug cannot be changed: it has already appeared in a customer quote or order.');
        }

        if (isset($data['sku']) && $data['sku'] !== $existing['sku']) {
            $bySku = $this->repository->findBySku($data['sku']);
            if ($bySku !== null && (int) $bySku['id'] !== $id) {
                throw new DuplicateSkuException(sprintf('SKU "%s" already exists.', $data['sku']));
            }
        }

        $mergedForValidation = array_merge($existing, $data);
        $this->validatePricing($mergedForValidation, true);
        $this->validateAttributes($data);

        $this->repository->updateFields($id, $data);
    }

    public function deactivate(int $id): void
    {
        $existing = $this->repository->getProductById($id);
        if ($existing === null) {
            throw new ProductNotFoundException(sprintf('Product #%d not found.', $id));
        }

        // Never hard-deletes -- a historical order/quote must still be
        // able to display a since-deactivated product
        // (docs/specs/03-catalog.md §2).
        $this->repository->updateFields($id, ['is_active' => 0]);
    }

    private function validatePricing(array $data, bool $partial = false): void
    {
        if (!$partial && !isset($data['price'])) {
            throw new InvalidArgumentException('Price is required.');
        }

        if (isset($data['price']) && (float) $data['price'] < 0) {
            throw new InvalidPricingException('Price cannot be negative.');
        }

        if (array_key_exists('sale_price', $data) && $data['sale_price'] !== null) {
            $price = (float) ($data['price'] ?? 0);
            if ((float) $data['sale_price'] >= $price) {
                throw new InvalidPricingException('Sale price must be less than the regular price.');
            }
        }
    }

    private function validateAttributes(array $data): void
    {
        if (!array_key_exists('attributes_json', $data) || $data['attributes_json'] === null) {
            return;
        }

        if (!is_array($data['attributes_json'])) {
            throw new InvalidArgumentException('attributes_json must be a valid associative array/JSON object.');
        }
    }

    private function mapProduct(array $row): Product
    {
        return new Product($row);
    }
}
