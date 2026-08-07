<?php
declare(strict_types=1);

namespace App\Domains\Orders\Events;

/**
 * Not dispatched -- see OrderPlaced's docblock for why. Warehouse
 * consumes the paid->fulfilling transition specifically (fulfillment
 * trigger) once that domain exists.
 */
final class OrderStatusChanged
{
    /** @var int */
    public $orderId;

    /** @var string */
    public $fromStatus;

    /** @var string */
    public $toStatus;

    public function __construct(int $orderId, string $fromStatus, string $toStatus)
    {
        $this->orderId = $orderId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
    }
}
