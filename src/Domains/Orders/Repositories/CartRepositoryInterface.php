<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

/**
 * New -- Phase 3's CartRepository had no interface at all (bound by
 * concrete class everywhere). Added now per docs/specs/06-orders.md §5's
 * explicit "existing methods unchanged" contract, since every other
 * domain's repository is interface-bound and Orders is where this one
 * finally gets pulled into that convention -- the method bodies
 * themselves are untouched from Phase 3.
 */
interface CartRepositoryInterface
{
    public function getCartIdBySession(string $sessionId): ?int;

    public function saveCartItems(string $sessionId, array $items): void;

    public function getCartItemsBySession(string $sessionId): array;

    public function removeCartItem(string $sessionId, string $productSlug): void;

    public function clearCart(string $sessionId): void;

    /**
     * Not in Phase 3's original cart repository -- new for checkout
     * (docs/specs/06-orders.md §2): "the cart is not deleted but marked
     * converted... and a new empty cart is created for the session/user
     * going forward." getCartIdBySession() excludes converted carts, so
     * calling this makes the next saveCartItems() call for the same
     * session create a brand new cart row rather than reusing the
     * now-historical one.
     */
    public function markConverted(string $sessionId, int $orderId): void;
}
