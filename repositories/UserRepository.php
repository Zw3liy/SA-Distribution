<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

class UserRepository
{
    /** @var PDO */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

        return $data ? new User($data) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new User($data) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, company_name, email, phone, password_hash, is_active, is_verified, notifications_marketing, notifications_updates, created_at, updated_at)
             VALUES (:first_name, :last_name, :company_name, :email, :phone, :password_hash, :is_active, :is_verified, :notifications_marketing, :notifications_updates, NOW(), NOW())'
        );

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => $data['password_hash'],
            'is_active' => $data['is_active'] ?? 1,
            'is_verified' => $data['is_verified'] ?? 0,
            'notifications_marketing' => $data['notifications_marketing'] ?? 0,
            'notifications_updates' => $data['notifications_updates'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['first_name'])) {
            $fields[] = 'first_name = :first_name';
            $params['first_name'] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $fields[] = 'last_name = :last_name';
            $params['last_name'] = $data['last_name'];
        }

        if (array_key_exists('company_name', $data)) {
            $fields[] = 'company_name = :company_name';
            $params['company_name'] = $data['company_name'];
        }

        if (isset($data['phone'])) {
            $fields[] = 'phone = :phone';
            $params['phone'] = $data['phone'];
        }

        if (isset($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }

        if (isset($data['notifications_marketing'])) {
            $fields[] = 'notifications_marketing = :notifications_marketing';
            $params['notifications_marketing'] = $data['notifications_marketing'];
        }

        if (isset($data['notifications_updates'])) {
            $fields[] = 'notifications_updates = :notifications_updates';
            $params['notifications_updates'] = $data['notifications_updates'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
