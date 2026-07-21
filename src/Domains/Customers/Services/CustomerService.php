<?php
declare(strict_types=1);

namespace App\Domains\Customers\Services;

use App\Domains\Customers\Exceptions\DuplicateBuyerException;
use App\Domains\Customers\Exceptions\InvalidAccountTypeException;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Repositories\CustomerRepositoryInterface;
use App\Domains\Identity\Models\User;

class CustomerService implements CustomerServiceInterface
{
    /** @var CustomerRepositoryInterface */
    private $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Validates the account-type business rule (docs/specs/04-customers.md
     * §2/§8): company_name is required for b2b, forbidden (must be null)
     * for b2c.
     */
    public function createForUser(User $user, array $data): Customer
    {
        $accountType = $data['account_type'] ?? 'b2c';

        if (!in_array($accountType, ['b2c', 'b2b'], true)) {
            throw new InvalidAccountTypeException(sprintf('Unknown account type "%s".', $accountType));
        }

        $companyName = $data['company_name'] ?? null;

        if ($accountType === 'b2b' && ($companyName === null || trim((string) $companyName) === '')) {
            throw new InvalidAccountTypeException('A company name is required for a B2B account.');
        }

        if ($accountType === 'b2c') {
            $companyName = null;
        }

        $customer = $this->repository->create([
            'account_type' => $accountType,
            'company_name' => $companyName,
            'parent_customer_id' => $data['parent_customer_id'] ?? null,
            'credit_terms' => $data['credit_terms'] ?? null,
        ]);

        $this->repository->linkUser($customer->id, $user->id, true);

        return $customer;
    }

    public function findForUser(User $user): ?Customer
    {
        return $this->repository->findByUserId($user->id);
    }

    public function addBuyer(Customer $b2bAccount, User $user): void
    {
        if (!$b2bAccount->isB2b()) {
            throw new InvalidAccountTypeException('Buyers can only be added to a B2B account.');
        }

        if ($this->repository->isUserLinked($b2bAccount->id, $user->id)) {
            throw new DuplicateBuyerException(sprintf('User #%d is already linked to this account.', $user->id));
        }

        $this->repository->linkUser($b2bAccount->id, $user->id, false);
    }

    public function listAll(int $limit, int $offset): array
    {
        return $this->repository->findAll($limit, $offset);
    }

    public function countAll(): int
    {
        return $this->repository->countAll();
    }

    public function getById(int $id): ?Customer
    {
        return $this->repository->findById($id);
    }

    public function buyersFor(Customer $customer): array
    {
        return $this->repository->buyersFor($customer->id);
    }
}
