<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

/**
 * The one interface every other domain is expected to take a dependency
 * on (docs/specs/02-administration.md §5) — kept deliberately narrow
 * (one real method) so it can never become a backdoor into
 * Administration's other concerns.
 */
interface AuditLoggerInterface
{
    public function record(string $domain, string $action, string $entityType, $entityId, array $before, array $after): void;
}
