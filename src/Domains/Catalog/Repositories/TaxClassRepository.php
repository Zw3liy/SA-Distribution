<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Repositories;

use App\Domains\Catalog\Models\TaxClass;
use PDO;

class TaxClassRepository implements TaxClassRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?TaxClass
    {
        $stmt = $this->db->prepare('SELECT * FROM tax_classes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new TaxClass($data) : null;
    }

    /**
     * Falls back to the first tax class by id when a product has no
     * explicit tax_class_id set yet (existing products predate this
     * column -- docs/specs/03-catalog.md §3).
     */
    public function findDefault(): ?TaxClass
    {
        $stmt = $this->db->query('SELECT * FROM tax_classes ORDER BY id ASC LIMIT 1');
        $data = $stmt->fetch();

        return $data ? new TaxClass($data) : null;
    }
}
