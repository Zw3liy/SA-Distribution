<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

use App\Domains\Administration\Models\SystemSetting;
use PDO;

class SystemSettingRepository implements SystemSettingRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function get(string $key): ?SystemSetting
    {
        $stmt = $this->db->prepare('SELECT * FROM system_settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $data = $stmt->fetch();

        return $data ? new SystemSetting($data) : null;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM system_settings ORDER BY `key` ASC');

        return array_map(static function (array $row): SystemSetting {
            return new SystemSetting($row);
        }, $stmt->fetchAll());
    }

    public function upsert(string $key, string $rawValue, string $type, int $updatedBy): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (`key`, value, value_type, is_editable, updated_by, updated_at)
             VALUES (:key, :value, :type, 1, :updated_by, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), value_type = VALUES(value_type), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([
            'key' => $key,
            'value' => $rawValue,
            'type' => $type,
            'updated_by' => $updatedBy,
        ]);
    }
}
