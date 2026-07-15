<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

/** @var array $appConfig */
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= esc($appConfig['base_url']); ?>">SA Business Distribution</a>
        <nav class="site-nav" aria-label="Primary navigation">
            <a href="<?= esc($appConfig['base_url']); ?>">Home</a>
            <a href="products.php">Products</a>
            <a href="cart.php">Cart (<?= getCartCount(); ?>)</a>
            <a href="wishlist.php">Wishlist (<?= getWishlistCount(); ?>)</a>
            <a href="#contact">Contact</a>
            <?php if (isAuthenticated()): ?>
                <a href="account-dashboard.php">My Account</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <a class="btn-secondary" href="#contact">Request Quote</a>
        </div>
    </div>
</header>
