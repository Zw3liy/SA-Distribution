<?php
declare(strict_types=1);

namespace Tests\Unit\Customers;

use App\Domains\Customers\Exceptions\CustomerNotFoundException;
use App\Domains\Customers\Models\Address;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Repositories\AddressRepositoryInterface;
use App\Domains\Customers\Services\AddressService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AddressServiceTest extends TestCase
{
    private function makeCustomer(int $id = 10): Customer
    {
        return new Customer([
            'id' => $id,
            'account_type' => 'b2c',
            'company_name' => null,
            'parent_customer_id' => null,
            'credit_terms' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeAddressRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 5,
            'customer_id' => 10,
            'user_id' => null,
            'label' => 'Head Office',
            'is_default' => 1,
            'address_line_1' => '1 Main Road',
            'address_line_2' => null,
            'city' => 'Johannesburg',
            'region' => 'Gauteng',
            'postal_code' => '2196',
            'country' => 'South Africa',
            'type' => 'shipping',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Head Office',
            'address_line_1' => '1 Main Road',
            'city' => 'Johannesburg',
            'region' => 'Gauteng',
            'postal_code' => '2196',
            'type' => 'shipping',
        ], $overrides);
    }

    public function testAddRejectsMissingRequiredField(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new AddressService($repo);

        $this->expectException(InvalidArgumentException::class);
        $data = $this->validPayload();
        unset($data['city']);
        $service->add($this->makeCustomer(), $data);
    }

    public function testAddRejectsInvalidType(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new AddressService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->add($this->makeCustomer(), $this->validPayload(['type' => 'headquarters']));
    }

    public function testAddAllowsBothAsAType(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->once())->method('create')->willReturn(new Address($this->makeAddressRow(['type' => 'both'])));

        $service = new AddressService($repo);
        $address = $service->add($this->makeCustomer(), $this->validPayload(['type' => 'both']));

        $this->assertSame('both', $address->type);
    }

    /**
     * South African postal codes are exactly 4 digits
     * (docs/specs/04-customers.md §8) -- the real validation rule this
     * domain adds; Phase 3 never validated postal codes at all.
     */
    public function testAddRejectsNonFourDigitPostalCode(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new AddressService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->add($this->makeCustomer(), $this->validPayload(['postal_code' => '12345']));
    }

    public function testAddRejectsNonNumericPostalCode(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new AddressService($repo);

        $this->expectException(InvalidArgumentException::class);
        $service->add($this->makeCustomer(), $this->validPayload(['postal_code' => 'ABCD']));
    }

    public function testAddClearsExistingDefaultWhenNewAddressIsDefault(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->once())->method('clearDefaultForType')->with(10, 'shipping');
        $repo->expects($this->once())->method('create')->willReturn(new Address($this->makeAddressRow()));

        $service = new AddressService($repo);
        $service->add($this->makeCustomer(), $this->validPayload(['is_default' => true]));

        $this->assertTrue(true);
    }

    public function testAddDoesNotClearDefaultsWhenNotDefault(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->expects($this->never())->method('clearDefaultForType');
        $repo->expects($this->once())->method('create')->willReturn(new Address($this->makeAddressRow(['is_default' => 0])));

        $service = new AddressService($repo);
        $service->add($this->makeCustomer(), $this->validPayload());

        $this->assertTrue(true);
    }

    public function testSetDefaultThrowsWhenAddressMissing(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);
        $repo->expects($this->never())->method('setDefault');

        $service = new AddressService($repo);

        $this->expectException(CustomerNotFoundException::class);
        $service->setDefault(999, 'shipping');
    }

    public function testSetDefaultClearsThenSets(): void
    {
        $repo = $this->createMock(AddressRepositoryInterface::class);
        $repo->method('findById')->with(5)->willReturn(new Address($this->makeAddressRow(['id' => 5, 'customer_id' => 10])));
        $repo->expects($this->once())->method('clearDefaultForType')->with(10, 'shipping');
        $repo->expects($this->once())->method('setDefault')->with(5);

        $service = new AddressService($repo);
        $service->setDefault(5, 'shipping');

        $this->assertTrue(true);
    }
}
