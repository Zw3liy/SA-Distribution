<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';

$appConfig = require __DIR__ . '/config/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');

    if ($action === 'remove' && $slug !== '') {
        removeFromWishlist($slug);
        setFlashMessage('Item removed from wishlist.');
    }

    header('Location: wishlist.php');
    exit;
}

$flashMessage = getFlashMessage();
$wishlistItems = getWishlistItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="View saved products and move them to your cart or request a quote." />
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include __DIR__ . '/components/header.php'; ?>
    <main class="container page-content">
        <section class="products-hero">
            <div>
                <p class="eyebrow">Wishlist</p>
                <h1>Saved products</h1>
                <p>Keep track of items you want to review later or request a quote for.</p>
            </div>
        </section>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($wishlistItems)): ?>
            <div class="empty-cart">
                <h2>No saved items yet</h2>
                <p>Add products to your wishlist to compare them later.</p>
                <a class="btn-primary" href="products.php">Browse products</a>
            </div>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlistItems as $item): ?>
                    <article class="wishlist-card">
                        <?php if (!empty($item['thumbnail'])): ?>
                            <img src="<?= esc($item['thumbnail']); ?>" alt="<?= esc($item['name']); ?>">
                        <?php else: ?>
                            <div class="product-card-placeholder">Image unavailable</div>
                        <?php endif; ?>
                        <div class="wishlist-card-body">
                            <h3><?= esc($item['name']); ?></h3>
                            <p class="product-sku">SKU: <?= esc($item['sku']); ?></p>
                            <div class="wishlist-actions">
                                <a class="btn-outline btn-card" href="product-details.php?slug=<?= esc($item['slug']); ?>">View details</a>
                                <form method="post" action="wishlist.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="slug" value="<?= esc($item['slug']); ?>">
                                    <button class="btn-outline" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/components/footer.php'; ?>
</body>
</html>
