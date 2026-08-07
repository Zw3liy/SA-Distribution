<?php
declare(strict_types=1);

namespace App\Domains\Orders\Controllers;

use App\Config\Config;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Services\AddressServiceInterface;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Inventory\Exceptions\InsufficientStockException;
use App\Domains\Orders\Exceptions\InvalidAddressException;
use App\Domains\Orders\Services\CartServiceInterface;
use App\Domains\Orders\Services\CheckoutServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;
use RuntimeException;
use Throwable;

/**
 * Orchestrates CheckoutServiceInterface::checkout() -- never touches
 * Inventory/Finance/Customers repositories directly, only their service
 * interfaces (docs/specs/06-orders.md §7). Depends on four other
 * domains' service interfaces (Identity, Customers x2, plus its own
 * Orders-domain services) -- Orders has the most inbound/outbound
 * cross-domain dependencies of any domain per the Phase 4 blueprint,
 * and this controller is where that's most visible.
 */
class CheckoutController
{
    /** @var CartServiceInterface */
    private $cartService;

    /** @var CheckoutServiceInterface */
    private $checkoutService;

    /** @var UserServiceInterface */
    private $userService;

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var AddressServiceInterface */
    private $addressService;

    /** @var Config */
    private $config;

    public function __construct(
        CartServiceInterface $cartService,
        CheckoutServiceInterface $checkoutService,
        UserServiceInterface $userService,
        CustomerServiceInterface $customerService,
        AddressServiceInterface $addressService,
        Config $config
    ) {
        $this->cartService = $cartService;
        $this->checkoutService = $checkoutService;
        $this->userService = $userService;
        $this->customerService = $customerService;
        $this->addressService = $addressService;
        $this->config = $config;
    }

    /**
     * Route action for GET/POST /checkout.php -- address selection,
     * review, place order (docs/specs/06-orders.md §13).
     */
    public function checkout(Request $request): Response
    {
        if (!isAuthenticated()) {
            return Response::redirect('/login.php');
        }

        $userId = (int) currentUserId();
        $sessionId = session_id();
        $error = '';

        $user = $this->userService->getUserById($userId);
        if ($user === null) {
            return Response::redirect('/login.php');
        }

        // Resolves (or lazily creates) the Customer for the current
        // user -- the same defensive pattern already established in
        // Customers' AccountController::resolveCustomer(), duplicated
        // rather than reused across the controller boundary (both call
        // the same public CustomerServiceInterface contract, which is
        // the correct level of sharing between controllers in different
        // domains per docs/specs/00-index.md).
        $customer = $this->customerService->findForUser($user);
        if ($customer === null) {
            $customer = $this->customerService->createForUser($user, ['account_type' => 'b2c']);
        }

        if ($request->method() === 'POST') {
            try {
                $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
                if (!verify_csrf_token($token)) {
                    throw new RuntimeException('Invalid CSRF token.');
                }

                $shippingAddressId = (int) filter_input(INPUT_POST, 'shipping_address_id', FILTER_VALIDATE_INT);
                $billingAddressId = (int) filter_input(INPUT_POST, 'billing_address_id', FILTER_VALIDATE_INT);

                if ($shippingAddressId <= 0 || $billingAddressId <= 0) {
                    throw new InvalidAddressException('Please select a shipping and billing address.');
                }

                $order = $this->checkoutService->checkout($sessionId, $customer->id, $userId, $shippingAddressId, $billingAddressId);

                setFlashMessage(sprintf('Order %s placed successfully.', $order->orderNumber));

                return Response::redirect('/account-orders.php');
            } catch (InsufficientStockException|InvalidAddressException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $cartItems = $this->cartService->getCartItems($sessionId);
        $summary = $this->cartService->getCartSummary($cartItems);
        $addresses = $this->addressService->listFor($customer);

        $html = View::render('pages/checkout', [
            'appConfig' => $this->config->all(),
            'cartItems' => $cartItems,
            'summary' => $summary,
            'addresses' => $addresses,
            'error' => $error,
            'flashMessage' => getFlashMessage(),
        ]);

        return Response::html($html);
    }
}
