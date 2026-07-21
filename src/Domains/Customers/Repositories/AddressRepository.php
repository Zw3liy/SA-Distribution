<?php
declare(strict_types=1);

namespace App\Domains\Customers\Repositories;

use App\Domains\Customers\Models\Address;
use PDO;
use RuntimeException;

class AddressRepository implements AddressRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByCustomerId(int $customerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, created_at ASC');
        $stmt->execute(['customer_id' => $customerId]);

        return array_map(static function (array $row): Address {
            return new Address($row);
        }, $stmt->fetchAll());
    }

    public function findById(int $id): ?Address
    {
        $stmt = $this->db->prepare('SELECT * FROM addresses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new Address($data) : null;
    }

    public function create(array $data): Address
    {
        $stmt = $this->db->prepare(
            'INSERT INTO addresses (customer_id, label, is_default, address_line_1, address_line_2, city, region, postal_code, country, type, created_at, updated_at)
             VALUES (:customer_id, :label, :is_default, :address_line_1, :address_line_2, :city, :region, :postal_code, :country, :type, NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $data['customer_id'],
            'label' => $data['label'],
            'is_default' => $data['is_default'] ?? 0,
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'],
            'region' => $data['region'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'] ?? 'South Africa',
            'type' => $data['type'],
        ]);

        $id = (int) $this->db->lastInsertId();
        $created = $this->findById($id);

        if ($created === null) {
            throw new RuntimeException('Address could not be reloaded after creation.');
        }

        return $created;
    }

    public function clearDefaultForType(int $customerId, string $type): void
    {
        $stmt = $this->db->prepare(
            "UPDATE addresses SET is_default = 0 WHERE customer_id = :customer_id AND (type = :type OR type = 'both')"
        );
        $stmt->execute(['customer_id' => $customerId, 'type' => $type]);
    }

    public function setDefault(int $addressId): void
    {
        $stmt = $this->db->prepare('UPDATE addresses SET is_default = 1, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $addressId]);
    }
}
