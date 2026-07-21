<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Domains\Catalog\Services\ProductServiceInterface;
use App\Http\Request;
use App\Http\Response;
use App\Models\CartItem;
use App\Services\CartService;
use App\Support\View;
use Throwable;

class CartController
{
    /** @var CartService */
    private $cartService;

    /** @var ProductServiceInterface */
    private $productService;

    /** @var Config */
    private $config;

    public function __construct(CartService $cartService, ProductServiceInterface $productService, Config $config)
    {
        $this->cartService = $cartService;
        $this->productService = $productService;
        $this->config = $config;
    }

    /**
     * Original business logic, unchanged. Used by the database-backed
     * cart JSON API (api() below).
     */
    public function getCartPageData(string $sessionId): array
    {
        $items = $this->cartService->getCartItems($sessionId);
        $summary = $this->cartService->getCartSummary($items);
        return [
            'items' => $items,
            'summary' => $summary,
            'recentlyViewed' => $this->productService->getRecentlyViewedProducts(),
        ];
    }

    /**
     * Original business logic, unchanged.
     */
    public function handleCartAction(string $sessionId): void
    {
        $action = trim((string) filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
        $quantity = (int) filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

        if ($action === 'add' && $slug !== '') {
            $this->cartService->addProductToCart($sessionId, $slug, $quantity);
            setFlashMessage('Product successfully added to cart.');
        }

        if ($action === 'update' && $slug !== '') {
            $this->cartService->updateCartItemQuantity($sessionId, $slug, $quantity);
            setFlashMessage('Cart updated successfully.');
        }

        if ($action === 'remove' && $slug !== '') {
            $this->cartService->removeProductFromCart($sessionId, $slug);
            setFlashMessage('Item removed from cart.');
        }
    }

    /**
     * Route action for GET/POST /cart.php — the session-based cart page.
     * This is a direct port of the original cart.php, which operated
     * purely on the session cart via global helper functions rather than
     * CartService/CartRepository. That is a pre-existing inconsistency
     * with the database-backed cart used by api() below (see
     * PROJECT_AUDIT.md); it is preserved here deliberately, not fixed,
     * since unifying the two is a business-logic decision out of scope
     * for this phase.
     */
    public function page(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
            $quantity = (int) filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

            if ($action === 'add' && $slug !== '') {
                $product = $this->productService->getProductBySlug($slug);
                if ($product !== null) {
                    addToCart([
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'price' => $product->price,
                        'sale_price' => $product->salePrice,
                        'thumbnail' => $product->thumbnail,
                    ], $quantity);
                    setFlashMessage('Product added to cart.');
                }
            }

            if ($action === 'wishlist' && $slug !== '') {
                $product = $this->productService->getProductBySlug($slug);
                if ($product !== null) {
                    addToWishlist([
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'thumbnail' => $product->thumbnail,
                    ]);
                    setFlashMessage('Product added to wishlist.');
                }
            }

            if ($action === 'update' && $slug !== '') {
                updateCartItemQuantity($slug, $quantity);
                setFlashMessage('Cart updated successfully.');
            }

            if ($action === 'remove' && $slug !== '') {
                removeFromCart($slug);
                setFlashMessage('Item removed from cart.');
            }

            return Response::redirect('/cart.php');
        }

        $html = View::render('pages/cart', [
            'appConfig' => $this->config->all(),
            'flashMessage' => getFlashMessage(),
            'cartItems' => getCartItems(),
            'cartTotal' => getCartTotal(),
        ]);

        return Response::html($html);
    }

    /**
     * Route action for GET/POST /cart-api.php — the database-backed
     * cart JSON API, a direct port of the original cart-api.php.
     */
    public function api(Request $request): Response
    {
        $sessionId = session_id();
        if ($sessionId === '') {
            return Response::json(['error' => 'Session unavailable.'], 400);
        }

        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';

        try {
            if ($request->method() === 'GET' && $action === 'summary') {
                $data = $this->getCartPageData($sessionId);

                return Response::json([
                    'items' => array_map(function (CartItem $item) {
                        return [
                            'product_id' => $item->productId,
                            'slug' => $item->slug,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'price' => $item->price,
                            'sale_price' => $item->salePrice,
                            'quantity' => $item->quantity,
                            'stock' => $item->stock,
                            'thumbnail' => $item->thumbnail,
                            'unit_price' => $item->getUnitPrice(),
                            'line_total' => $item->getLineTotal(),
                        ];
                    }, $data['items']),
                    'summary' => $data['summary'],
                ]);
            }

            if ($request->method() === 'POST' && in_array($action, ['add', 'update', 'remove'], true)) {
                $_POST['action'] = $action;
                $this->handleCartAction($sessionId);

                return Response::json(['success' => true, 'message' => getFlashMessage()]);
            }

            return Response::json(['error' => 'Invalid request.'], 400);
        } catch (Throwable $exception) {
            return Response::json([
                'error' => 'Unable to process cart action.',
                'detail' => $exception->getMessage(),
            ], 500);
        }
    }
}
