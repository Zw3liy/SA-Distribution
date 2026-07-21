<?php
declare(strict_types=1);

namespace App\Domains\Customers\Repositories;

use App\Domains\Customers\Models\Address;

interface AddressRepositoryInterface
{
    /**
     * @return Address[]
     */
    public function findByCustomerId(int $customerId): array;

    public function findById(int $id): ?Address;

    public function create(array $data): Address;

    public function clearDefaultForType(int $customerId, string $type): void;

    public function setDefault(int $addressId): void;
}
