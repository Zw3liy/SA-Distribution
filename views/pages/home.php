<?php
declare(strict_types=1);

/** @var array $appConfig */
/** @var array $featuredProducts */
/** @var array $categories */
$seoTitle = seo_title('Business Technology, Stock and Service');
$seoDescription = 'Shop computers, networking, security, printing and business technology with nationwide delivery, volume pricing and expert support.';
$categoryIcons = ['bi-laptop', 'bi-phone', 'bi-router', 'bi-camera-video', 'bi-printer', 'bi-server', 'bi-keyboard', 'bi-headset'];
$featuredProducts = $featuredProducts ?? [];
$categories = $categories ?? [
    ['name' => 'Laptops'],
    ['name' => 'Mobile & Apple'],
    ['name' => 'Networking'],
    ['name' => 'CCTV & Security'],
    ['name' => 'Office & Printing'],
    ['name' => 'Servers & Storage'],
    ['name' => 'Components'],
    ['name' => 'Accessories'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <link rel="canonical" href="<?= esc($appConfig['base_url']); ?>">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include APP_BASE_PATH . '/components/header.php'; ?>

    <main>
        <section class="sabd-intro">
            <div class="container sabd-intro-grid">
                <div class="sabd-intro-copy">
                    <span class="sabd-label">South African business technology</span>
                    <h1>Stock. Price. Service.<br><span>Technology you can rely on.</span></h1>
                    <p>One accountable partner for devices, infrastructure and technical support—from a single workstation to a nationwide rollout.</p>
                    <div class="hero-actions">
                        <a class="btn-primary" href="products.php">Shop the catalogue</a>
                        <a class="btn-ghost" href="quote-request.php">Request project pricing</a>
                    </div>
                </div>
                <div class="sabd-promo-stack" aria-label="Current promotions">
                    <a class="sabd-promo-main" href="products.php?sort=featured">
                        <span>Featured business deals</span>
                        <strong>Upgrade the office without slowing it down.</strong>
                        <small>Shop selected technology <i class="bi bi-arrow-right"></i></small>
                    </a>
                    <div class="sabd-promo-pair">
                        <a href="products.php?search=laptop"><i class="bi bi-laptop"></i><span>Mobile workforce</span><strong>Business laptops</strong></a>
                        <a href="products.php?search=network"><i class="bi bi-diagram-3"></i><span>Connected teams</span><strong>Network essentials</strong></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="sabd-service-strip" aria-label="Customer benefits">
            <div class="container">
                <div><i class="bi bi-truck"></i><span><strong>Nationwide delivery</strong><small>Reliable fulfilment across South Africa</small></span></div>
                <div><i class="bi bi-receipt"></i><span><strong>Business pricing</strong><small>Quotes for volume and projects</small></span></div>
                <div><i class="bi bi-shield-check"></i><span><strong>Trusted supply</strong><small>Warranty-backed global brands</small></span></div>
                <div><i class="bi bi-headset"></i><span><strong>Technical support</strong><small>Guidance before and after purchase</small></span></div>
            </div>
        </section>

        <section class="container sabd-category-section">
            <div class="sabd-section-title">
                <div><span>Find it faster</span><h2>Shop technology departments</h2></div>
                <a href="products.php">Browse the full catalogue <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="sabd-category-rail">
                <?php foreach (array_slice($categories, 0, 8) as $index => $category): ?>
                    <?php
                    $name = is_array($category) ? (string) ($category['name'] ?? 'Technology') : (string) $category;
                    $id = is_array($category) ? ($category['id'] ?? null) : null;
                    $href = $id !== null ? 'products.php?category=' . urlencode((string) $id) : 'products.php?search=' . urlencode($name);
                    ?>
                    <a href="<?= esc($href); ?>">
                        <span><i class="bi <?= esc($categoryIcons[$index % count($categoryIcons)]); ?>"></i></span>
                        <strong><?= esc($name); ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="sabd-deals-section">
            <div class="container">
                <div class="sabd-section-title">
                    <div><span>Selected for business</span><h2>Featured technology</h2></div>
                    <a href="products.php?sort=featured">See all products <i class="bi bi-arrow-right"></i></a>
                </div>
                <?php if ($featuredProducts !== []): ?>
                    <div class="product-grid sabd-home-products">
                        <?php foreach (array_slice($featuredProducts, 0, 4) as $product): ?>
                            <?php include APP_BASE_PATH . '/components/product-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="sabd-empty-products">
                        <i class="bi bi-box-seam"></i>
                        <div><strong>Our catalogue is being prepared.</strong><p>Browse all products or request a tailored procurement quote.</p></div>
                        <a class="btn-primary" href="products.php">Browse catalogue</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="container sabd-solutions">
            <div class="sabd-section-title">
                <div><span>Beyond the product box</span><h2>Solutions built around your business</h2></div>
            </div>
            <div class="business-grid">
                <article><i class="bi bi-buildings"></i><h3>Corporate procurement</h3><p>Consolidate hardware, accessories and infrastructure through one accountable supplier.</p><a href="quote-request.php">Request a quote</a></article>
                <article><i class="bi bi-boxes"></i><h3>Rollouts and bulk orders</h3><p>Coordinate quantity pricing, product alternatives and delivery across teams and locations.</p><a href="products.php">Build an order</a></article>
                <article><i class="bi bi-tools"></i><h3>Infrastructure and support</h3><p>Add configuration, deployment and ongoing technical services to your technology purchase.</p><a href="#contact">Talk to a specialist</a></article>
            </div>
        </section>

        <section class="sabd-brand-strip">
            <div class="container"><span>Technology from brands businesses trust</span><strong>HP</strong><strong>Lenovo</strong><strong>Dell</strong><strong>ASUS</strong><strong>Ubiquiti</strong><strong>Microsoft</strong></div>
        </section>
    </main>

    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
    <script type="module" src="js/main.js"></script>
</body>
</html>
