<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

use App\Domains\Administration\Models\FeatureFlag;

interface FeatureFlagRepositoryInterface
{
    public function get(string $key): ?FeatureFlag;

    /**
     * @return FeatureFlag[]
     */
    public function all(): array;

    public function upsert(string $key, bool $isEnabled, array $rules, int $updatedBy): void;
}
