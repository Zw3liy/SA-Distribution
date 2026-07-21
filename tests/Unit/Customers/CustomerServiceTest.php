<?php
declare(strict_types=1);

namespace Tests\Unit\Customers;

use App\Domains\Customers\Exceptions\DuplicateBuyerException;
use App\Domains\Customers\Exceptions\InvalidAccountTypeException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Repositories\CustomerRepositoryInterface;
use App\Domains\Customers\Services\CustomerService;
use App\Domains\Identity\Models\User;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    private function makeUser(int $id = 1): User
    {
        return new User([
            'id' => $id,
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'company_name' => null,
            'email' => 'jane@example.com',
            'phone' => '0110000000',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'is_active' => 1,
            'is_verified' => 1,
            'account_kind' => 'customer',
            'notifications_marketing' => 0,
            'notifications_updates' => 1,
            'last_login_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeCustomerRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 10,
            'account_type' => 'b2c',
            'company_name' => null,
            'parent_customer_id' => null,
            'credit_terms' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public function testCreateForUserRejectsUnknownAccountType(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new CustomerService($repo);

        $this->expectException(InvalidAccountTypeException::class);
        $service->createForUser($this->makeUser(), ['account_type' => 'reseller']);
    }

    public function testCreateForUserRejectsB2bWithoutCompanyName(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new CustomerService($repo);

        $this->expectException(InvalidAccountTypeException::class);
        $service->createForUser($this->makeUser(), ['account_type' => 'b2b']);
    }

    public function testCreateForUserRejectsB2bWithBlankCompanyName(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->never())->method('create');

        $service = new CustomerService($repo);

        $this->expectException(InvalidAccountTypeException::class);
        $service->createForUser($this->makeUser(), ['account_type' => 'b2b', 'company_name' => '   ']);
    }

    public function testCreateForUserForcesNullCompanyNameForB2c(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['account_type'] === 'b2c' && $data['company_name'] === null;
            }))
            ->willReturn(new Customer($this->makeCustomerRow()));
        $repo->expects($this->once())->method('linkUser')->with(10, 1, true);

        $service = new CustomerService($repo);
        $customer = $service->createForUser($this->makeUser(), ['account_type' => 'b2c', 'company_name' => 'Should be ignored']);

        $this->assertSame(10, $customer->id);
        $this->assertFalse($customer->isB2b());
    }

    public function testCreateForUserAcceptsB2bWithCompanyName(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['account_type'] === 'b2b' && $data['company_name'] === 'Acme Distribution';
            }))
            ->willReturn(new Customer($this->makeCustomerRow(['account_type' => 'b2b', 'company_name' => 'Acme Distribution'])));
        $repo->expects($this->once())->method('linkUser')->with(10, 1, true);

        $service = new CustomerService($repo);
        $customer = $service->createForUser($this->makeUser(), ['account_type' => 'b2b', 'company_name' => 'Acme Distribution']);

        $this->assertTrue($customer->isB2b());
        $this->assertSame('Acme Distribution', $customer->companyName);
    }

    public function testAddBuyerRejectsB2cAccount(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->expects($this->never())->method('linkUser');

        $service = new CustomerService($repo);
        $b2cAccount = new Customer($this->makeCustomerRow(['account_type' => 'b2c']));

        $this->expectException(InvalidAccountTypeException::class);
        $service->addBuyer($b2cAccount, $this->makeUser(2));
    }

    public function testAddBuyerRejectsDuplicateLink(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->method('isUserLinked')->with(10, 2)->willReturn(true);
        $repo->expects($this->never())->method('linkUser');

        $service = new CustomerService($repo);
        $b2bAccount = new Customer($this->makeCustomerRow(['account_type' => 'b2b', 'company_name' => 'Acme']));

        $this->expectException(DuplicateBuyerException::class);
        $service->addBuyer($b2bAccount, $this->makeUser(2));
    }

    public function testAddBuyerLinksNonPrimaryBuyer(): void
    {
        $repo = $this->createMock(CustomerRepositoryInterface::class);
        $repo->method('isUserLinked')->with(10, 2)->willReturn(false);
        $repo->expects($this->once())->method('linkUser')->with(10, 2, false);

        $service = new CustomerService($repo);
        $b2bAccount = new Customer($this->makeCustomerRow(['account_type' => 'b2b', 'company_name' => 'Acme']));
        $service->addBuyer($b2bAccount, $this->makeUser(2));

        $this->assertTrue(true);
    }
}
