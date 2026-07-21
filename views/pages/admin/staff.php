<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Identity\Models\User[] $staff
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Staff | ' . $appConfig['name'];
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
        <h1>Staff accounts</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <h2>Add staff account</h2>
        <form method="post" action="/admin/staff" class="product-action-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <input type="hidden" name="form_action" value="create">
            <div class="filters-grid">
                <div class="filter-field">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="filter-field">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                <div class="filter-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="filter-field">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                <div class="filter-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn-primary">Create staff account</button>
        </form>

        <h2>Existing staff</h2>
        <?php if (empty($staff)): ?>
            <div class="empty-state"><p>No staff accounts yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><?= esc($member->getFullName()); ?></td>
                            <td><?= esc($member->email); ?></td>
                            <td><span class="badge <?= $member->isActive ? 'badge-active' : 'badge-inactive'; ?>"><?= $member->isActive ? 'Active' : 'Inactive'; ?></span></td>
                            <td><?= esc($member->createdAt); ?></td>
                            <td>
                                <form method="post" action="/admin/staff">
                                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                    <input type="hidden" name="form_action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $member->id; ?>">
                                    <input type="hidden" name="is_active" value="<?= $member->isActive ? '0' : '1'; ?>">
                                    <button type="submit" class="btn-outline"><?= $member->isActive ? 'Deactivate' : 'Activate'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
