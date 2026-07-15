<?php
declare(strict_types=1);

class ProductController
{
    /** @var ProductService */
    private $service;

    /** @var int */
    private $perPage = 12;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function handleRequest(): array
    {
        $page = (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $page = $page >= 1 ? $page : 1;

        $search = trim((string) filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
        $brand = filter_input(INPUT_GET, 'brand', FILTER_VALIDATE_INT);
        $sort = trim((string) filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'featured');

        $allowedSorts = ['price_asc', 'price_desc', 'name_asc', 'name_desc', 'newest', 'featured'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'featured';
        }

        $filters = [
            'search' => $search,
            'category_id' => $category,
            'brand_id' => $brand,
        ];

        $offset = ($page - 1) * $this->perPage;
        $products = $this->service->getProducts($filters, $sort, $this->perPage, $offset);
        $totalProducts = $this->service->getProductsCount($filters);

        return [
            'products' => $products,
            'categories' => $this->service->getCategories(),
            'brands' => $this->service->getBrands(),
            'search' => $search,
            'selectedCategory' => $category,
            'selectedBrand' => $brand,
            'sort' => $sort,
            'currentPage' => $page,
            'perPage' => $this->perPage,
            'totalProducts' => $totalProducts,
            'totalPages' => (int) ceil($totalProducts / $this->perPage),
        ];
    }
}
