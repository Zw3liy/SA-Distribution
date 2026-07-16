<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/controllers/AuthController.php';

requireGuest();

$appConfig = require __DIR__ . '/config/app.php';
$userRepository = new UserRepository($db);
$authService = new AuthService($userRepository);
$authController = new AuthController($authService);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = trim((string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW) ?: '');
        if (!verify_csrf_token($token)) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $authController->handleLogin();
        header('Location: account-dashboard.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Customer login for SA Business Distribution.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include __DIR__ . '/components/header.php'; ?>
    <main class="container page-content auth-page">
        <section class="auth-card">
            <h1>Welcome back</h1>
            <p>Secure access to your account, quotes, address book, and corporate purchasing tools.</p>

            <?php if ($flashMessage): ?>
                <div class="flash-message success"><?= esc($flashMessage); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash-message error"><?= esc($error); ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <label for="email">Business email</label>
                <input id="email" name="email" type="email" placeholder="you@company.co.za" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="Enter your password" required>

                <button class="btn-primary" type="submit">Sign in</button>
            </form>

            <p class="auth-helper">New to SA Business Distribution? <a href="register.php">Create an account</a></p>
        </section>
    </main>
</body>
</html>
