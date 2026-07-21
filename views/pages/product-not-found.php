<?php
declare(strict_types=1);

/** @var array $appConfig */

$seoTitle = 'Product not found | ' . $appConfig['name'];
$seoDescription = 'The requested product could not be found. Browse all products at SA Business Distribution.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content page-error">
        <section class="empty-state">
            <h1>Product not found</h1>
            <p>The product you are looking for is unavailable or may have been removed.</p>
            <a class="btn-primary" href="products.php">Browse products</a>
        </section>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
