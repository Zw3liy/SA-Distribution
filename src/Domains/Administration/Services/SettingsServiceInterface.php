<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

interface SettingsServiceInterface
{
    /**
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param mixed $value
     */
    public function set(string $key, $value, int $updatedByUserId): void;

    /**
     * @return \App\Domains\Administration\Models\SystemSetting[]
     */
    public function all(): array;
}
