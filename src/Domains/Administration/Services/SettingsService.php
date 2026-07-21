<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Exceptions\InvalidSettingTypeException;
use App\Domains\Administration\Repositories\SystemSettingRepositoryInterface;

class SettingsService implements SettingsServiceInterface
{
    /** @var SystemSettingRepositoryInterface */
    private $repository;

    public function __construct(SystemSettingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function get(string $key, $default = null)
    {
        $setting = $this->repository->get($key);

        return $setting !== null ? $setting->value : $default;
    }

    public function set(string $key, $value, int $updatedByUserId): void
    {
        $type = $this->inferType($value);
        $rawValue = $this->encode($value, $type);

        $this->repository->upsert($key, $rawValue, $type, $updatedByUserId);
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    private function inferType($value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_array($value)) {
            return 'json';
        }
        if (is_string($value)) {
            return 'string';
        }

        throw new InvalidSettingTypeException('Unsupported setting value type: ' . gettype($value));
    }

    private function encode($value, string $type): string
    {
        switch ($type) {
            case 'bool':
                return $value ? '1' : '0';
            case 'json':
                return (string) json_encode($value);
            default:
                return (string) $value;
        }
    }
}
