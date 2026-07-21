<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\ApiCredential;
use App\Domains\Identity\Models\User;

interface ApiCredentialServiceInterface
{
    /**
     * Returns the raw token. This is the only time it is ever available --
     * only its hash is persisted (docs/specs/01-identity.md §16).
     */
    public function issue(User $issuer, string $name, array $scopes, ?int $expiresInDays = null): string;

    public function authenticate(string $rawToken): ?User;

    public function revoke(int $credentialId): void;
}
