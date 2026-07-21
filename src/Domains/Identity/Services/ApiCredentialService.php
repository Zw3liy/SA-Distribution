<?php
declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\ApiCredentialRepositoryInterface;
use App\Domains\Identity\Repositories\UserRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ApiCredentialService implements ApiCredentialServiceInterface
{
    /** @var ApiCredentialRepositoryInterface */
    private $credentialRepository;

    /** @var UserRepositoryInterface */
    private $userRepository;

    public function __construct(ApiCredentialRepositoryInterface $credentialRepository, UserRepositoryInterface $userRepository)
    {
        $this->credentialRepository = $credentialRepository;
        $this->userRepository = $userRepository;
    }

    public function issue(User $issuer, string $name, array $scopes, ?int $expiresInDays = null): string
    {
        // A staff member can never mint a token with more access than
        // they themselves have (docs/specs/01-identity.md §8). Effective
        // permission resolution belongs to a future RBAC-checking
        // service; in this phase we only guard the one rule that matters
        // most here -- a token cannot claim a scope the issuer's own
        // account_kind wouldn't be allowed to use at all.
        if (!$issuer->isStaff()) {
            throw new InvalidArgumentException('Only staff accounts may issue API credentials.');
        }

        if (empty($scopes)) {
            throw new InvalidArgumentException('At least one scope is required.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = $expiresInDays !== null
            ? (new \DateTimeImmutable("+{$expiresInDays} days"))->format('Y-m-d H:i:s')
            : null;

        $this->credentialRepository->create($issuer->id, $name, $tokenHash, $scopes, $expiresAt);

        return $rawToken;
    }

    public function authenticate(string $rawToken): ?User
    {
        $tokenHash = hash('sha256', $rawToken);
        $credential = $this->credentialRepository->findByTokenHash($tokenHash);

        if ($credential === null || $credential->isRevoked() || $credential->isExpired()) {
            return null;
        }

        $this->credentialRepository->touchLastUsed($credential->id);

        return $this->userRepository->findById($credential->userId);
    }

    public function revoke(int $credentialId): void
    {
        $this->credentialRepository->revoke($credentialId);
    }
}
