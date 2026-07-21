<?php
declare(strict_types=1);

namespace Tests\Integration\Customers;

use App\Domains\Customers\Repositories\AddressRepository;
use App\Domains\Customers\Repositories\CustomerRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_customers_domain.sql migration applied.
 */
final class AddressRepositoryTest extends TestCase
{
    private PDO $db;
    private AddressRepository $repository;
    private CustomerRepository $customerRepository;
    private int $customerId = 0;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'sa_business';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $this->db = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->repository = new AddressRepository($this->db);
        $this->customerRepository = new CustomerRepository($this->db);

        $this->cleanup();

        $customer = $this->customerRepository->create(['account_type' => 'b2c', 'company_name' => null]);
        $this->customerId = $customer->id;
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        // addresses.customer_id has ON DELETE CASCADE, but tests delete
        // addresses explicitly first for clarity, then remove the
        // fixture customer created in setUp() by its tracked id (never
        // linked via customer_users, so safe to delete outright).
        $this->db->exec("DELETE FROM addresses WHERE label LIKE 'Repo Test%'");
        if ($this->customerId > 0) {
            $this->db->exec('DELETE FROM customers WHERE id = ' . $this->customerId);
            $this->customerId = 0;
        }
    }

    private function baseAddressData(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customerId,
            'label' => 'Repo Test Head Office',
            'is_default' => 0,
            'address_line_1' => '1 Main Road',
            'address_line_2' => null,
            'city' => 'Johannesburg',
            'region' => 'Gauteng',
            'postal_code' => '2196',
            'country' => 'South Africa',
            'type' => 'shipping',
        ], $overrides);
    }

    public function testCreateAndFindByIdRoundTrips(): void
    {
        $address = $this->repository->create($this->baseAddressData());

        $this->assertGreaterThan(0, $address->id);
        $this->assertSame($this->customerId, $address->customerId);

        $found = $this->repository->findById($address->id);
        $this->assertNotNull($found);
        $this->assertSame('1 Main Road', $found->addressLine1);
    }

    public function testFindByCustomerIdOrdersDefaultFirst(): void
    {
        $this->repository->create($this->baseAddressData(['label' => 'Repo Test Secondary', 'is_default' => 0]));
        $this->repository->create($this->baseAddressData(['label' => 'Repo Test Primary', 'is_default' => 1]));

        $addresses = $this->repository->findByCustomerId($this->customerId);

        $this->assertCount(2, $addresses);
        $this->assertTrue($addresses[0]->isDefault);
        $this->assertSame('Repo Test Primary', $addresses[0]->label);
    }

    /**
     * clearDefaultForType must clear both an exact type match and any
     * 'both'-typed address, since a 'both' address covers billing and
     * shipping simultaneously (AddressService::setDefault() relies on
     * this).
     */
    public function testClearDefaultForTypeClearsExactTypeAndBoth(): void
    {
        $shipping = $this->repository->create($this->baseAddressData(['label' => 'Repo Test Shipping', 'is_default' => 1, 'type' => 'shipping']));
        $both = $this->repository->create($this->baseAddressData(['label' => 'Repo Test Both', 'is_default' => 1, 'type' => 'both']));
        $billing = $this->repository->create($this->baseAddressData(['label' => 'Repo Test Billing', 'is_default' => 1, 'type' => 'billing']));

        $this->repository->clearDefaultForType($this->customerId, 'shipping');

        $this->assertFalse($this->repository->findById($shipping->id)->isDefault);
        $this->assertFalse($this->repository->findById($both->id)->isDefault);
        $this->assertTrue($this->repository->findById($billing->id)->isDefault);
    }

    public function testSetDefaultMarksAddressAsDefault(): void
    {
        $address = $this->repository->create($this->baseAddressData(['is_default' => 0]));

        $this->repository->setDefault($address->id);

        $this->assertTrue($this->repository->findById($address->id)->isDefault);
    }

    /**
     * Migration regression test (docs/specs/04-customers.md §18): a
     * freshly-created address is fully owned by customer_id, with the
     * deprecated user_id compatibility column left null -- confirming
     * the re-parenting migration's schema shape behaves as designed for
     * all new writes going forward.
     */
    public function testNewAddressesAreCustomerOwnedNotUserOwned(): void
    {
        $address = $this->repository->create($this->baseAddressData());

        $this->assertSame($this->customerId, $address->customerId);
        $this->assertNull($address->userId);
    }
}
