<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Http\Request;
use App\Http\Response;
use App\Services\ProductService;
use App\Support\View;

class ProductController
{
    /** @var ProductService */
    private $service;

    /** @var Config */
    private $config;

    /** @var int */
    private $perPage = 12;

    public function __construct(ProductService $service, Config $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged.
     */
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

    /**
     * Route action for GET /products.php.
     */
    public function index(Request $request): Response
    {
        $data = $this->handleRequest();

        $html = View::render('pages/products', array_merge($data, [
            'appConfig' => $this->config->all(),
        ]));

        return Response::html($html);
    }

    /**
     * Route action for GET /product-details.php.
     */
    public function show(Request $request): Response
    {
        $slug = trim((string) filter_input(INPUT_GET, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        if ($slug === '') {
            return Response::redirect('/products.php');
        }

        $product = $this->service->getProductBySlug($slug);
        if ($product === null) {
            $html = View::render('pages/product-not-found', [
                'appConfig' => $this->config->all(),
            ]);

            return Response::notFound($html);
        }

        $productImages = $this->service->getProductImages($product->id);
        $relatedProducts = $this->service->getRelatedProducts($product->id, $product->categoryId, $product->brandId);
        $this->service->trackProductView($product);
        $recentlyViewed = array_filter($this->service->getRecentlyViewedProducts(), function ($item) use ($product) {
            return $item['id'] !== $product->id;
        });

        $html = View::render('pages/product-details', [
            'appConfig' => $this->config->all(),
            'product' => $product,
            'productImages' => $productImages,
            'relatedProducts' => $relatedProducts,
            'recentlyViewed' => $recentlyViewed,
        ]);

        return Response::html($html);
    }
}
