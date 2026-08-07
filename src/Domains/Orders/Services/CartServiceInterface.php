<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

/**
 * New -- Phase 3's CartService had no interface (bound by concrete class
 * everywhere). Added per docs/specs/06-orders.md §5 ("existing methods
 * unchanged"); CheckoutService depends on this interface, not the
 * concrete class, to reach the DB-backed cart path per §19.
 */
interface CartServiceInterface
{
    public function getCartItems(string $sessionId): array;

    public function getCartSummary(array $items): array;

    public function addProductToCart(string $sessionId, string $slug, int $quantity): void;

    public function updateCartItemQuantity(string $sessionId, string $slug, int $quantity): void;

    public function removeProductFromCart(string $sessionId, string $slug): void;

    public function clearCart(string $sessionId): void;

    /** New for checkout -- see CartRepositoryInterface::markConverted(). */
    public function markCartConverted(string $sessionId, int $orderId): void;
}
