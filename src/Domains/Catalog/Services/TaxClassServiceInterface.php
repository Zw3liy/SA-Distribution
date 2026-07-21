<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\TaxClass;

interface TaxClassServiceInterface
{
    public function forProduct(Product $product): ?TaxClass;
}
