<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Repositories;

use PDO;

class ProductRepository implements ProductRepositoryInterface
{
    private const SELECT_COLUMNS = 'p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.stock, p.is_featured, p.is_new, p.is_on_sale, p.is_active, p.short_description, p.description, p.attributes_json, p.tax_class_id, p.category_id, c.name AS category_name, p.brand_id, b.name AS brand_name, pi.file_name AS thumbnail, p.created_at';

    private const BASE_JOIN = 'FROM products AS p JOIN categories AS c ON p.category_id = c.id JOIN brands AS b ON p.brand_id = b.id LEFT JOIN product_images AS pi ON pi.product_id = p.id AND pi.is_primary = 1';

    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getProducts(array $filters, string $sort, int $limit, int $offset): array
    {
        return $this->runListQuery($filters, $sort, $limit, $offset, true);
    }

    public function countProducts(array $filters): int
    {
        return $this->runCountQuery($filters, true);
    }

    public function getProductsForAdmin(array $filters, string $sort, int $limit, int $offset): array
    {
        return $this->runListQuery($filters, $sort, $limit, $offset, false);
    }

    public function countProductsForAdmin(array $filters): int
    {
        return $this->runCountQuery($filters, false);
    }

    public function getCategories(): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBrands(): array
    {
        $stmt = $this->db->prepare('SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductBySlug(string $slug): ?array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' ' . self::BASE_JOIN . ' WHERE p.slug = :slug AND p.is_active = 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product === false ? null : $product;
    }

    /**
     * New: unlike getProductBySlug(), this is not filtered to
     * is_active=1, since staff/admin screens must be able to load a
     * deactivated product to review or reactivate it
     * (docs/specs/03-catalog.md §2).
     */
    public function getProductById(int $id): ?array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' ' . self::BASE_JOIN . ' WHERE p.id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product === false ? null : $product;
    }

    public function getProductImages(int $productId): array
    {
        $stmt = $this->db->prepare('SELECT file_name, alt_text, is_primary FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC');
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRelatedProducts(int $productId, int $categoryId, int $brandId, int $limit = 4): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' ' . self::BASE_JOIN . ' WHERE p.is_active = 1 AND p.id != :product_id AND (p.category_id = :category_id OR p.brand_id = :brand_id) ORDER BY p.is_featured DESC, p.created_at DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':brand_id', $brandId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySku(string $sku): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::SELECT_COLUMNS . ' ' . self::BASE_JOIN . ' WHERE p.sku = :sku');
        $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
        $stmt->execute();

        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        return $product === false ? null : $product;
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (sku, name, slug, short_description, description, attributes_json, category_id, brand_id, tax_class_id, price, sale_price, stock, is_featured, is_new, is_on_sale, is_active, created_at, updated_at)
             VALUES (:sku, :name, :slug, :short_description, :description, :attributes_json, :category_id, :brand_id, :tax_class_id, :price, :sale_price, :stock, :is_featured, :is_new, :is_on_sale, :is_active, NOW(), NOW())'
        );

        $stmt->execute([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'],
            'description' => $data['description'],
            'attributes_json' => isset($data['attributes_json']) ? json_encode($data['attributes_json']) : null,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'],
            'tax_class_id' => $data['tax_class_id'] ?? null,
            'price' => $data['price'],
            'sale_price' => $data['sale_price'] ?? null,
            'stock' => $data['stock'] ?? 0,
            'is_featured' => $data['is_featured'] ?? 0,
            'is_new' => $data['is_new'] ?? 0,
            'is_on_sale' => $data['is_on_sale'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateFields(int $id, array $data): void
    {
        $columnMap = [
            'sku' => 'sku', 'name' => 'name', 'slug' => 'slug',
            'short_description' => 'short_description', 'description' => 'description',
            'category_id' => 'category_id', 'brand_id' => 'brand_id', 'tax_class_id' => 'tax_class_id',
            'price' => 'price', 'sale_price' => 'sale_price', 'stock' => 'stock',
            'is_featured' => 'is_featured', 'is_new' => 'is_new', 'is_on_sale' => 'is_on_sale',
            'is_active' => 'is_active',
        ];

        $fields = [];
        $params = ['id' => $id];

        foreach ($columnMap as $key => $column) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$column} = :{$key}";
                $params[$key] = $data[$key];
            }
        }

        if (array_key_exists('attributes_json', $data)) {
            $fields[] = 'attributes_json = :attributes_json';
            $params['attributes_json'] = json_encode($data['attributes_json']);
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function slugHasTransactionHistory(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM quote_items WHERE product_slug = :slug');
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    private function runListQuery(array $filters, string $sort, int $limit, int $offset, bool $activeOnly): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' ' . self::BASE_JOIN;
        [$where, $params] = $this->buildFilterClause($filters, $activeOnly);
        $sql .= $where;
        $sql .= $this->buildSortClause($sort);
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function runCountQuery(array $filters, bool $activeOnly): int
    {
        $sql = 'SELECT COUNT(*) FROM products AS p';
        [$where, $params] = $this->buildFilterClause($filters, $activeOnly);
        $sql .= $where;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function buildFilterClause(array $filters, bool $activeOnly): array
    {
        $conditions = $activeOnly ? ['p.is_active = 1'] : [];
        $params = [];

        if (($filters['search'] ?? '') !== '') {
            // Three distinct placeholders bound to the same value, not
            // one reused three times: PDO with native prepares
            // (PDO::ATTR_EMULATE_PREPARES => false, see Database.php)
            // rejects a named placeholder that appears more than once in
            // a single query ("SQLSTATE[HY093]: Invalid parameter
            // number") -- a real, pre-existing bug in this query
            // (present since Phase 3) that this domain's regression
            // testing (docs/specs/03-catalog.md §18) surfaced for the
            // first time, since search was never previously exercised
            // live end-to-end.
            $conditions[] = '(p.name LIKE :search_name OR p.short_description LIKE :search_desc OR p.sku LIKE :search_sku)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[':search_name'] = $searchTerm;
            $params[':search_desc'] = $searchTerm;
            $params[':search_sku'] = $searchTerm;
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $conditions[] = 'p.brand_id = :brand_id';
            $params[':brand_id'] = $filters['brand_id'];
        }

        $where = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';

        return [$where, $params];
    }

    private function buildSortClause($sort)
    {
        if ($sort === 'price_asc') {
            return ' ORDER BY p.sale_price IS NOT NULL, COALESCE(p.sale_price, p.price) ASC';
        }

        if ($sort === 'price_desc') {
            return ' ORDER BY p.sale_price IS NOT NULL, COALESCE(p.sale_price, p.price) DESC';
        }

        if ($sort === 'name_asc') {
            return ' ORDER BY p.name ASC';
        }

        if ($sort === 'name_desc') {
            return ' ORDER BY p.name DESC';
        }

        if ($sort === 'newest') {
            return ' ORDER BY p.created_at DESC';
        }

        return ' ORDER BY p.is_featured DESC, p.created_at DESC';
    }
}
