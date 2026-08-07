<?php

declare(strict_types=1);

namespace App\Domains\Warehouse\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Warehouse\Services\GoodsReceiptServiceInterface;
use App\Domains\Warehouse\Services\PickListServiceInterface;
use App\Domains\Warehouse\Services\ShipmentServiceInterface;
use App\Domains\Warehouse\Services\StockTransferServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Staff-only operational screens for the physical warehouse workflow
 * (docs/specs/07-warehouse.md §7/§13/§15): pick-list queue and detail
 * (pick/pack/ship), goods receiving, and stock transfers. No
 * customer-facing surface exists in this domain (§16).
 *
 * Defense in depth per the established convention: the Kernel's /admin/*
 * guard enforces account_kind=staff; each action additionally enforces
 * the specific operational permission (§11) -- pickers do not
 * automatically get receiving or transfer access. Every write is
 * audit-logged (§15): this is a physical-stock-custody domain.
 */
class AdminWarehouseController
{
    private const PER_PAGE = 25;

    /** @var PickListServiceInterface */
    private $pickListService;

    /** @var ShipmentServiceInterface */
    private $shipmentService;

    /** @var GoodsReceiptServiceInterface */
    private $goodsReceiptService;

    /** @var StockTransferServiceInterface */
    private $stockTransferService;

    /** @var WarehouseRepositoryInterface */
    private $warehouseRepository;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(
        PickListServiceInterface $pickListService,
        ShipmentServiceInterface $shipmentService,
        GoodsReceiptServiceInterface $goodsReceiptService,
        StockTransferServiceInterface $stockTransferService,
        WarehouseRepositoryInterface $warehouseRepository,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        Config $config
    ) {
        $this->pickListService = $pickListService;
        $this->shipmentService = $shipmentService;
        $this->goodsReceiptService = $goodsReceiptService;
        $this->stockTransferService = $stockTransferService;
        $this->warehouseRepository = $warehouseRepository;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /admin/warehouse/pick-lists -- the
     * dispatch queue (§13).
     */
    public function pickLists(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'warehouse.pick.manage')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $html = View::render('pages/admin/warehouse-pick-lists', [
            'appConfig' => $this->config->all(),
            'pickLists' => $this->pickListService->listAllForAdmin(self::PER_PAGE, $offset),
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($this->pickListService->countAllForAdmin() / self::PER_PAGE)),
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/warehouse/pick-list -- query-param
     * routed (?id=), same convention as every other admin detail screen.
     * POST sub-actions: mark_picked, mark_packed, create_shipment.
     */
    public function pickListDetail(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'warehouse.pick.manage')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';
        $pickListId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $action = trim((string) filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                if ($action === 'mark_picked') {
                    $itemId = (int) filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
                    if ($itemId <= 0) {
                        throw new InvalidArgumentException('A pick-list item is required.');
                    }

                    $this->pickListService->markPicked($itemId, (int) currentUserId());
                    $this->auditLogger->record('warehouse', 'pick.item_picked', 'pick_list', $pickListId, [], [
                        'item_id' => $itemId,
                        'actor_user_id' => (int) currentUserId(),
                    ]);
                    setFlashMessage('Item marked picked.');
                } elseif ($action === 'mark_packed') {
                    $this->pickListService->markPacked($pickListId, (int) currentUserId());
                    $this->auditLogger->record('warehouse', 'pick.list_packed', 'pick_list', $pickListId, [], [
                        'actor_user_id' => (int) currentUserId(),
                    ]);
                    setFlashMessage('Pick list packed.');
                } elseif ($action === 'create_shipment') {
                    // Shipment creation is a distinct operational role
                    // (§11), checked here in addition to the page-level
                    // pick permission.
                    if (!$this->userService->hasPermission($currentUser, 'warehouse.ship.manage')) {
                        throw new RuntimeException('You do not have permission to create shipments.');
                    }

                    $carrier = trim((string) filter_input(INPUT_POST, 'carrier', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
                    $tracking = trim((string) filter_input(INPUT_POST, 'tracking_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                    $shipment = $this->shipmentService->createFor($pickListId, $carrier, $tracking !== '' ? $tracking : null, (int) currentUserId());
                    $this->auditLogger->record('warehouse', 'ship.shipment_created', 'shipment', $shipment->id, [], [
                        'pick_list_id' => $pickListId,
                        'carrier' => $shipment->carrier,
                        'tracking_number' => $shipment->trackingNumber,
                    ]);
                    setFlashMessage(sprintf('Shipment %d created; order handed to %s.', $shipment->id, $shipment->carrier));
                } else {
                    throw new InvalidArgumentException('Unknown warehouse action.');
                }

                return Response::redirect('/admin/warehouse/pick-list?id=' . $pickListId);
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $pickList = $this->pickListService->findById($pickListId);
        if ($pickList === null) {
            return Response::notFound(View::render('pages/404', ['appConfig' => $this->config->all()]));
        }

        $html = View::render('pages/admin/warehouse-pick-list-detail', [
            'appConfig' => $this->config->all(),
            'pickList' => $pickList,
            'shipment' => $this->shipmentService->findByOrder($pickList->orderId),
            'canShip' => $this->userService->hasPermission($currentUser, 'warehouse.ship.manage'),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/warehouse/receiving -- goods
     * receipt entry (§13).
     */
    public function receiving(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'warehouse.receive.manage')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $warehouseId = (int) filter_input(INPUT_POST, 'warehouse_id', FILTER_VALIDATE_INT);
                $purchaseOrderIdInput = filter_input(INPUT_POST, 'purchase_order_id', FILTER_VALIDATE_INT);
                $purchaseOrderId = $purchaseOrderIdInput !== false && $purchaseOrderIdInput !== null && $purchaseOrderIdInput > 0
                    ? (int) $purchaseOrderIdInput
                    : null;
                $allowOverReceipt = filter_input(INPUT_POST, 'allow_over_receipt') !== null;

                $productIds = array_values(array_filter((array) filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?: []));
                $quantities = array_values(array_filter((array) filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?: []));

                if ($warehouseId <= 0) {
                    throw new InvalidArgumentException('A receiving warehouse is required.');
                }
                if (count($productIds) !== count($quantities) || empty($productIds)) {
                    throw new InvalidArgumentException('Each product needs a quantity.');
                }

                $items = [];
                foreach ($productIds as $index => $productId) {
                    $items[] = [
                        'product_id' => (int) $productId,
                        'quantity' => (int) $quantities[$index],
                    ];
                }

                $receipt = $this->goodsReceiptService->receive($purchaseOrderId, $warehouseId, $items, (int) currentUserId(), $allowOverReceipt);
                $this->auditLogger->record('warehouse', 'receive.goods_received', 'goods_receipt', $receipt->id, [], [
                    'warehouse_id' => $warehouseId,
                    'purchase_order_id' => $purchaseOrderId,
                    'line_count' => count($items),
                ]);
                setFlashMessage(sprintf('Goods receipt %d recorded and stock updated.', $receipt->id));

                return Response::redirect('/admin/warehouse/receiving');
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/admin/warehouse-receiving', [
            'appConfig' => $this->config->all(),
            'warehouses' => $this->warehouseRepository->all(),
            'receipts' => $this->goodsReceiptService->listAllForAdmin(10, 0),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/warehouse/transfers -- initiate
     * and complete inter-warehouse stock transfers (§13). The transfer
     * completion is the atomic-pair operation (§2/§14); failures are
     * rolled back by StockTransferService and surfaced here.
     */
    public function transfers(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'warehouse.transfer.manage')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $action = trim((string) filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

                if ($action === 'initiate') {
                    $fromWarehouseId = (int) filter_input(INPUT_POST, 'from_warehouse_id', FILTER_VALIDATE_INT);
                    $toWarehouseId = (int) filter_input(INPUT_POST, 'to_warehouse_id', FILTER_VALIDATE_INT);
                    $productIds = array_values(array_filter((array) filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?: []));
                    $quantities = array_values(array_filter((array) filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY) ?: []));

                    if ($fromWarehouseId <= 0 || $toWarehouseId <= 0) {
                        throw new InvalidArgumentException('Both warehouses are required.');
                    }
                    if (count($productIds) !== count($quantities) || empty($productIds)) {
                        throw new InvalidArgumentException('Each product needs a quantity.');
                    }

                    $items = [];
                    foreach ($productIds as $index => $productId) {
                        $items[] = [
                            'product_id' => (int) $productId,
                            'quantity' => (int) $quantities[$index],
                        ];
                    }

                    $transfer = $this->stockTransferService->initiate($fromWarehouseId, $toWarehouseId, $items, (int) currentUserId());
                    $this->auditLogger->record('warehouse', 'transfer.initiated', 'stock_transfer', $transfer->id, [], [
                        'from_warehouse_id' => $fromWarehouseId,
                        'to_warehouse_id' => $toWarehouseId,
                        'line_count' => count($items),
                    ]);
                    setFlashMessage(sprintf('Stock transfer %d initiated.', $transfer->id));
                } elseif ($action === 'complete') {
                    $transferId = (int) filter_input(INPUT_POST, 'transfer_id', FILTER_VALIDATE_INT);
                    if ($transferId <= 0) {
                        throw new InvalidArgumentException('A transfer id is required.');
                    }

                    $this->stockTransferService->complete($transferId);
                    $this->auditLogger->record('warehouse', 'transfer.completed', 'stock_transfer', $transferId, [], [
                        'actor_user_id' => (int) currentUserId(),
                    ]);
                    setFlashMessage(sprintf('Stock transfer %d completed -- both warehouse legs applied atomically.', $transferId));
                } else {
                    throw new InvalidArgumentException('Unknown transfer action.');
                }

                return Response::redirect('/admin/warehouse/transfers');
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/admin/warehouse-transfers', [
            'appConfig' => $this->config->all(),
            'warehouses' => $this->warehouseRepository->all(),
            'transfers' => $this->stockTransferService->listAllForAdmin(20, 0),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
