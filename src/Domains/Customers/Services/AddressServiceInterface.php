<?php
declare(strict_types=1);

namespace App\Domains\Customers\Services;

use App\Domains\Customers\Models\Address;
use App\Domains\Customers\Models\Customer;

interface AddressServiceInterface
{
    /**
     * @return Address[]
     */
    public function listFor(Customer $customer): array;

    public function add(Customer $customer, array $data): Address;

    public function setDefault(int $addressId, string $type): void;
}
