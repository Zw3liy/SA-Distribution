<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\Config;
use App\Container\Container;
use App\Controllers\HomeController;
use App\Controllers\QuoteController;
use App\Database\Database;
use App\Domains\Administration\Controllers\AdminDashboardController;
use App\Domains\Administration\Controllers\AuditLogController;
use App\Domains\Administration\Controllers\FeatureFlagController;
use App\Domains\Administration\Controllers\StaffController;
use App\Domains\Administration\Controllers\SystemSettingController;
use App\Domains\Administration\Repositories\AuditLogRepository;
use App\Domains\Administration\Repositories\AuditLogRepositoryInterface;
use App\Domains\Administration\Repositories\FeatureFlagRepository;
use App\Domains\Administration\Repositories\FeatureFlagRepositoryInterface;
use App\Domains\Administration\Repositories\SystemSettingRepository;
use App\Domains\Administration\Repositories\SystemSettingRepositoryInterface;
use App\Domains\Administration\Services\AuditLogger;
use App\Domains\Administration\Services\AuditLoggerInterface;
use App\Domains\Administration\Services\FeatureFlagService;
use App\Domains\Administration\Services\FeatureFlagServiceInterface;
use App\Domains\Administration\Services\SettingsService;
use App\Domains\Administration\Services\SettingsServiceInterface;
use App\Domains\Catalog\Controllers\AdminProductController;
use App\Domains\Catalog\Controllers\ProductController;
use App\Domains\Catalog\Repositories\ProductRepository;
use App\Domains\Catalog\Repositories\ProductRepositoryInterface;
use App\Domains\Catalog\Repositories\TaxClassRepository;
use App\Domains\Catalog\Repositories\TaxClassRepositoryInterface;
use App\Domains\Catalog\Services\ProductService;
use App\Domains\Catalog\Services\ProductServiceInterface;
use App\Domains\Catalog\Services\TaxClassService;
use App\Domains\Catalog\Services\TaxClassServiceInterface;
use App\Domains\Customers\Controllers\AccountController;
use App\Domains\Customers\Controllers\AdminCustomerController;
use App\Domains\Customers\Controllers\WishlistController;
use App\Domains\Customers\Repositories\AddressRepository;
use App\Domains\Customers\Repositories\AddressRepositoryInterface;
use App\Domains\Customers\Repositories\CustomerRepository;
use App\Domains\Customers\Repositories\CustomerRepositoryInterface;
use App\Domains\Customers\Services\AddressService;
use App\Domains\Customers\Services\AddressServiceInterface;
use App\Domains\Customers\Services\CustomerService;
use App\Domains\Customers\Services\CustomerServiceInterface;
use App\Domains\Identity\Controllers\AuthController;
use App\Domains\Identity\Repositories\ApiCredentialRepository;
use App\Domains\Identity\Repositories\ApiCredentialRepositoryInterface;
use App\Domains\Identity\Repositories\UserRepository;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use App\Domains\Identity\Services\ApiCredentialService;
use App\Domains\Identity\Services\ApiCredentialServiceInterface;
use App\Domains\Identity\Services\AuthService;
use App\Domains\Identity\Services\AuthServiceInterface;
use App\Domains\Identity\Services\UserService;
use App\Domains\Identity\Services\UserServiceInterface;
use App\Domains\Inventory\Controllers\AdminInventoryController;
use App\Domains\Inventory\Repositories\InventoryItemRepository;
use App\Domains\Inventory\Repositories\InventoryItemRepositoryInterface;
use App\Domains\Inventory\Repositories\StockMovementRepository;
use App\Domains\Inventory\Repositories\StockMovementRepositoryInterface;
use App\Domains\Inventory\Repositories\StockReservationRepository;
use App\Domains\Inventory\Repositories\StockReservationRepositoryInterface;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use App\Domains\Inventory\Repositories\WarehouseRepositoryInterface;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Inventory\Services\InventoryServiceInterface;
use App\Domains\Orders\Controllers\AdminOrderController;
use App\Domains\Orders\Controllers\CartController;
use App\Domains\Orders\Controllers\CheckoutController;
use App\Domains\Orders\Repositories\CartRepository;
use App\Domains\Orders\Repositories\CartRepositoryInterface;
use App\Domains\Orders\Repositories\OrderRepository;
use App\Domains\Orders\Repositories\OrderRepositoryInterface;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepository;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepositoryInterface;
use App\Domains\Orders\Repositories\PaymentRepository;
use App\Domains\Orders\Repositories\PaymentRepositoryInterface;
use App\Domains\Orders\Services\CartService;
use App\Domains\Orders\Services\CartServiceInterface;
use App\Domains\Orders\Services\CheckoutService;
use App\Domains\Orders\Services\CheckoutServiceInterface;
use App\Domains\Orders\Services\OrderService;
use App\Domains\Orders\Services\OrderServiceInterface;
use App\Domains\Orders\Services\TaxCalculator;
use App\Domains\Orders\Services\TaxCalculatorInterface;
use App\Logging\Logger;
use App\Repositories\QuoteRepository;
use App\Services\QuoteService;
use App\Support\View;
use ErrorException;
use PDO;
use Throwable;

/**
 * Front-controller kernel. Boots the session, configuration, dependency
 * container and routes exactly once per request, then dispatches to a
 * controller action. This is what public/index.php delegates to instead
 * of every page doing its own require_once chain and manual wiring.
 */
final class Kernel
{
    /** @var string */
    private $basePath;

    /** @var Container */
    private $container;

    /** @var Router */
    private $router;

    /** @var Logger */
    private $logger;

    /** @var Config */
    private $config;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->container = new Container();
        $this->router = new Router();
        $this->logger = new Logger($this->basePath . '/storage/logs/app.log');

        $this->registerErrorHandling();
        $this->config = new Config($this->basePath . '/config/app.php');
        $this->bootSession();

        View::setBasePath($this->basePath . '/views');

        $this->registerBindings();
        $this->registerRoutes();
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->match($request->method(), $request->path());

            if ($route === null) {
                return $this->notFoundResponse();
            }

            if ($this->isAdminRoute($request->path()) && !$this->passesAdminGuard()) {
                return $this->forbiddenResponse();
            }

            [$controllerClass, $action] = $route;
            $controller = $this->container->get($controllerClass);

            return $controller->$action($request);
        } catch (Throwable $exception) {
            $this->logger->exception($exception);

            return $this->errorResponse($exception, $request);
        }
    }

    /**
     * Staff-only route guard for the /admin/* namespace, per
     * docs/specs/02-administration.md §16: "defense in depth --
     * account_kind = 'staff' AND the specific permission for the
     * screen, not either/or." The Router only does exact-path matching
     * with no concept of route groups or middleware, so this check runs
     * here, after a route is matched but before the controller is
     * invoked -- the one place every request funnels through.
     *
     * The account_kind check is the coarse gate (defense layer 1);
     * per-screen permission checks (defense layer 2) are enforced
     * inside each admin controller action via
     * UserServiceInterface::hasPermission() -- Catalog's
     * AdminProductController, Customers' AdminCustomerController,
     * Inventory's AdminInventoryController, and Orders'
     * AdminOrderController all enforce this second layer, now that the
     * permission catalog has been seeded (docs/specs/03-catalog.md §19,
     * docs/specs/04-customers.md §19, docs/specs/05-inventory.md §19,
     * docs/specs/06-orders.md §19 migrations).
     */
    private function isAdminRoute(string $path): bool
    {
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function passesAdminGuard(): bool
    {
        return isAuthenticated() && isStaffAccount();
    }

    /**
     * Non-fatal PHP errors (warnings/notices/deprecations) are logged
     * but otherwise handled exactly as PHP would normally handle them
     * (returning false keeps the built-in behavior) — this is additive
     * observability, not a change in runtime behavior. A last-resort
     * exception handler is also registered as a safety net for anything
     * that escapes every try/catch in the app, replacing PHP's raw
     * error output with a logged entry and a clean error page.
     */
    private function registerErrorHandling(): void
    {
        set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            $this->logger->warning($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);

            return false;
        });

        set_exception_handler(function (Throwable $exception): void {
            $this->logger->exception($exception);
            $this->errorResponse($exception, Request::fromGlobals())->send();
        });
    }

    private function bootSession(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', '1');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: no-referrer-when-downgrade');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            header('Content-Security-Policy: ' . $this->config->get('security.content_security_policy'));
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }

        if (!isset($_SESSION['quote_requests'])) {
            $_SESSION['quote_requests'] = [];
        }
    }

    private function registerBindings(): void
    {
        $this->container->set('config', function () {
            return $this->config;
        });

        $this->container->set(Logger::class, function () {
            return $this->logger;
        });

        $this->container->set(PDO::class, function () {
            return (new Database($this->config->get('db')))->getConnection();
        });

        // Identity domain — bound by interface, per docs/specs/01-identity.md §5/§6.
        $this->container->set(UserRepositoryInterface::class, function (Container $c) {
            return new UserRepository($c->get(PDO::class));
        });
        $this->container->set(ApiCredentialRepositoryInterface::class, function (Container $c) {
            return new ApiCredentialRepository($c->get(PDO::class));
        });
        $this->container->set(AuthServiceInterface::class, function (Container $c) {
            return new AuthService($c->get(UserRepositoryInterface::class));
        });
        $this->container->set(UserServiceInterface::class, function (Container $c) {
            return new UserService($c->get(UserRepositoryInterface::class));
        });
        $this->container->set(ApiCredentialServiceInterface::class, function (Container $c) {
            return new ApiCredentialService($c->get(ApiCredentialRepositoryInterface::class), $c->get(UserRepositoryInterface::class));
        });
        // AuthController now also depends on Customers' CustomerServiceInterface
        // (docs/specs/04-customers.md §10 -- auto-creates a b2c Customer on
        // self-registration, wired as a direct synchronous call since no
        // event bus exists in this platform) and UserServiceInterface (to
        // reload the freshly-registered User so it can be passed through).
        $this->container->set(AuthController::class, function (Container $c) {
            return new AuthController($c->get(AuthServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get(CustomerServiceInterface::class), $c->get(UserServiceInterface::class), $c->get('config'));
        });

        // Administration domain — bound by interface, per
        // docs/specs/02-administration.md §5/§6. AuditLoggerInterface is
        // the one interface every other domain is expected to depend on.
        $this->container->set(AuditLogRepositoryInterface::class, function (Container $c) {
            return new AuditLogRepository($c->get(PDO::class));
        });
        $this->container->set(SystemSettingRepositoryInterface::class, function (Container $c) {
            return new SystemSettingRepository($c->get(PDO::class));
        });
        $this->container->set(FeatureFlagRepositoryInterface::class, function (Container $c) {
            return new FeatureFlagRepository($c->get(PDO::class));
        });
        $this->container->set(AuditLoggerInterface::class, function (Container $c) {
            return new AuditLogger($c->get(AuditLogRepositoryInterface::class), $c->get(Logger::class));
        });
        $this->container->set(SettingsServiceInterface::class, function (Container $c) {
            return new SettingsService($c->get(SystemSettingRepositoryInterface::class));
        });
        $this->container->set(FeatureFlagServiceInterface::class, function (Container $c) {
            return new FeatureFlagService($c->get(FeatureFlagRepositoryInterface::class));
        });
        $this->container->set(AdminDashboardController::class, function (Container $c) {
            return new AdminDashboardController($c->get('config'));
        });
        $this->container->set(AuditLogController::class, function (Container $c) {
            return new AuditLogController($c->get(AuditLogRepositoryInterface::class), $c->get('config'));
        });
        $this->container->set(SystemSettingController::class, function (Container $c) {
            return new SystemSettingController($c->get(SettingsServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get('config'));
        });
        $this->container->set(FeatureFlagController::class, function (Container $c) {
            return new FeatureFlagController($c->get(FeatureFlagServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get('config'));
        });
        $this->container->set(StaffController::class, function (Container $c) {
            return new StaffController($c->get(AuthServiceInterface::class), $c->get(UserServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get('config'));
        });

        // Catalog domain — bound by interface, per docs/specs/03-catalog.md §5/§6.
        $this->container->set(ProductRepositoryInterface::class, function (Container $c) {
            return new ProductRepository($c->get(PDO::class));
        });
        $this->container->set(TaxClassRepositoryInterface::class, function (Container $c) {
            return new TaxClassRepository($c->get(PDO::class));
        });
        $this->container->set(ProductServiceInterface::class, function (Container $c) {
            return new ProductService($c->get(ProductRepositoryInterface::class));
        });
        $this->container->set(TaxClassServiceInterface::class, function (Container $c) {
            return new TaxClassService($c->get(TaxClassRepositoryInterface::class));
        });
        $this->container->set(ProductController::class, function (Container $c) {
            return new ProductController($c->get(ProductServiceInterface::class), $c->get('config'));
        });
        // AdminProductController now also depends on Inventory's
        // InventoryServiceInterface (docs/specs/05-inventory.md §10 --
        // consumes Catalog\Events\ProductCreated as a direct call).
        $this->container->set(AdminProductController::class, function (Container $c) {
            return new AdminProductController($c->get(ProductServiceInterface::class), $c->get(UserServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get(InventoryServiceInterface::class), $c->get('config'));
        });

        // Customers domain — bound by interface, per docs/specs/04-customers.md §5/§6.
        $this->container->set(CustomerRepositoryInterface::class, function (Container $c) {
            return new CustomerRepository($c->get(PDO::class));
        });
        $this->container->set(AddressRepositoryInterface::class, function (Container $c) {
            return new AddressRepository($c->get(PDO::class));
        });
        $this->container->set(CustomerServiceInterface::class, function (Container $c) {
            return new CustomerService($c->get(CustomerRepositoryInterface::class));
        });
        $this->container->set(AddressServiceInterface::class, function (Container $c) {
            return new AddressService($c->get(AddressRepositoryInterface::class));
        });
        // AccountController now also depends on Orders' OrderServiceInterface
        // (docs/specs/06-orders.md §13 -- the customer order-history page
        // belongs to Customers' account area, not to a separate Orders
        // controller; see AccountController::orders()).
        $this->container->set(AccountController::class, function (Container $c) {
            return new AccountController($c->get(UserServiceInterface::class), $c->get(CustomerServiceInterface::class), $c->get(AddressServiceInterface::class), $c->get(OrderServiceInterface::class), $c->get('config'));
        });
        $this->container->set(WishlistController::class, function (Container $c) {
            return new WishlistController($c->get('config'));
        });
        $this->container->set(AdminCustomerController::class, function (Container $c) {
            return new AdminCustomerController($c->get(CustomerServiceInterface::class), $c->get(UserServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get('config'));
        });

        // Inventory domain — bound by interface, per docs/specs/05-inventory.md §5/§6.
        // InventoryService depends on Catalog's ProductRepositoryInterface
        // directly (not a new Inventory-owned abstraction) solely to keep
        // the products.stock compatibility mirror in sync (§2) -- the same
        // precedent as Orders' CartService/CheckoutService depending
        // directly on Catalog's/Inventory's repositories below.
        $this->container->set(WarehouseRepositoryInterface::class, function (Container $c) {
            return new WarehouseRepository($c->get(PDO::class));
        });
        $this->container->set(InventoryItemRepositoryInterface::class, function (Container $c) {
            return new InventoryItemRepository($c->get(PDO::class));
        });
        $this->container->set(StockReservationRepositoryInterface::class, function (Container $c) {
            return new StockReservationRepository($c->get(PDO::class));
        });
        $this->container->set(StockMovementRepositoryInterface::class, function (Container $c) {
            return new StockMovementRepository($c->get(PDO::class));
        });
        $this->container->set(InventoryServiceInterface::class, function (Container $c) {
            return new InventoryService(
                $c->get(InventoryItemRepositoryInterface::class),
                $c->get(StockReservationRepositoryInterface::class),
                $c->get(StockMovementRepositoryInterface::class),
                $c->get(WarehouseRepositoryInterface::class),
                $c->get(ProductRepositoryInterface::class),
                $c->get(Logger::class)
            );
        });
        $this->container->set(AdminInventoryController::class, function (Container $c) {
            return new AdminInventoryController(
                $c->get(InventoryServiceInterface::class),
                $c->get(InventoryItemRepositoryInterface::class),
                $c->get(WarehouseRepositoryInterface::class),
                $c->get(ProductServiceInterface::class),
                $c->get(UserServiceInterface::class),
                $c->get(AuditLoggerInterface::class),
                $c->get('config')
            );
        });

        // Orders domain — bound by interface, per docs/specs/06-orders.md
        // §5/§6. CartRepository/CartService/CartController are moved
        // (namespace-only) from their Phase 3 locations per §19.
        // CartService depends on Catalog's ProductRepositoryInterface
        // directly, the same established precedent as Inventory's
        // InventoryService above. CheckoutService depends on Inventory's
        // WarehouseRepositoryInterface directly (the one deliberate
        // repository-level cross-domain exception in this domain,
        // documented on CheckoutService itself) for the single
        // default-warehouse lookup.
        $this->container->set(CartRepositoryInterface::class, function (Container $c) {
            return new CartRepository($c->get(PDO::class));
        });
        $this->container->set(CartServiceInterface::class, function (Container $c) {
            return new CartService($c->get(ProductRepositoryInterface::class), $c->get(CartRepositoryInterface::class));
        });
        $this->container->set(OrderRepositoryInterface::class, function (Container $c) {
            return new OrderRepository($c->get(PDO::class));
        });
        $this->container->set(OrderStatusHistoryRepositoryInterface::class, function (Container $c) {
            return new OrderStatusHistoryRepository($c->get(PDO::class));
        });
        $this->container->set(PaymentRepositoryInterface::class, function (Container $c) {
            return new PaymentRepository($c->get(PDO::class));
        });
        $this->container->set(TaxCalculatorInterface::class, function (Container $c) {
            return new TaxCalculator();
        });
        $this->container->set(OrderServiceInterface::class, function (Container $c) {
            return new OrderService(
                $c->get(OrderRepositoryInterface::class),
                $c->get(OrderStatusHistoryRepositoryInterface::class),
                $c->get(InventoryServiceInterface::class)
            );
        });
        $this->container->set(CheckoutServiceInterface::class, function (Container $c) {
            return new CheckoutService(
                $c->get(CartServiceInterface::class),
                $c->get(CustomerServiceInterface::class),
                $c->get(AddressServiceInterface::class),
                $c->get(InventoryServiceInterface::class),
                $c->get(WarehouseRepositoryInterface::class),
                $c->get(TaxCalculatorInterface::class),
                $c->get(OrderRepositoryInterface::class),
                $c->get(PaymentRepositoryInterface::class)
            );
        });
        $this->container->set(CartController::class, function (Container $c) {
            return new CartController($c->get(CartServiceInterface::class), $c->get(ProductServiceInterface::class), $c->get('config'));
        });
        $this->container->set(CheckoutController::class, function (Container $c) {
            return new CheckoutController(
                $c->get(CartServiceInterface::class),
                $c->get(CheckoutServiceInterface::class),
                $c->get(UserServiceInterface::class),
                $c->get(CustomerServiceInterface::class),
                $c->get(AddressServiceInterface::class),
                $c->get('config')
            );
        });
        $this->container->set(AdminOrderController::class, function (Container $c) {
            return new AdminOrderController($c->get(OrderServiceInterface::class), $c->get(UserServiceInterface::class), $c->get(AuditLoggerInterface::class), $c->get('config'));
        });

        // Not-yet-migrated domains — unchanged from Phase 3, still bound
        // by concrete class. QuoteController now depends on Orders'
        // CartServiceInterface (namespace-only change, same contract).
        $this->container->set(QuoteRepository::class, function (Container $c) {
            return new QuoteRepository($c->get(PDO::class));
        });
        $this->container->set(QuoteService::class, function (Container $c) {
            return new QuoteService($c->get(QuoteRepository::class));
        });

        $this->container->set(HomeController::class, function (Container $c) {
            return new HomeController($c->get('config'));
        });
        $this->container->set(QuoteController::class, function (Container $c) {
            return new QuoteController($c->get(QuoteService::class), $c->get(CartServiceInterface::class), $c->get('config'));
        });
    }

    private function registerRoutes(): void
    {
        $this->router->get('/', HomeController::class, 'index');
        $this->router->get('/index.php', HomeController::class, 'index');

        $this->router->get('/products.php', ProductController::class, 'index');
        $this->router->get('/product-details.php', ProductController::class, 'show');

        $this->router->any('/login.php', AuthController::class, 'login');
        $this->router->any('/register.php', AuthController::class, 'register');
        $this->router->get('/logout.php', AuthController::class, 'logout');

        $this->router->any('/account-dashboard.php', AccountController::class, 'dashboard');
        $this->router->any('/account-edit.php', AccountController::class, 'edit');
        $this->router->any('/account-orders.php', AccountController::class, 'orders');

        $this->router->any('/cart.php', CartController::class, 'page');
        $this->router->any('/cart-api.php', CartController::class, 'api');

        $this->router->any('/checkout.php', CheckoutController::class, 'checkout');

        $this->router->any('/wishlist.php', WishlistController::class, 'page');

        $this->router->any('/quote-request.php', QuoteController::class, 'sessionRequest');
        $this->router->post('/quote-api.php', QuoteController::class, 'api');

        // Administration domain — staff-only, guarded in handle() above.
        $this->router->get('/admin', AdminDashboardController::class, 'index');
        $this->router->any('/admin/staff', StaffController::class, 'index');
        $this->router->any('/admin/settings', SystemSettingController::class, 'index');
        $this->router->any('/admin/feature-flags', FeatureFlagController::class, 'index');
        $this->router->get('/admin/audit-log', AuditLogController::class, 'index');

        // Catalog domain — staff-only, guarded in handle() above.
        $this->router->any('/admin/catalog/products', AdminProductController::class, 'index');

        // Customers domain — staff-only, guarded in handle() above. The
        // /admin/customers/view?id= query-param routing (rather than a
        // path parameter) is because Router only supports exact-path
        // matching -- documented on AdminCustomerController::show().
        $this->router->any('/admin/customers', AdminCustomerController::class, 'index');
        $this->router->any('/admin/customers/view', AdminCustomerController::class, 'show');

        // Inventory domain — staff-only, guarded in handle() above. Same
        // query-param routing convention as Customers.
        $this->router->any('/admin/inventory', AdminInventoryController::class, 'index');
        $this->router->any('/admin/inventory/adjust', AdminInventoryController::class, 'adjust');

        // Orders domain — staff-only, guarded in handle() above. Same
        // query-param routing convention as Customers/Inventory.
        $this->router->any('/admin/orders', AdminOrderController::class, 'index');
        $this->router->any('/admin/orders/view', AdminOrderController::class, 'show');
    }

    private function notFoundResponse(): Response
    {
        $html = View::render('pages/404', ['appConfig' => $this->config->all()]);

        return Response::notFound($html);
    }

    private function forbiddenResponse(): Response
    {
        $html = View::render('pages/403', ['appConfig' => $this->config->all()]);

        return Response::forbidden($html);
    }

    private function errorResponse(Throwable $exception, Request $request): Response
    {
        if (str_ends_with($request->path(), '-api.php')) {
            return Response::json([
                'error' => 'Unexpected server error.',
            ], 500);
        }

        $html = View::render('pages/error', ['appConfig' => $this->config->all()]);

        return new Response($html, 500, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
