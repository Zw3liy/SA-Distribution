<?php
declare(strict_types=1);

namespace App\Domains\Orders\Events;

/**
 * Not dispatched -- see OrderPlaced's docblock for why.
 */
final class OrderCancelled
{
    /** @var int */
    public $orderId;

    /** @var string */
    public $reason;

    public function __construct(int $orderId, string $reason)
    {
        $this->orderId = $orderId;
        $this->reason = $reason;
    }
}
