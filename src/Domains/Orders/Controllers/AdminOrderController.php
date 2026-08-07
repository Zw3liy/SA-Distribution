<?php
declare(strict_types=1);

namespace App\Domains\Orders\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domains\Orders\Exceptions\OrderNotFoundException;
use App\Domains\Orders\Services\OrderServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Order list/detail/status-transition screen for staff
 * (docs/specs/06-orders.md §7/§13). Defense in depth per the established
 * convention: the Kernel's /admin/* guard enforces account_kind=staff;
 * this controller additionally enforces orders.order.view /
 * orders.order.manage.
 */
class AdminOrderController
{
    private const PER_PAGE = 25;

    /** @var OrderServiceInterface */
    private $orderService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(
        OrderServiceInterface $orderService,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        Config $config
    ) {
        $this->orderService = $orderService;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET /admin/orders.
     */
    public function index(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'orders.order.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $html = View::render('pages/admin/orders', [
            'appConfig' => $this->config->all(),
            'orders' => $this->orderService->listAllForAdmin(self::PER_PAGE, $offset),
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($this->orderService->countAllForAdmin() / self::PER_PAGE)),
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/orders/view -- query-param
     * routed (?id=), since the Router only supports exact-path matching
     * (same convention as Customers'/Inventory's admin detail screens).
     */
    public function show(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'orders.order.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';
        $orderId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                if (!$this->userService->hasPermission($currentUser, 'orders.order.manage')) {
                    throw new RuntimeException('You do not have permission to manage orders.');
                }

                $orderId = (int) filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
                $toStatus = trim((string) filter_input(INPUT_POST, 'to_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
                $note = trim((string) filter_input(INPUT_POST, 'note', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '') ?: null;

                if ($orderId <= 0 || $toStatus === '') {
                    throw new InvalidArgumentException('An order and target status are required.');
                }

                $this->orderService->transition($orderId, $toStatus, (int) currentUserId(), $note);

                // Every status transition is both an OrderStatusHistory
                // row (already recorded by OrderService, the business
                // record) and an audit log call when staff-initiated
                // (docs/specs/06-orders.md §15, the compliance record).
                $this->auditLogger->record('orders', 'order.status_changed', 'order', $orderId, [], [
                    'to_status' => $toStatus,
                    'note' => $note,
                ]);

                setFlashMessage('Order status updated.');

                return Response::redirect('/admin/orders/view?id=' . $orderId);
            } catch (InvalidOrderTransitionException|OrderNotFoundException|InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $order = $this->orderService->findById($orderId);
        if ($order === null) {
            return Response::notFound(View::render('pages/404', ['appConfig' => $this->config->all()]));
        }

        $html = View::render('pages/admin/order-detail', [
            'appConfig' => $this->config->all(),
            'order' => $order,
            'history' => $this->orderService->statusHistoryFor($order->id),
            'canManage' => $this->userService->hasPermission($currentUser, 'orders.order.manage'),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
