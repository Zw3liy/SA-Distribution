<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;

interface UserServiceInterface
{
    public function getUserById(int $id): ?User;

    public function updateProfile(int $userId, array $data): bool;
}
