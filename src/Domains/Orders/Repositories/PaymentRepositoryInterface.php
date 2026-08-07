<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use App\Domains\Orders\Models\Payment;

/**
 * Not in the spec's minimal §6 repository list, but a real requirement
 * of §3's payments table and §16's "orchestration record" concept --
 * CheckoutService needs somewhere to persist the pending/succeeded/failed
 * lifecycle of that record. Added as a deliberate, documented extension,
 * matching the same precedent as Inventory's StockMovementRepository
 * (also not literally forced by the interface list but required by the
 * table the spec itself defines).
 */
interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function updateStatus(int $id, string $status): void;

    public function findByOrderId(int $orderId): ?Payment;
}
