<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Repositories\FeatureFlagRepositoryInterface;
use App\Domains\Identity\Models\User;

class FeatureFlagService implements FeatureFlagServiceInterface
{
    /** @var FeatureFlagRepositoryInterface */
    private $repository;

    public function __construct(FeatureFlagRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Feature flags default to false (off) unless explicitly enabled —
     * new functionality is opt-in, never silently activated
     * (docs/specs/02-administration.md §2). A flag with no row at all
     * is therefore off, not an error.
     */
    public function isEnabled(string $key, ?User $context = null): bool
    {
        $flag = $this->repository->get($key);
        if ($flag === null || !$flag->isEnabled) {
            return false;
        }

        if (!empty($flag->rolloutRules['staff_only']) && ($context === null || !$context->isStaff())) {
            return false;
        }

        return true;
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function set(string $key, bool $isEnabled, array $rules, int $updatedByUserId): void
    {
        $this->repository->upsert($key, $isEnabled, $rules, $updatedByUserId);
    }
}
