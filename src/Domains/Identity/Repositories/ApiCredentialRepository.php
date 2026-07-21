<?php
declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\ApiCredential;
use PDO;

class ApiCredentialRepository implements ApiCredentialRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $userId, string $name, string $tokenHash, array $scopes, ?string $expiresAt): ApiCredential
    {
        $stmt = $this->db->prepare(
            'INSERT INTO api_credentials (user_id, name, token_hash, scopes, expires_at, created_at)
             VALUES (:user_id, :name, :token_hash, :scopes, :expires_at, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => $tokenHash,
            'scopes' => json_encode(array_values($scopes)),
            'expires_at' => $expiresAt,
        ]);

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function findById(int $id): ApiCredential
    {
        $stmt = $this->db->prepare('SELECT * FROM api_credentials WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return new ApiCredential($stmt->fetch());
    }

    public function findByTokenHash(string $tokenHash): ?ApiCredential
    {
        $stmt = $this->db->prepare('SELECT * FROM api_credentials WHERE token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $tokenHash]);
        $data = $stmt->fetch();

        return $data ? new ApiCredential($data) : null;
    }

    public function revoke(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE api_credentials SET revoked_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function touchLastUsed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE api_credentials SET last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
