<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Identity\Models\User $user
 * @var string $error
 * @var string $flashMessage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit profile | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Update your SA Business Distribution account details.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content auth-page">
        <section class="auth-card">
            <h1>Edit account details</h1>
            <p>Keep your contact and company information up to date.</p>

            <?php if ($flashMessage): ?>
                <div class="flash-message success"><?= esc($flashMessage); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash-message error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" action="account-edit.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" value="<?= esc($user->firstName); ?>" required>

                <label for="last_name">Last name</label>
                <input id="last_name" name="last_name" type="text" value="<?= esc($user->lastName); ?>" required>

                <label for="company_name">Company</label>
                <input id="company_name" name="company_name" type="text" value="<?= esc($user->companyName ?? ''); ?>">

                <label for="phone">Phone number</label>
                <input id="phone" name="phone" type="tel" value="<?= esc($user->phone); ?>" required>

                <label for="password">New password</label>
                <input id="password" name="password" type="password" placeholder="Leave blank to keep current password">

                <label for="password_confirm">Confirm new password</label>
                <input id="password_confirm" name="password_confirm" type="password" placeholder="Leave blank to keep current password">

                <div class="checkbox-field">
                    <label><input type="checkbox" name="notifications_updates" <?= $user->notificationsUpdates ? 'checked' : ''; ?>> Receive product updates</label>
                </div>

                <button class="btn-primary" type="submit">Save changes</button>
            </form>

            <p class="auth-helper"><a href="account-dashboard.php">Back to dashboard</a></p>
        </section>
    </main>
</body>
</html>
