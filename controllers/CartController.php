<?php
declare(strict_types=1);

class CartController
{
    /** @var CartService */
    private $cartService;

    /** @var ProductService */
    private $productService;

    public function __construct(CartService $cartService, ProductService $productService)
    {
        $this->cartService = $cartService;
        $this->productService = $productService;
    }

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
}
