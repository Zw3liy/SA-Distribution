<?php
declare(strict_types=1);

/** @var array $appConfig */

$seoTitle = seo_title('Enterprise IT Solutions');
$seoDescription = seo_description();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <meta name="keywords" content="enterprise IT, business laptops, servers, networking, cybersecurity, managed services">
    <link rel="canonical" href="<?= esc($appConfig['base_url']); ?>">
    <meta property="og:title" content="<?= esc($seoTitle); ?>">
    <meta property="og:description" content="<?= esc($seoDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= esc($appConfig['base_url']); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= esc($seoTitle); ?>">
    <meta name="twitter:description" content="<?= esc($seoDescription); ?>">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>

    <main>
        <section class="hero-section">
            <div class="hero-content container">
                <span class="eyebrow">Enterprise Technology Distribution</span>
                <h1>Premium IT hardware, networking and managed services for business.</h1>
                <p>Delivering trusted procurement, design, deployment, and support for South African enterprises.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="products.php">Explore Products</a>
                    <a class="btn-outline" href="products.php">View products</a>
                </div>
            </div>
        </section>

        <section class="category-section container" aria-labelledby="featured-categories-title">
            <div class="section-header">
                <p class="section-label">Featured categories</p>
                <h2 id="featured-categories-title">Solutions for modern business infrastructure</h2>
            </div>
            <div class="category-grid">
                <article class="category-card">
                    <img src="images/laptops.webp" alt="Business laptops" loading="lazy">
                    <div>
                        <h3>Business Laptops</h3>
                        <p>Secure workstations for hybrid teams and corporate users.</p>
                    </div>
                </article>
                <article class="category-card">
                    <img src="images/servers.webp" alt="Servers and storage" loading="lazy">
                    <div>
                        <h3>Servers & Storage</h3>
                        <p>Enterprise-grade compute and data infrastructure for scale.</p>
                    </div>
                </article>
                <article class="category-card">
                    <img src="images/networking.webp" alt="Networking equipment" loading="lazy">
                    <div>
                        <h3>Networking</h3>
                        <p>Secure switching, routing, and wireless systems for business.</p>
                    </div>
                </article>
                <article class="category-card">
                    <img src="images/security.webp" alt="Security solutions" loading="lazy">
                    <div>
                        <h3>Cyber Security</h3>
                        <p>Protect infrastructure with enterprise firewalls, CCTV and access control.</p>
                    </div>
                </article>
            </div>
        </section>
    </main>

    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
    <script type="module" src="js/main.js"></script>
</body>
</html>
