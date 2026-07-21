<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Identity\Models\User;

interface FeatureFlagServiceInterface
{
    public function isEnabled(string $key, ?User $context = null): bool;

    /**
     * @return \App\Domains\Administration\Models\FeatureFlag[]
     */
    public function all(): array;

    public function set(string $key, bool $isEnabled, array $rules, int $updatedByUserId): void;
}
