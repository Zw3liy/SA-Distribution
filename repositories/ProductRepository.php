<?php
declare(strict_types=1);

class ProductRepository
{
    /** @var PDO */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getProducts(array $filters, string $sort, int $limit, int $offset): array
    {
        $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.stock, p.is_featured, p.is_new, p.is_on_sale, p.short_description, p.description, p.category_id, c.name AS category_name, p.brand_id, b.name AS brand_name, pi.file_name AS thumbnail, p.created_at FROM products AS p JOIN categories AS c ON p.category_id = c.id JOIN brands AS b ON p.brand_id = b.id LEFT JOIN product_images AS pi ON pi.product_id = p.id AND pi.is_primary = 1";
        $conditions = ['p.is_active = 1'];
        $params = [];

        if ($filters['search'] !== '') {
            $conditions[] = '(p.name LIKE :search OR p.short_description LIKE :search OR p.sku LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $conditions[] = 'p.brand_id = :brand_id';
            $params[':brand_id'] = $filters['brand_id'];
        }

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

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

    public function countProducts(array $filters): int
    {
        $sql = 'SELECT COUNT(*) FROM products AS p WHERE p.is_active = 1';
        $conditions = [];
        $params = [];

        if ($filters['search'] !== '') {
            $conditions[] = '(p.name LIKE :search OR p.short_description LIKE :search OR p.sku LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $conditions[] = 'p.brand_id = :brand_id';
            $params[':brand_id'] = $filters['brand_id'];
        }

        if ($conditions) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
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
        $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.stock, p.is_featured, p.is_new, p.is_on_sale, p.short_description, p.description, p.category_id, c.name AS category_name, p.brand_id, b.name AS brand_name, pi.file_name AS thumbnail, p.created_at FROM products AS p JOIN categories AS c ON p.category_id = c.id JOIN brands AS b ON p.brand_id = b.id LEFT JOIN product_images AS pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.slug = :slug AND p.is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
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
        $sql = "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price, p.stock, p.is_featured, p.is_new, p.is_on_sale, p.short_description, p.description, p.category_id, c.name AS category_name, p.brand_id, b.name AS brand_name, pi.file_name AS thumbnail, p.created_at FROM products AS p JOIN categories AS c ON p.category_id = c.id JOIN brands AS b ON p.brand_id = b.id LEFT JOIN product_images AS pi ON pi.product_id = p.id AND pi.is_primary = 1 WHERE p.is_active = 1 AND p.id != :product_id AND (p.category_id = :category_id OR p.brand_id = :brand_id) ORDER BY p.is_featured DESC, p.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':brand_id', $brandId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
