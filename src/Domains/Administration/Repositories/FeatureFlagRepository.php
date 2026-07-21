<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

use App\Domains\Administration\Models\FeatureFlag;
use PDO;

class FeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function get(string $key): ?FeatureFlag
    {
        $stmt = $this->db->prepare('SELECT * FROM feature_flags WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $data = $stmt->fetch();

        return $data ? new FeatureFlag($data) : null;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM feature_flags ORDER BY `key` ASC');

        return array_map(static function (array $row): FeatureFlag {
            return new FeatureFlag($row);
        }, $stmt->fetchAll());
    }

    public function upsert(string $key, bool $isEnabled, array $rules, int $updatedBy): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO feature_flags (`key`, is_enabled, rollout_rules_json, updated_by, updated_at)
             VALUES (:key, :is_enabled, :rules, :updated_by, NOW())
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), rollout_rules_json = VALUES(rollout_rules_json), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([
            'key' => $key,
            'is_enabled' => $isEnabled ? 1 : 0,
            'rules' => json_encode($rules),
            'updated_by' => $updatedBy,
        ]);
    }
}
