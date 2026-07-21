<?php
declare(strict_types=1);

namespace App\Domains\Customers\Controllers;

use App\Config\Config;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Services\AddressServiceInterface;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * "My Account" dashboard/edit -- legitimately spans two domains'
 * services (Customers for company/address data, Identity for
 * login/security data), which is fine per docs/specs/00-index.md
 * conventions: controllers may depend on multiple domains' services; it
 * is *services* that must not cross-call each other's repositories
 * directly.
 */
class AccountController
{
    /** @var UserServiceInterface */
    private $userService;

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var AddressServiceInterface */
    private $addressService;

    /** @var Config */
    private $config;

    public function __construct(
        UserServiceInterface $userService,
        CustomerServiceInterface $customerService,
        AddressServiceInterface $addressService,
        Config $config
    ) {
        $this->userService = $userService;
        $this->customerService = $customerService;
        $this->addressService = $addressService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged.
     */
    public function getDashboardData(int $userId): array
    {
        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            throw new RuntimeException('User account not found.');
        }

        return [
            'user' => $user,
        ];
    }

    /**
     * Original business logic, unchanged. company_name is deliberately
     * NOT part of this write path any more -- it now goes through the
     * linked Customer record instead (docs/specs/04-customers.md §2).
     * users.company_name remains a read-only, deprecated compatibility
     * field for one release rather than being cut over immediately.
     */
    public function updateProfile(int $userId): bool
    {
        $firstName = trim((string) filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $lastName = trim((string) filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $phone = trim((string) filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW) ?: '');
        $passwordConfirm = trim((string) filter_input(INPUT_POST, 'password_confirm', FILTER_UNSAFE_RAW) ?: '');

        if ($firstName === '' || $lastName === '' || $phone === '') {
            throw new InvalidArgumentException('First name, last name, and phone are required.');
        }

        if ($password !== '' && $password !== $passwordConfirm) {
            throw new InvalidArgumentException('Password confirmation does not match.');
        }

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'notifications_marketing' => (int) filter_input(INPUT_POST, 'notifications_marketing', FILTER_VALIDATE_BOOLEAN),
            'notifications_updates' => (int) filter_input(INPUT_POST, 'notifications_updates', FILTER_VALIDATE_BOOLEAN),
        ];

        if ($password !== '') {
            $payload['password'] = $password;
        }

        return $this->userService->updateProfile($userId, $payload);
    }

    /**
     * Resolves (or lazily creates) the Customer record for a given
     * user. A record should already exist for every self-registered
     * user via AuthController's retrofit, but pre-existing users from
     * before this domain's backfill migration are handled defensively
     * rather than crashing the account page.
     */
    private function resolveCustomer($user): Customer
    {
        $customer = $this->customerService->findForUser($user);
        if ($customer !== null) {
            return $customer;
        }

        return $this->customerService->createForUser($user, ['account_type' => 'b2c']);
    }

    /**
     * Route action for GET /account-dashboard.php.
     */
    public function dashboard(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $userId = currentUserId();
        $userData = $this->getDashboardData($userId);

        $html = View::render('pages/account-dashboard', [
            'appConfig' => $this->config->all(),
            'user' => $userData['user'],
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /account-edit.php. Handles the existing
     * profile-update flow plus two new address actions
     * (docs/specs/04-customers.md §5/§7), distinguished by the
     * form_action field the same way Administration's StaffController
     * distinguishes its actions.
     */
    public function edit(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $userId = currentUserId();
        $user = $this->userService->getUserById($userId);
        $error = '';

        if ($user === null) {
            return Response::redirect('/login.php');
        }

        $customer = $this->resolveCustomer($user);

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $action = trim((string) filter_input(INPUT_POST, 'form_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'update_profile');

                if ($action === 'update_profile') {
                    $this->updateProfile($userId);
                    setFlashMessage('Your profile has been updated.');
                } elseif ($action === 'add_address') {
                    $this->addressService->add($customer, [
                        'label' => trim((string) filter_input(INPUT_POST, 'label', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'address_line_1' => trim((string) filter_input(INPUT_POST, 'address_line_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'address_line_2' => trim((string) filter_input(INPUT_POST, 'address_line_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '') ?: null,
                        'city' => trim((string) filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'region' => trim((string) filter_input(INPUT_POST, 'region', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'postal_code' => trim((string) filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'country' => trim((string) filter_input(INPUT_POST, 'country', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '') ?: 'South Africa',
                        'type' => trim((string) filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: ''),
                        'is_default' => filter_input(INPUT_POST, 'is_default', FILTER_VALIDATE_BOOLEAN),
                    ]);
                    setFlashMessage('Address added.');
                } elseif ($action === 'set_default_address') {
                    $addressId = (int) filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
                    $type = trim((string) filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
                    $this->addressService->setDefault($addressId, $type);
                    setFlashMessage('Default address updated.');
                } else {
                    throw new RuntimeException('Unknown account action.');
                }

                return Response::redirect('/account-edit.php');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $html = View::render('pages/account-edit', [
            'appConfig' => $this->config->all(),
            'user' => $user,
            'addresses' => $this->addressService->listFor($customer),
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
