<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Catalog\Exceptions\DuplicateSkuException;
use App\Domains\Catalog\Exceptions\ProductNotFoundException;
use App\Domains\Catalog\Exceptions\SlugImmutableException;
use App\Domains\Catalog\Services\ProductServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Admin-portal product CRUD (docs/specs/03-catalog.md §7). Defense in
 * depth per the Identity/Administration convention: the Kernel's
 * /admin/* guard already enforces account_kind=staff; this controller
 * additionally enforces the specific permission for each action.
 */
class AdminProductController
{
    private const PER_PAGE = 20;

    /** @var ProductServiceInterface */
    private $productService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /**
     * Consumes Catalog\Events\ProductCreated (docs/specs/05-inventory.md
     * §10) as a direct, synchronous controller-level call rather than a
     * real event-bus subscription -- this platform has none (see
     * Customers' AuthController->CustomerService retrofit for the same
     * pattern). Ensures every product created through this screen
     * immediately has a zero-quantity InventoryItem in the default
     * warehouse.
     *
     * @var InventoryServiceInterface
     */
    private $inventoryService;

    /** @var Config */
    private $config;

    public function __construct(
        ProductServiceInterface $productService,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        InventoryServiceInterface $inventoryService,
        Config $config
    ) {
        $this->productService = $productService;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->inventoryService = $inventoryService;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /admin/catalog/products.
     */
    public function index(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'catalog.product.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $action = trim((string) filter_input(INPUT_POST, 'form_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                if ($action === 'create') {
                    $this->requirePermission($currentUser, 'catalog.product.create');
                    $this->createProduct();
                } elseif ($action === 'update') {
                    $this->requirePermission($currentUser, 'catalog.product.edit');
                    $this->updateProduct();
                } elseif ($action === 'deactivate') {
                    $this->requirePermission($currentUser, 'catalog.product.deactivate');
                    $this->deactivateProduct();
                } else {
                    throw new RuntimeException('Unknown product action.');
                }

                return Response::redirect('/admin/catalog/products');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $offset = ($page - 1) * self::PER_PAGE;
        $filters = ['search' => '', 'category_id' => null, 'brand_id' => null];

        $html = View::render('pages/admin/catalog-products', [
            'appConfig' => $this->config->all(),
            'products' => $this->productService->getProductsForAdmin($filters, 'newest', self::PER_PAGE, $offset),
            'categories' => $this->productService->getCategories(),
            'brands' => $this->productService->getBrands(),
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($this->productService->getProductsCountForAdmin($filters) / self::PER_PAGE)),
            'canCreate' => $this->userService->hasPermission($currentUser, 'catalog.product.create'),
            'canEdit' => $this->userService->hasPermission($currentUser, 'catalog.product.edit'),
            'canDeactivate' => $this->userService->hasPermission($currentUser, 'catalog.product.deactivate'),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    private function requirePermission($user, string $permission): void
    {
        if ($user === null || !$this->userService->hasPermission($user, $permission)) {
            throw new RuntimeException('You do not have permission to perform this action.');
        }
    }

    private function createProduct(): void
    {
        $data = $this->readProductInput();

        try {
            $product = $this->productService->create($data);
        } catch (DuplicateSkuException $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        $this->auditLogger->record('catalog', 'product.created', 'product', $product->id, [], [
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->price,
        ]);

        // Zero-quantity by design (docs/specs/05-inventory.md §10) --
        // this immediately supersedes whatever value was entered in this
        // form's legacy "stock" field, since products.stock is now a
        // read-only mirror of Inventory (§2). Real initial stock must be
        // set afterward via /admin/inventory/adjust, which is
        // audit-trailed; documented as a workflow change in
        // docs/reports/PHASE5-INVENTORY-COMPLETION-REPORT.md.
        $this->inventoryService->initializeForProduct($product->id);

        setFlashMessage('Product created. Set its initial stock via Inventory.');
    }

    private function updateProduct(): void
    {
        $id = (int) filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid product.');
        }

        $before = $this->productService->getProductById($id);
        if ($before === null) {
            throw new ProductNotFoundException(sprintf('Product #%d not found.', $id));
        }

        $data = $this->readProductInput();

        try {
            $this->productService->update($id, $data);
        } catch (DuplicateSkuException|SlugImmutableException $exception) {
            throw new RuntimeException($exception->getMessage());
        }

        $after = $this->productService->getProductById($id);

        $this->auditLogger->record('catalog', 'product.updated', 'product', $id, [
            'price' => $before->price,
            'sale_price' => $before->salePrice,
            'name' => $before->name,
        ], [
            'price' => $after->price,
            'sale_price' => $after->salePrice,
            'name' => $after->name,
        ]);

        setFlashMessage('Product updated.');
    }

    private function deactivateProduct(): void
    {
        $id = (int) filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid product.');
        }

        $this->productService->deactivate($id);
        $this->auditLogger->record('catalog', 'product.deactivated', 'product', $id, [], ['is_active' => false]);

        setFlashMessage('Product deactivated.');
    }

    private function readProductInput(): array
    {
        $name = trim((string) filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $sku = trim((string) filter_input(INPUT_POST, 'sku', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $shortDescription = trim((string) filter_input(INPUT_POST, 'short_description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $description = trim((string) filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW) ?: '');
        $categoryId = (int) filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $brandId = (int) filter_input(INPUT_POST, 'brand_id', FILTER_VALIDATE_INT);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $salePriceRaw = trim((string) filter_input(INPUT_POST, 'sale_price', FILTER_UNSAFE_RAW) ?: '');
        $stock = (int) filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

        if ($name === '' || $slug === '' || $sku === '' || $categoryId <= 0 || $brandId <= 0 || $price === false || $price === null) {
            throw new InvalidArgumentException('Name, slug, SKU, category, brand and price are all required.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'short_description' => $shortDescription,
            'description' => $description,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'price' => (float) $price,
            'sale_price' => $salePriceRaw !== '' ? (float) $salePriceRaw : null,
            'stock' => max(0, $stock),
            'is_featured' => filter_input(INPUT_POST, 'is_featured', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'is_new' => filter_input(INPUT_POST, 'is_new', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'is_on_sale' => filter_input(INPUT_POST, 'is_on_sale', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'is_active' => filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }
}
