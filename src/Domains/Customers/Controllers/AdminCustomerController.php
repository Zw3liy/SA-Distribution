<?php
declare(strict_types=1);

namespace App\Domains\Customers\Controllers;

use App\Config\Config;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Customers\Exceptions\CustomerNotFoundException;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Staff-facing customer list/detail, including the B2B account
 * hierarchy view (docs/specs/04-customers.md §13). Defense in depth per
 * the established convention: the Kernel's /admin/* guard enforces
 * account_kind=staff; this controller additionally enforces
 * customers.account.view / customers.b2b.manage.
 */
class AdminCustomerController
{
    private const PER_PAGE = 25;

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var AuditLoggerInterface */
    private $auditLogger;

    /** @var Config */
    private $config;

    public function __construct(
        CustomerServiceInterface $customerService,
        UserServiceInterface $userService,
        AuditLoggerInterface $auditLogger,
        Config $config
    ) {
        $this->customerService = $customerService;
        $this->userService = $userService;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Route action for GET /admin/customers.
     */
    public function index(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'customers.account.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $offset = ($page - 1) * self::PER_PAGE;

        $html = View::render('pages/admin/customers', [
            'appConfig' => $this->config->all(),
            'customers' => $this->customerService->listAll(self::PER_PAGE, $offset),
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($this->customerService->countAll() / self::PER_PAGE)),
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /admin/customers/{id} equivalent --
     * routed as /admin/customers/view with a customer_id query/post
     * param, since the Router only supports exact-path matching with no
     * path parameters (docs/specs/00-index.md notes this Router
     * limitation).
     */
    public function show(Request $request): Response
    {
        $currentUser = $this->userService->getUserById((int) currentUserId());
        if ($currentUser === null || !$this->userService->hasPermission($currentUser, 'customers.account.view')) {
            return Response::forbidden(View::render('pages/403', ['appConfig' => $this->config->all()]));
        }

        $error = '';
        $customerId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                if (!$this->userService->hasPermission($currentUser, 'customers.b2b.manage')) {
                    throw new RuntimeException('You do not have permission to manage B2B buyers.');
                }

                $customerId = (int) filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
                $buyerEmail = trim((string) filter_input(INPUT_POST, 'buyer_email', FILTER_VALIDATE_EMAIL) ?: '');

                $customer = $this->customerService->getById($customerId);
                if ($customer === null) {
                    throw new CustomerNotFoundException(sprintf('Customer #%d not found.', $customerId));
                }

                $buyer = $this->userService->getUserByEmail($buyerEmail);
                if ($buyer === null) {
                    throw new InvalidArgumentException('No user found with that email.');
                }

                $this->customerService->addBuyer($customer, $buyer);

                $this->auditLogger->record('customers', 'buyer.added', 'customer', $customerId, [], [
                    'buyer_email' => $buyerEmail,
                ]);

                setFlashMessage('Buyer added to account.');

                return Response::redirect('/admin/customers/view?id=' . $customerId);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $customer = $this->customerService->getById($customerId);
        if ($customer === null) {
            return Response::notFound(View::render('pages/404', ['appConfig' => $this->config->all()]));
        }

        $html = View::render('pages/admin/customer-detail', [
            'appConfig' => $this->config->all(),
            'customer' => $customer,
            'buyers' => $this->customerService->buyersFor($customer),
            'canManageBuyers' => $this->userService->hasPermission($currentUser, 'customers.b2b.manage'),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
