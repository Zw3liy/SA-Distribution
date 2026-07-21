<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

use App\Domains\Administration\Models\AuditLogEntry;
use PDO;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insert(array $entry): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log_entries (actor_user_id, domain, action, entity_type, entity_id, before_json, after_json, ip, created_at)
             VALUES (:actor_user_id, :domain, :action, :entity_type, :entity_id, :before_json, :after_json, :ip, NOW())'
        );
        $stmt->execute([
            'actor_user_id' => $entry['actor_user_id'] ?? null,
            'domain' => $entry['domain'],
            'action' => $entry['action'],
            'entity_type' => $entry['entity_type'],
            'entity_id' => (string) $entry['entity_id'],
            'before_json' => json_encode($entry['before'] ?? []),
            'after_json' => json_encode($entry['after'] ?? []),
            'ip' => $entry['ip'] ?? null,
        ]);
    }

    public function query(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $sql = "SELECT * FROM audit_log_entries {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): AuditLogEntry {
            return new AuditLogEntry($row);
        }, $stmt->fetchAll());
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM audit_log_entries {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['domain'])) {
            $clauses[] = 'domain = :domain';
            $params['domain'] = $filters['domain'];
        }

        if (!empty($filters['actor_user_id'])) {
            $clauses[] = 'actor_user_id = :actor_user_id';
            $params['actor_user_id'] = $filters['actor_user_id'];
        }

        $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

        return [$where, $params];
    }
}
