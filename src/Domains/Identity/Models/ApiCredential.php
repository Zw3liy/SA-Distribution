<?php
declare(strict_types=1);

namespace App\Domains\Identity\Models;

/**
 * A machine credential for the future API Platform domain (built ahead
 * of need here, per docs/specs/01-identity.md and the Phase 4 blueprint's
 * explicit instruction that Identity should be "ready for API Platform
 * later"). The raw token is never stored -- only its hash.
 */
class ApiCredential
{
    /** @var int */
    public $id;

    /** @var int */
    public $userId;

    /** @var string */
    public $name;

    /** @var string */
    public $tokenHash;

    /** @var array<int, string> */
    public $scopes;

    /** @var string|null */
    public $lastUsedAt;

    /** @var string|null */
    public $expiresAt;

    /** @var string|null */
    public $revokedAt;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->userId = (int) $data['user_id'];
        $this->name = (string) $data['name'];
        $this->tokenHash = (string) $data['token_hash'];
        $this->scopes = is_string($data['scopes']) ? (json_decode($data['scopes'], true) ?: []) : (array) $data['scopes'];
        $this->lastUsedAt = $data['last_used_at'] ?? null;
        $this->expiresAt = $data['expires_at'] ?? null;
        $this->revokedAt = $data['revoked_at'] ?? null;
        $this->createdAt = (string) $data['created_at'];
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && strtotime($this->expiresAt) < time();
    }
}
