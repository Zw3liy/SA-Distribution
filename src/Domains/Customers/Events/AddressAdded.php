<?php
declare(strict_types=1);

namespace App\Domains\Customers\Events;

/**
 * See CustomerCreated for why this is not yet dispatched anywhere.
 */
final class AddressAdded
{
    /** @var int */
    public $customerId;

    /** @var int */
    public $addressId;

    public function __construct(int $customerId, int $addressId)
    {
        $this->customerId = $customerId;
        $this->addressId = $addressId;
    }
}
