<?php
declare(strict_types=1);

namespace App\Domains\Customers\Events;

/**
 * Public event contract per docs/specs/04-customers.md §9. Not
 * dispatched anywhere yet -- no event bus exists in the platform, and
 * this event's real consumers (CRM, Analytics) don't exist as domains
 * yet either. Defined now so the contract is settled, same pattern as
 * Catalog's event DTOs.
 */
final class CustomerCreated
{
    /** @var int */
    public $customerId;

    /** @var string */
    public $accountType;

    public function __construct(int $customerId, string $accountType)
    {
        $this->customerId = $customerId;
        $this->accountType = $accountType;
    }
}
