<?php
declare(strict_types=1);

namespace App\Http;

use App\Config\Config;
use App\Container\Container;
use App\Controllers\AccountController;
use App\Controllers\CartController;
use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Controllers\QuoteController;
use App\Controllers\WishlistController;
use App\Database\Database;
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
use App\Logging\Logger;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Services\CartService;
use App\Services\ProductService;
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

            [$controllerClass, $action] = $route;
            $controller = $this->container->get($controllerClass);

            return $controller->$action($request);
        } catch (Throwable $exception) {
            $this->logger->exception($exception);

            return $this->errorResponse($exception, $request);
        }
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
        $this->container->set(AuthController::class, function (Container $c) {
            return new AuthController($c->get(AuthServiceInterface::class), $c->get('config'));
        });

        // Not-yet-migrated domains — unchanged from Phase 3, still bound
        // by concrete class. AccountController depends on Identity's
        // UserServiceInterface (a cross-domain service dependency, which
        // is fine — see docs/specs/00-index.md conventions).
        $this->container->set(ProductRepository::class, function (Container $c) {
            return new ProductRepository($c->get(PDO::class));
        });
        $this->container->set(CartRepository::class, function (Container $c) {
            return new CartRepository($c->get(PDO::class));
        });
        $this->container->set(QuoteRepository::class, function (Container $c) {
            return new QuoteRepository($c->get(PDO::class));
        });

        $this->container->set(ProductService::class, function (Container $c) {
            return new ProductService($c->get(ProductRepository::class));
        });
        $this->container->set(CartService::class, function (Container $c) {
            return new CartService($c->get(ProductRepository::class), $c->get(CartRepository::class));
        });
        $this->container->set(QuoteService::class, function (Container $c) {
            return new QuoteService($c->get(QuoteRepository::class));
        });

        $this->container->set(HomeController::class, function (Container $c) {
            return new HomeController($c->get('config'));
        });
        $this->container->set(AccountController::class, function (Container $c) {
            return new AccountController($c->get(UserServiceInterface::class), $c->get('config'));
        });
        $this->container->set(CartController::class, function (Container $c) {
            return new CartController($c->get(CartService::class), $c->get(ProductService::class), $c->get('config'));
        });
        $this->container->set(ProductController::class, function (Container $c) {
            return new ProductController($c->get(ProductService::class), $c->get('config'));
        });
        $this->container->set(QuoteController::class, function (Container $c) {
            return new QuoteController($c->get(QuoteService::class), $c->get(CartService::class), $c->get('config'));
        });
        $this->container->set(WishlistController::class, function (Container $c) {
            return new WishlistController($c->get('config'));
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

        $this->router->any('/cart.php', CartController::class, 'page');
        $this->router->any('/cart-api.php', CartController::class, 'api');

        $this->router->any('/wishlist.php', WishlistController::class, 'page');

        $this->router->any('/quote-request.php', QuoteController::class, 'sessionRequest');
        $this->router->post('/quote-api.php', QuoteController::class, 'api');
    }

    private function notFoundResponse(): Response
    {
        $html = View::render('pages/404', ['appConfig' => $this->config->all()]);

        return Response::notFound($html);
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
