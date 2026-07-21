<?php
declare(strict_types=1);

namespace App\Domains\Customers\Services;

use App\Domains\Customers\Exceptions\CustomerNotFoundException;
use App\Domains\Customers\Models\Address;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Repositories\AddressRepositoryInterface;
use InvalidArgumentException;

class AddressService implements AddressServiceInterface
{
    /**
     * South African postal codes are exactly 4 digits
     * (docs/specs/04-customers.md §8) -- previously unvalidated, since
     * Address was never wired into any controller in Phase 3.
     */
    private const SA_POSTAL_CODE_PATTERN = '/^\d{4}$/';

    private const VALID_TYPES = ['billing', 'shipping', 'both'];

    /** @var AddressRepositoryInterface */
    private $repository;

    public function __construct(AddressRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function listFor(Customer $customer): array
    {
        return $this->repository->findByCustomerId($customer->id);
    }

    public function add(Customer $customer, array $data): Address
    {
        $this->validate($data);

        $isDefault = !empty($data['is_default']);
        if ($isDefault) {
            $this->repository->clearDefaultForType($customer->id, $data['type']);
        }

        return $this->repository->create([
            'customer_id' => $customer->id,
            'label' => $data['label'],
            'is_default' => $isDefault ? 1 : 0,
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'],
            'region' => $data['region'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'] ?? 'South Africa',
            'type' => $data['type'],
        ]);
    }

    public function setDefault(int $addressId, string $type): void
    {
        $address = $this->repository->findById($addressId);
        if ($address === null) {
            throw new CustomerNotFoundException(sprintf('Address #%d not found.', $addressId));
        }

        $this->repository->clearDefaultForType($address->customerId, $type);
        $this->repository->setDefault($addressId);
    }

    private function validate(array $data): void
    {
        foreach (['label', 'address_line_1', 'city', 'region', 'postal_code', 'type'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException(sprintf('"%s" is required.', $field));
            }
        }

        if (!in_array($data['type'], self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid address type "%s".', $data['type']));
        }

        if (preg_match(self::SA_POSTAL_CODE_PATTERN, (string) $data['postal_code']) !== 1) {
            throw new InvalidArgumentException('Postal code must be exactly 4 digits (South African format).');
        }
    }
}
