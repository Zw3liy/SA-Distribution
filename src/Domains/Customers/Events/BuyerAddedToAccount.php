<?php
declare(strict_types=1);

namespace App\Domains\Customers\Events;

/**
 * See CustomerCreated for why this is not yet dispatched anywhere.
 */
final class BuyerAddedToAccount
{
    /** @var int */
    public $customerId;

    /** @var int */
    public $userId;

    public function __construct(int $customerId, int $userId)
    {
        $this->customerId = $customerId;
        $this->userId = $userId;
    }
}
