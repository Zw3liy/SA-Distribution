<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\Warehouse;
use PDO;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?Warehouse
    {
        $stmt = $this->db->prepare('SELECT * FROM warehouses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new Warehouse($data) : null;
    }

    public function findDefault(): ?Warehouse
    {
        $stmt = $this->db->query('SELECT * FROM warehouses WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $data = $stmt->fetch();

        return $data ? new Warehouse($data) : null;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name ASC');

        return array_map(static function (array $row): Warehouse {
            return new Warehouse($row);
        }, $stmt->fetchAll());
    }
}
