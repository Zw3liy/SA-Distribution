<?php
declare(strict_types=1);

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Repositories\AuditLogRepositoryInterface;
use App\Logging\Logger;
use Throwable;

class AuditLogger implements AuditLoggerInterface
{
    /** @var AuditLogRepositoryInterface */
    private $repository;

    /** @var Logger */
    private $logger;

    public function __construct(AuditLogRepositoryInterface $repository, Logger $logger)
    {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * Audit log writes must never throw in a way that blocks the primary
     * action they're auditing (docs/specs/02-administration.md §14) — a
     * broken audit table can't take down checkout or any other write
     * path. Failures are caught here and logged operationally instead.
     */
    public function record(string $domain, string $action, string $entityType, $entityId, array $before = [], array $after = []): void
    {
        try {
            $this->repository->insert([
                'actor_user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'domain' => $domain,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'before' => $before,
                'after' => $after,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Audit log write failed: ' . $exception->getMessage(), [
                'domain' => $domain,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }
}
