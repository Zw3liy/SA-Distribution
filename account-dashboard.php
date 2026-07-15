<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/controllers/AccountController.php';

requireLogin();

$appConfig = require __DIR__ . '/config/app.php';
$userRepository = new UserRepository($db);
$userService = new UserService($userRepository);
$accountController = new AccountController($userService);

$userId = currentUserId();
$userData = $accountController->getDashboardData($userId);
$user = $userData['user'];

$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account dashboard | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Your SA Business Distribution account dashboard.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include __DIR__ . '/components/header.php'; ?>
    <main class="container page-content account-page">
        <section class="account-hero">
            <div>
                <p class="eyebrow">Account dashboard</p>
                <h1>Welcome, <?= esc($user->getFullName()); ?></h1>
                <p>Access your profile, saved addresses, quote history, and account settings.</p>
            </div>
        </section>

        <?php if ($flashMessage): ?>
            <div class="flash-message success"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <div class="account-summary-grid">
            <div class="account-summary-card">
                <h2>Account details</h2>
                <p><strong>Email:</strong> <?= esc($user->email); ?></p>
                <p><strong>Phone:</strong> <?= esc($user->phone); ?></p>
                <p><strong>Company:</strong> <?= esc($user->companyName ?? 'Not specified'); ?></p>
                <p><strong>Status:</strong> <?= $user->isVerified ? 'Verified' : 'Pending verification'; ?></p>
            </div>
            <div class="account-summary-card">
                <h2>Recent activity</h2>
                <p><strong>Last login:</strong> <?= esc($user->lastLoginAt ?? 'Never'); ?></p>
                <p><strong>Notifications:</strong></p>
                <ul>
                    <li>Marketing: <?= $user->notificationsMarketing ? 'Enabled' : 'Disabled'; ?></li>
                    <li>Updates: <?= $user->notificationsUpdates ? 'Enabled' : 'Disabled'; ?></li>
                </ul>
            </div>
        </div>

        <div class="account-actions">
            <a class="btn-secondary" href="account-edit.php">Edit profile</a>
            <a class="btn-outline" href="logout.php">Sign out</a>
        </div>
    </main>
</body>
</html>
