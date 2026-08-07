<?php
declare(strict_types=1);

namespace App\Domains\Orders\Services;

use App\Domains\Orders\Models\Order;

interface CheckoutServiceInterface
{
    /**
     * Deviates from docs/specs/06-orders.md §5's literal signature by
     * adding $userId: the orders table's user_id column (§3) is a real,
     * required field the spec's own checkout() signature omitted --
     * documented here rather than silently working around it (e.g. by
     * guessing a "primary contact" user internally, which would be
     * incorrect for a B2B buyer who isn't the account's primary
     * contact).
     *
     * @throws \App\Domains\Inventory\Exceptions\InsufficientStockException
     * @throws \App\Domains\Orders\Exceptions\InvalidAddressException
     * @throws \App\Domains\Orders\Exceptions\PaymentFailedException
     */
    public function checkout(string $sessionId, int $customerId, int $userId, int $shippingAddressId, int $billingAddressId): Order;
}
