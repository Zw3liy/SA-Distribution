<?php
declare(strict_types=1);

namespace App\Domains\Administration\Controllers;

use App\Config\Config;
use App\Domains\Administration\Repositories\AuditLogRepositoryInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\View;

class AuditLogController
{
    private const PER_PAGE = 25;

    /** @var AuditLogRepositoryInterface */
    private $auditLogRepository;

    /** @var Config */
    private $config;

    public function __construct(AuditLogRepositoryInterface $auditLogRepository, Config $config)
    {
        $this->auditLogRepository = $auditLogRepository;
        $this->config = $config;
    }

    /**
     * Route action for GET /admin/audit-log. Paginated, filterable by
     * domain (docs/specs/02-administration.md §13/§17 — never a
     * full-table read).
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
        $domainFilter = trim((string) filter_input(INPUT_GET, 'domain', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

        $filters = $domainFilter !== '' ? ['domain' => $domainFilter] : [];
        $offset = (($page - 1) * self::PER_PAGE);

        $entries = $this->auditLogRepository->query($filters, self::PER_PAGE, $offset);
        $total = $this->auditLogRepository->count($filters);

        $html = View::render('pages/admin/audit-log', [
            'appConfig' => $this->config->all(),
            'entries' => $entries,
            'domainFilter' => $domainFilter,
            'currentPage' => $page,
            'totalPages' => (int) max(1, ceil($total / self::PER_PAGE)),
        ]);

        return Response::html($html);
    }
}
