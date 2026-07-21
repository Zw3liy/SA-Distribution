<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;

interface AuthServiceInterface
{
    public function register(array $data): int;

    /**
     * @throws \App\Domains\Identity\Exceptions\AccountLockedException
     * @throws \App\Domains\Identity\Exceptions\InvalidCredentialsException
     * @throws \App\Domains\Identity\Exceptions\AccountInactiveException
     */
    public function authenticate(string $email, string $password, string $ip): User;
}
