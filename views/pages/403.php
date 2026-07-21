<?php
declare(strict_types=1);

/** @var array $appConfig */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access denied | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="You do not have permission to view this page.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content page-error">
        <section class="empty-state">
            <h1>Access denied</h1>
            <p>You don't have permission to view this page.</p>
            <a class="btn-primary" href="/">Back to home</a>
        </section>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
