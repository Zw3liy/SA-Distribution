<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

use App\Domains\Administration\Models\SystemSetting;

interface SystemSettingRepositoryInterface
{
    public function get(string $key): ?SystemSetting;

    /**
     * @return SystemSetting[]
     */
    public function all(): array;

    public function upsert(string $key, string $rawValue, string $type, int $updatedBy): void;
}
