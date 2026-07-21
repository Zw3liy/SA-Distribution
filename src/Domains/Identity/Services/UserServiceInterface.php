<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;

interface UserServiceInterface
{
    public function getUserById(int $id): ?User;

    public function updateProfile(int $userId, array $data): bool;

    public function hasPermission(User $user, string $permission): bool;

    /**
     * @return string[]
     */
    public function permissionsFor(User $user): array;

    /**
     * @return User[]
     */
    public function listByAccountKind(string $accountKind): array;

    public function setActive(int $userId, bool $isActive): bool;
}
