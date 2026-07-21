<?php
declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function updateLastLogin(int $id): void;

    public function recordLoginAttempt(string $email, string $ip, bool $successful): void;

    public function recentFailedAttempts(string $email, string $ip, int $windowSeconds): int;

    public function userHasPermission(int $userId, string $permissionName): bool;

    /**
     * @return string[]
     */
    public function permissionsForUser(int $userId): array;

    /**
     * @return User[]
     */
    public function findByAccountKind(string $accountKind): array;
}
