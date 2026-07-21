<?php
declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\ApiCredential;

interface ApiCredentialRepositoryInterface
{
    public function create(int $userId, string $name, string $tokenHash, array $scopes, ?string $expiresAt): ApiCredential;

    public function findByTokenHash(string $tokenHash): ?ApiCredential;

    public function revoke(int $id): void;

    public function touchLastUsed(int $id): void;
}
