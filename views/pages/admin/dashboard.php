<?php
declare(strict_types=1);

/** @var array $appConfig */

$seoTitle = 'Admin dashboard | ' . $appConfig['name'];
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
        <h1>Admin dashboard</h1>
        <p>Welcome to the SA Business Distribution admin portal. This shell hosts every back-office screen — staff, settings, feature flags, and the audit log — and is the mounting point for each future domain's own admin screens as they're implemented.</p>
        <div class="filters-grid">
            <div class="filter-field">
                <a class="btn-primary" href="/admin/staff">Manage staff</a>
            </div>
            <div class="filter-field">
                <a class="btn-secondary" href="/admin/settings">System settings</a>
            </div>
            <div class="filter-field">
                <a class="btn-secondary" href="/admin/feature-flags">Feature flags</a>
            </div>
            <div class="filter-field">
                <a class="btn-secondary" href="/admin/audit-log">Audit log</a>
            </div>
        </div>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
