<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById(int $id): ?Warehouse;

    /**
     * The lowest-id active warehouse. Not an explicit is_default column
     * (the spec's minimal §3 schema doesn't define one) -- documented
     * convention: the first-seeded warehouse (MAIN) is always the
     * default until the Warehouse domain (#7) introduces a real
     * default-location concept.
     */
    public function findDefault(): ?Warehouse;

    public function all(): array;
}
