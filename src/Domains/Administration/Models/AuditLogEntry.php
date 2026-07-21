<?php
declare(strict_types=1);

namespace App\Domains\Administration\Models;

class AuditLogEntry
{
    /** @var int */
    public $id;

    /** @var int|null */
    public $actorUserId;

    /** @var string */
    public $domain;

    /** @var string */
    public $action;

    /** @var string */
    public $entityType;

    /** @var string */
    public $entityId;

    /** @var array */
    public $before;

    /** @var array */
    public $after;

    /** @var string|null */
    public $ip;

    /** @var string */
    public $createdAt;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->actorUserId = isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null;
        $this->domain = (string) $data['domain'];
        $this->action = (string) $data['action'];
        $this->entityType = (string) $data['entity_type'];
        $this->entityId = (string) $data['entity_id'];
        $this->before = $this->decode($data['before_json'] ?? null);
        $this->after = $this->decode($data['after_json'] ?? null);
        $this->ip = $data['ip'] ?? null;
        $this->createdAt = (string) $data['created_at'];
    }

    private function decode($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_string($value) ? (json_decode($value, true) ?: []) : (array) $value;
    }
}
