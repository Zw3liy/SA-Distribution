<?php
declare(strict_types=1);

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\TaxClass;
use App\Domains\Catalog\Repositories\TaxClassRepositoryInterface;

class TaxClassService implements TaxClassServiceInterface
{
    /** @var TaxClassRepositoryInterface */
    private $repository;

    public function __construct(TaxClassRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function forProduct(Product $product): ?TaxClass
    {
        if ($product->taxClassId !== null) {
            $taxClass = $this->repository->findById($product->taxClassId);
            if ($taxClass !== null) {
                return $taxClass;
            }
        }

        return $this->repository->findDefault();
    }
}
