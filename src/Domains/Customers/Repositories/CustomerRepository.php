<?php
declare(strict_types=1);

namespace App\Domains\Customers\Repositories;

use App\Domains\Customers\Models\Customer;
use PDO;

class CustomerRepository implements CustomerRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?Customer
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new Customer($data) : null;
    }

    public function findByUserId(int $userId): ?Customer
    {
        $stmt = $this->db->prepare(
            'SELECT c.* FROM customers c
             INNER JOIN customer_users cu ON cu.customer_id = c.id
             WHERE cu.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch();

        return $data ? new Customer($data) : null;
    }

    public function create(array $data): Customer
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customers (account_type, company_name, parent_customer_id, credit_terms, created_at, updated_at)
             VALUES (:account_type, :company_name, :parent_customer_id, :credit_terms, NOW(), NOW())'
        );
        $stmt->execute([
            'account_type' => $data['account_type'],
            'company_name' => $data['company_name'] ?? null,
            'parent_customer_id' => $data['parent_customer_id'] ?? null,
            'credit_terms' => $data['credit_terms'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        $created = $this->findById($id);

        if ($created === null) {
            throw new \RuntimeException('Customer could not be reloaded after creation.');
        }

        return $created;
    }

    public function linkUser(int $customerId, int $userId, bool $isPrimary): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customer_users (customer_id, user_id, is_primary_contact) VALUES (:customer_id, :user_id, :is_primary)'
        );
        $stmt->execute([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'is_primary' => $isPrimary ? 1 : 0,
        ]);
    }

    public function isUserLinked(int $customerId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM customer_users WHERE customer_id = :customer_id AND user_id = :user_id');
        $stmt->execute(['customer_id' => $customerId, 'user_id' => $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findAll(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): Customer {
            return new Customer($row);
        }, $stmt->fetchAll());
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM customers');

        return (int) $stmt->fetchColumn();
    }

    public function buyersFor(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.email, u.first_name, u.last_name, cu.is_primary_contact
             FROM customer_users cu
             INNER JOIN users u ON u.id = cu.user_id
             WHERE cu.customer_id = :customer_id
             ORDER BY cu.is_primary_contact DESC, u.last_name ASC'
        );
        $stmt->execute(['customer_id' => $customerId]);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'is_primary_contact' => (bool) $row['is_primary_contact'],
            ];
        }, $stmt->fetchAll());
    }
}
