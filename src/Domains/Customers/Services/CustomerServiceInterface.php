<?php
declare(strict_types=1);

namespace App\Domains\Customers\Services;

use App\Domains\Customers\Models\Customer;
use App\Domains\Identity\Models\User;

interface CustomerServiceInterface
{
    public function createForUser(User $user, array $data): Customer;

    public function findForUser(User $user): ?Customer;

    /**
     * B2B account growth (docs/specs/04-customers.md §5) -- adds an
     * additional buyer login to an existing B2B account. Throws
     * InvalidAccountTypeException if the account isn't B2B, and
     * DuplicateBuyerException if the user is already linked.
     */
    public function addBuyer(Customer $b2bAccount, User $user): void;

    /**
     * @return Customer[]
     */
    public function listAll(int $limit, int $offset): array;

    public function countAll(): int;

    public function getById(int $id): ?Customer;

    /**
     * @return array<int, array{id:int, email:string, first_name:string, last_name:string, is_primary_contact:bool}>
     */
    public function buyersFor(Customer $customer): array;
}
