<?php
declare(strict_types=1);

namespace App\Domains\Administration\Repositories;

interface AuditLogRepositoryInterface
{
    public function insert(array $entry): void;

    /**
     * @return \App\Domains\Administration\Models\AuditLogEntry[]
     */
    public function query(array $filters, int $limit, int $offset): array;

    public function count(array $filters): int;
}
