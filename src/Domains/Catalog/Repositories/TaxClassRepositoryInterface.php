<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Repositories;

use App\Domains\Catalog\Models\TaxClass;

interface TaxClassRepositoryInterface
{
    public function findById(int $id): ?TaxClass;

    public function findDefault(): ?TaxClass;
}
