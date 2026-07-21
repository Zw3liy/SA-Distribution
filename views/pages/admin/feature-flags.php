<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Administration\Models\FeatureFlag[] $flags
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Feature flags | ' . $appConfig['name'];
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
        <h1>Feature flags</h1>
        <p>Flags default to off — new functionality is opt-in, never silently activated (docs/specs/02-administration.md §2).</p>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <h2>Add or update a flag</h2>
        <form method="post" action="/admin/feature-flags" class="product-action-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <div class="filters-grid">
                <div class="filter-field">
                    <label for="key">Key</label>
                    <input type="text" id="key" name="key" required placeholder="e.g. ai.assistant.enabled">
                </div>
                <div class="filter-field">
                    <label for="is_enabled">Enabled</label>
                    <select id="is_enabled" name="is_enabled">
                        <option value="0">Off</option>
                        <option value="1">On</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="staff_only">Staff only</label>
                    <select id="staff_only" name="staff_only">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-primary">Save flag</button>
        </form>

        <h2>Current flags</h2>
        <?php if (empty($flags)): ?>
            <div class="empty-state"><p>No feature flags defined yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Status</th>
                        <th>Rollout rules</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flags as $flag): ?>
                        <tr>
                            <td><?= esc($flag->key); ?></td>
                            <td><span class="badge <?= $flag->isEnabled ? 'badge-active' : 'badge-inactive'; ?>"><?= $flag->isEnabled ? 'On' : 'Off'; ?></span></td>
                            <td><?= esc(json_encode($flag->rolloutRules)); ?></td>
                            <td><?= esc($flag->updatedAt); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
