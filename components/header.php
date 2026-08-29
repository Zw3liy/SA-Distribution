<?php
declare(strict_types=1);

/** @var array $appConfig */
$baseUrl = rtrim((string) $appConfig['base_url'], '/') . '/';
?>
<header class="retail-header">
    <div class="retail-topbar">
        <div class="container topbar-inner">
            <span>South Africa's business technology store</span>
            <nav aria-label="Utility navigation">
                <a href="<?= esc($baseUrl); ?>quote-request.php">Request a quote</a>
                <a href="#contact">Business support</a>
                <span>Secure nationwide delivery</span>
            </nav>
        </div>
    </div>

    <div class="retail-mainbar">
        <div class="container mainbar-inner">
            <a class="retail-brand" href="<?= esc($baseUrl); ?>" aria-label="SA Business Distribution home">
                <span class="brand-mark">SA</span>
                <span class="brand-copy">
                    <strong>SA Distribution</strong>
                    <small>Enterprise IT &amp; Business Solutions</small>
                </span>
            </a>

            <form class="header-search" action="<?= esc($baseUrl); ?>products.php" method="get" role="search">
                <label class="sr-only" for="store-search">Search the catalogue</label>
                <input id="store-search" name="search" type="search" placeholder="Search products, brands or SKUs" autocomplete="off">
                <button type="submit" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
            </form>

            <nav class="account-actions" aria-label="Account and basket">
                <a href="<?= esc($baseUrl); ?>wishlist.php" aria-label="Wishlist">
                    <i class="bi bi-heart" aria-hidden="true"></i><span>Wishlist</span><b><?= getWishlistCount(); ?></b>
                </a>
                <a href="<?= esc($baseUrl); ?>cart.php" aria-label="Shopping cart">
                    <i class="bi bi-cart3" aria-hidden="true"></i><span>Cart</span><b><?= getCartCount(); ?></b>
                </a>
                <?php if (isAuthenticated()): ?>
                    <a href="<?= esc($baseUrl); ?>account-dashboard.php"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Account</span></a>
                <?php else: ?>
                    <a href="<?= esc($baseUrl); ?>login.php"><i class="bi bi-person" aria-hidden="true"></i><span>Sign in</span></a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <div class="category-nav-wrap">
        <nav class="container category-nav" aria-label="Product categories">
            <a class="category-all" href="<?= esc($baseUrl); ?>products.php"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Shop all</a>
            <a href="<?= esc($baseUrl); ?>products.php?search=laptop">Laptops</a>
            <a href="<?= esc($baseUrl); ?>products.php?search=desktop">PCs &amp; Components</a>
            <a href="<?= esc($baseUrl); ?>products.php?search=network">Networking</a>
            <a href="<?= esc($baseUrl); ?>products.php?search=security">CCTV &amp; Security</a>
            <a href="<?= esc($baseUrl); ?>products.php?search=printer">Office &amp; Printing</a>
            <a href="<?= esc($baseUrl); ?>products.php?sort=featured" class="nav-deals"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Deals</a>
        </nav>
    </div>
</header>
