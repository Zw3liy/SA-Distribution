<?php
declare(strict_types=1);

namespace App\Domains\Customers\Repositories;

use App\Domains\Customers\Models\Customer;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    /**
     * Single indexed lookup on customer_users.user_id -- called on
     * nearly every authenticated request to resolve "which customer is
     * this user acting for" (docs/specs/04-customers.md §17).
     */
    public function findByUserId(int $userId): ?Customer;

    public function create(array $data): Customer;

    public function linkUser(int $customerId, int $userId, bool $isPrimary): void;

    public function isUserLinked(int $customerId, int $userId): bool;

    /**
     * @return Customer[]
     */
    public function findAll(int $limit, int $offset): array;

    public function countAll(): int;

    /**
     * @return array<int, array{id:int, email:string, first_name:string, last_name:string, is_primary_contact:bool}>
     */
    public function buyersFor(int $customerId): array;
}
