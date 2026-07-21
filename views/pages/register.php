<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var string $error
 * @var string $flashMessage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Create a new SA Business Distribution customer account.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content auth-page">
        <section class="auth-card">
            <h1>Create your business account</h1>
            <p>Register to request quotes, manage orders, and access your company account information.</p>

            <?php if ($flashMessage): ?>
                <div class="flash-message success"><?= esc($flashMessage); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash-message error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" action="register.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" required>

                <label for="last_name">Last name</label>
                <input id="last_name" name="last_name" type="text" required>

                <label for="company_name">Company</label>
                <input id="company_name" name="company_name" type="text" placeholder="Your company name">

                <label for="email">Business email</label>
                <input id="email" name="email" type="email" placeholder="you@company.co.za" required>

                <label for="phone">Phone number</label>
                <input id="phone" name="phone" type="tel" placeholder="012 345 6789" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>

                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" required>

                <div class="checkbox-field">
                    <label><input type="checkbox" name="notifications_updates" checked> Receive product updates</label>
                </div>

                <button class="btn-primary" type="submit">Create account</button>
            </form>

            <p class="auth-helper">Already have an account? <a href="login.php">Sign in</a></p>
        </section>
    </main>
</body>
</html>
