<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Catalog\Services\ProductServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Inventory\Exceptions\InventoryItemNotFoundException;
use App\Domains\Inventory\Repositories\InventoryItemRepositoryInterface;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Stock level view/adjustment screen per warehouse
 * (docs/specs/05-inventory.md §7/§13), permission-gated. Every
 * adjustment is routed through InventoryService::adjust() -- never a
 * direct SQL update from this controller -- so the audit-trail rule
 * (§2) can't be bypassed. Defense in depth per the established
 * convention: the Kernel's /admin/* guard enforces account_kind=staff;
 * this controller additionally enforces inventory.stock.view /
 * inventory.stock.adjust.
 */
class AdminInventoryController
{
    private const PER_PAGE = 25;

    /** @var InventoryServiceInterface */
    private $inventoryService;

    /** @var InventoryItemRepositoryInterface */
    private $itemRepository;

    /** @var WarehouseRepositoryInterface */
    private $warehouseRepository;

    /** @var ProductServiceInterface */
    private $productService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(
        InventoryServiceInterface $inventoryService,
        InventoryItemRepositoryInterface $itemRepository,
        WarehouseRepositoryInterface $warehouseRepository,
        ProductServiceInterface $productService,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        Config $config
    ) {
        $this->inventoryService = $inventoryService;
        $this->itemRepository = $itemRepository;
        $this->warehouseRepository = $warehouseRepository;
        $this->productService = $productService;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET /admin/inventory -- per-warehouse stock list,
     * filterable/searchable (§13).
     */
    public function index(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'inventory.stock.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $warehouses = $this->warehouseRepository->all();
        $warehouseId = (int) filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);
        if ($warehouseId <= 0 && !empty($warehouses)) {
            $warehouseId = $warehouses[0]->id;
        }

        $search = trim((string) filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $items = $warehouseId > 0 ? $this->itemRepository->listForWarehouse($warehouseId, $search, self::PER_PAGE, $offset) : [];
        $total = $warehouseId > 0 ? $this->itemRepository->countForWarehouse($warehouseId, $search) : 0;

        $html = View::render('pages/admin/inventory', [
            'appConfig' => $this->config->all(),
            'warehouses' => $warehouses,
            'currentWarehouseId' => $warehouseId,
            'items' => $items,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($total / self::PER_PAGE)),
            'canAdjust' => $this->userService->hasPermission($currentUser, 'inventory.stock.adjust'),
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/inventory/adjust -- routed with
     * product_id/warehouse_id query/post params rather than a path
     * parameter, since the Router only supports exact-path matching
     * (same convention as Customers' /admin/customers/view).
     */
    public function adjust(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'inventory.stock.adjust')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';
        $productId = (int) filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
        $warehouseId = (int) filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $productId = (int) filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
                $warehouseId = (int) filter_input(INPUT_POST, 'warehouse_id', FILTER_VALIDATE_INT);
                $delta = (int) filter_input(INPUT_POST, 'delta', FILTER_VALIDATE_INT);
                $reason = trim((string) filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                // filter_input() with FILTER_VALIDATE_BOOLEAN returns
                // null (not false) when the field is entirely absent
                // from the POST body -- which an unchecked checkbox
                // always is. Coalescing to false here is required,
                // since InventoryServiceInterface::adjust()'s
                // $allowNegative parameter is a non-nullable bool under
                // strict_types=1 and would otherwise throw a TypeError
                // for the (extremely common) unchecked-checkbox case --
                // caught live during this domain's e2e verification.
                $allowNegative = filter_input(INPUT_POST, 'allow_negative', FILTER_VALIDATE_BOOLEAN) ?? false;

                if ($productId <= 0 || $warehouseId <= 0 || $delta === 0 || $reason === '') {
                    throw new InvalidArgumentException('Product, warehouse, a non-zero quantity change, and a reason are all required.');
                }

                $this->inventoryService->adjust($productId, $warehouseId, $delta, $reason, (int) currentUserId(), $allowNegative);

                $this->auditLogger->record('inventory', 'stock.adjusted', 'inventory_item', $productId, [], [
                    'warehouse_id' => $warehouseId,
                    'delta' => $delta,
                    'reason' => $reason,
                ]);

                setFlashMessage('Stock adjusted.');

                return Response::redirect('/admin/inventory?warehouse_id=' . $warehouseId);
            } catch (InsufficientStockException|InventoryItemNotFoundException|InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $product = $productId > 0 ? $this->productService->getProductById($productId) : null;
        $warehouses = $this->warehouseRepository->all();

        $html = View::render('pages/admin/inventory-adjust', [
            'appConfig' => $this->config->all(),
            'product' => $product,
            'productId' => $productId,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
