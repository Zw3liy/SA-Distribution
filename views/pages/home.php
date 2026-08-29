<?php
declare(strict_types=1);

/** @var array $appConfig */
$seoTitle = seo_title('Technology Deals for South African Business');
$seoDescription = 'Shop business laptops, networking, security, printing and enterprise technology with nationwide delivery and expert support.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <link rel="canonical" href="<?= esc($appConfig['base_url']); ?>">
    <meta property="og:title" content="<?= esc($seoTitle); ?>">
    <meta property="og:description" content="<?= esc($seoDescription); ?>">
    <meta property="og:type" content="website">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include APP_BASE_PATH . '/components/header.php'; ?>

    <main>
        <section class="commerce-hero">
            <div class="container hero-retail-grid">
                <div class="hero-retail-copy">
                    <span class="deal-kicker"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Business technology deals</span>
                    <h1>Serious technology.<br><span>Better business.</span></h1>
                    <p>Hardware, networking, security and managed IT—sourced for South African teams that cannot afford downtime.</p>
                    <div class="hero-actions">
                        <a class="btn-primary" href="products.php">Shop technology</a>
                        <a class="btn-ghost" href="quote-request.php">Get a business quote</a>
                    </div>
                    <ul class="hero-trust" aria-label="Customer benefits">
                        <li><i class="bi bi-truck"></i> Nationwide delivery</li>
                        <li><i class="bi bi-shield-check"></i> Trusted warranties</li>
                        <li><i class="bi bi-headset"></i> Expert support</li>
                    </ul>
                </div>
                <aside class="hero-deal-card" aria-label="Featured promotion">
                    <span class="deal-pill">WEEKLY BUSINESS DEAL</span>
                    <div class="deal-visual"><i class="bi bi-laptop" aria-hidden="true"></i></div>
                    <p class="deal-label">Enterprise-ready devices</p>
                    <h2>Equip your team for less</h2>
                    <p>Volume pricing and tailored procurement for growing businesses.</p>
                    <a href="products.php?search=laptop">View laptop deals <i class="bi bi-arrow-right"></i></a>
                </aside>
            </div>
        </section>

        <section class="benefit-strip" aria-label="Store benefits">
            <div class="container benefit-grid">
                <div><i class="bi bi-truck"></i><span><strong>Fast delivery</strong><small>Across South Africa</small></span></div>
                <div><i class="bi bi-receipt"></i><span><strong>Business quotes</strong><small>Volume and project pricing</small></span></div>
                <div><i class="bi bi-shield-lock"></i><span><strong>Secure shopping</strong><small>Protected accounts and checkout</small></span></div>
                <div><i class="bi bi-headset"></i><span><strong>Technical expertise</strong><small>Pre-sales and after-sales support</small></span></div>
            </div>
        </section>

        <section class="retail-section container">
            <div class="retail-section-heading">
                <div><span>Browse faster</span><h2>Shop by category</h2></div>
                <a href="products.php">View all categories <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="retail-category-grid">
                <a href="products.php?search=laptop"><i class="bi bi-laptop"></i><strong>Laptops</strong><small>Business, gaming &amp; mobile workstations</small></a>
                <a href="products.php?search=desktop"><i class="bi bi-pc-display"></i><strong>PCs &amp; Components</strong><small>Desktops, upgrades &amp; accessories</small></a>
                <a href="products.php?search=network"><i class="bi bi-router"></i><strong>Networking</strong><small>Wi-Fi, switching &amp; infrastructure</small></a>
                <a href="products.php?search=security"><i class="bi bi-camera-video"></i><strong>CCTV &amp; Security</strong><small>Cameras, access &amp; surveillance</small></a>
                <a href="products.php?search=printer"><i class="bi bi-printer"></i><strong>Office &amp; Printing</strong><small>Printers, consumables &amp; productivity</small></a>
                <a href="products.php?search=server"><i class="bi bi-server"></i><strong>Servers &amp; Storage</strong><small>Compute, backup &amp; data protection</small></a>
            </div>
        </section>

        <section class="flash-section">
            <div class="container">
                <div class="retail-section-heading light-heading">
                    <div><span>Limited-time pricing</span><h2><i class="bi bi-lightning-charge-fill"></i> Flash deals</h2></div>
                    <a href="products.php?sort=featured">Shop all deals <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="deal-grid">
                    <a href="products.php?search=laptop" class="promo-card promo-blue"><span>UP TO 20% OFF</span><i class="bi bi-laptop"></i><h3>Business laptops</h3><p>Performance and security for work anywhere.</p><b>Shop now →</b></a>
                    <a href="products.php?search=network" class="promo-card promo-cyan"><span>PROJECT PRICING</span><i class="bi bi-router"></i><h3>Networking</h3><p>Reliable infrastructure for connected teams.</p><b>Get connected →</b></a>
                    <a href="products.php?search=security" class="promo-card promo-orange"><span>SECURITY EVENT</span><i class="bi bi-camera-video"></i><h3>CCTV &amp; access</h3><p>Protect people, premises and critical assets.</p><b>Secure your site →</b></a>
                </div>
            </div>
        </section>

        <section class="retail-section container">
            <div class="retail-section-heading">
                <div><span>Procurement made simple</span><h2>Built for business buyers</h2></div>
            </div>
            <div class="business-grid">
                <article><i class="bi bi-building"></i><h3>Corporate procurement</h3><p>Centralise devices, accessories and infrastructure through one accountable technology partner.</p><a href="quote-request.php">Request pricing</a></article>
                <article><i class="bi bi-boxes"></i><h3>Bulk and project orders</h3><p>Get quantity-based pricing, delivery coordination and product alternatives aligned to your budget.</p><a href="products.php">Build your order</a></article>
                <article><i class="bi bi-tools"></i><h3>Deployment and support</h3><p>Extend the purchase with configuration, installation and ongoing managed services.</p><a href="#contact">Talk to an expert</a></article>
            </div>
        </section>

        <section class="brand-band">
            <div class="container"><span>Technology for every workload</span><strong>HP</strong><strong>Lenovo</strong><strong>Dell</strong><strong>ASUS</strong><strong>Ubiquiti</strong><strong>Microsoft</strong></div>
        </section>
    </main>

    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
    <script type="module" src="js/main.js"></script>
</body>
</html>
