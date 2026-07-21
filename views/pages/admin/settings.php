<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Administration\Models\SystemSetting[] $settings
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'System settings | ' . $appConfig['name'];
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
        <h1>System settings</h1>
        <p>Runtime-editable settings, backed by the database. Anything not listed here falls back to <code>config/app.php</code>'s defaults (docs/specs/02-administration.md §2).</p>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <h2>Add or update a setting</h2>
        <form method="post" action="/admin/settings" class="product-action-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <div class="filters-grid">
                <div class="filter-field">
                    <label for="key">Key</label>
                    <input type="text" id="key" name="key" required placeholder="e.g. storefront.banner_message">
                </div>
                <div class="filter-field">
                    <label for="value">Value</label>
                    <input type="text" id="value" name="value">
                </div>
            </div>
            <button type="submit" class="btn-primary">Save setting</button>
        </form>

        <h2>Current settings</h2>
        <?php if (empty($settings)): ?>
            <div class="empty-state"><p>No runtime settings defined yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Type</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $setting): ?>
                        <tr>
                            <td><?= esc($setting->key); ?></td>
                            <td><?= esc(is_scalar($setting->value) ? (string) $setting->value : json_encode($setting->value)); ?></td>
                            <td><?= esc($setting->valueType); ?></td>
                            <td><?= esc($setting->updatedAt); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
