<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Administration\Models\AuditLogEntry[] $entries
 * @var string $domainFilter
 * @var int $currentPage
 * @var int $totalPages
 */

$seoTitle = 'Audit log | ' . $appConfig['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/admin-nav.php'; ?>
    <main class="container page-content">
        <h1>Audit log</h1>
        <p>Append-only record of staff/system actions across every domain. Cannot be edited or deleted from this UI (docs/specs/02-administration.md §16).</p>

        <form method="get" action="/admin/audit-log" class="filters-grid">
            <div class="filter-field">
                <label for="domain">Domain</label>
                <input type="text" id="domain" name="domain" value="<?= esc($domainFilter); ?>" placeholder="e.g. identity">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-secondary">Filter</button>
            </div>
        </form>

        <?php if (empty($entries)): ?>
            <div class="empty-state"><p>No audit log entries<?= $domainFilter !== '' ? ' for domain "' . esc($domainFilter) . '"' : ''; ?>.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Domain</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Actor</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= esc($entry->createdAt); ?></td>
                            <td><?= esc($entry->domain); ?></td>
                            <td><?= esc($entry->action); ?></td>
                            <td><?= esc($entry->entityType . ' #' . $entry->entityId); ?></td>
                            <td><?= $entry->actorUserId !== null ? '#' . (int) $entry->actorUserId : 'system'; ?></td>
                            <td><?= esc((string) $entry->ip); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <nav class="pagination" aria-label="Audit log pagination">
                <?php if ($currentPage > 1): ?>
                    <a class="pagination-link" href="/admin/audit-log?page=<?= $currentPage - 1; ?>&domain=<?= urlencode($domainFilter); ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= (int) $currentPage; ?> of <?= (int) $totalPages; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a class="pagination-link" href="/admin/audit-log?page=<?= $currentPage + 1; ?>&domain=<?= urlencode($domainFilter); ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
