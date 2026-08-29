<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var array $products
 * @var array $categories
 * @var array $brands
 * @var string $search
 * @var int|null $selectedCategory
 * @var int|null $selectedBrand
 * @var string $sort
 * @var int $currentPage
 * @var int $perPage
 * @var int $totalProducts
 * @var int $totalPages
 */

$seoTitle = 'Shop Technology | ' . $appConfig['name'];
$seoDescription = 'Compare business technology by category, brand, price and availability from SA Business Distribution.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <link rel="canonical" href="<?= esc($appConfig['base_url'] . 'products.php'); ?>">
    <meta property="og:title" content="<?= esc($seoTitle); ?>">
    <meta property="og:description" content="<?= esc($seoDescription); ?>">
    <meta property="og:type" content="website">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include APP_BASE_PATH . '/components/header.php'; ?>

    <main class="container page-content">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= esc($appConfig['base_url']); ?>">Home</a><span>/</span><span>Technology catalogue</span>
        </nav>

        <section class="catalogue-banner">
            <div>
                <span class="deal-kicker"><i class="bi bi-lightning-charge-fill"></i> Live catalogue</span>
                <h1>Find the right technology</h1>
                <p>Compare business hardware, pricing and availability across our South African catalogue.</p>
            </div>
            <div class="catalogue-assurance"><i class="bi bi-building-check"></i><span><strong>Buying for business?</strong><small>Ask for quantity and project pricing.</small></span><a href="quote-request.php">Request quote</a></div>
        </section>

        <div class="catalogue-layout">
            <aside class="catalogue-sidebar">
                <form method="get" action="products.php" aria-label="Product filters">
                    <div class="sidebar-title"><strong>Filter products</strong><a href="products.php">Clear all</a></div>

                    <div class="sidebar-filter">
                        <label for="catalogue-search">Search</label>
                        <div class="sidebar-search"><input id="catalogue-search" name="search" type="search" value="<?= esc($search); ?>" placeholder="Name, SKU or keyword"><i class="bi bi-search"></i></div>
                    </div>

                    <div class="sidebar-filter">
                        <label for="category">Department</label>
                        <select id="category" name="category">
                            <option value="">All departments</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= esc((string) $category['id']); ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : ''; ?>><?= esc($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sidebar-filter">
                        <label for="brand">Brand</label>
                        <select id="brand" name="brand">
                            <option value="">All brands</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= esc((string) $brand['id']); ?>" <?= $selectedBrand === (int) $brand['id'] ? 'selected' : ''; ?>><?= esc($brand['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="sort" value="<?= esc($sort); ?>">
                    <button type="submit" class="btn-primary sidebar-apply">Apply filters</button>
                </form>

                <div class="sidebar-help"><i class="bi bi-headset"></i><div><strong>Need product advice?</strong><p>Our team can match specifications to your workload and budget.</p><a href="quote-request.php">Talk to an expert</a></div></div>
            </aside>

            <section class="catalogue-results" aria-label="Product results">
                <div class="catalogue-toolbar">
                    <div><strong><?= esc((string) $totalProducts); ?> products</strong><?php if ($search !== ''): ?><span>for “<?= esc($search); ?>”</span><?php endif; ?></div>
                    <form method="get" action="products.php" class="sort-form">
                        <input type="hidden" name="search" value="<?= esc($search); ?>">
                        <input type="hidden" name="category" value="<?= esc((string) $selectedCategory); ?>">
                        <input type="hidden" name="brand" value="<?= esc((string) $selectedBrand); ?>">
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="featured" <?= $sort === 'featured' ? 'selected' : ''; ?>>Featured</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : ''; ?>>Price: low to high</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : ''; ?>>Price: high to low</option>
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A–Z</option>
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest arrivals</option>
                        </select>
                        <noscript><button type="submit">Sort</button></noscript>
                    </form>
                </div>

                <div class="catalogue-delivery-note"><i class="bi bi-truck"></i><span>Delivery estimates and stock availability are confirmed during checkout or quotation.</span></div>

                <div class="product-grid takealot-grid">
                    <?php if (empty($products)): ?>
                        <div class="empty-state"><i class="bi bi-search"></i><h2>No matching products</h2><p>Try a broader search or clear your filters.</p><a class="btn-primary" href="products.php">View all products</a></div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php include APP_BASE_PATH . '/views/products/product-card.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Product pagination">
                        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                            <a href="<?= esc(sprintf('products.php?page=%d&search=%s&category=%s&brand=%s&sort=%s', $page, urlencode($search), (string) $selectedCategory, (string) $selectedBrand, $sort)); ?>" class="pagination-link <?= $page === $currentPage ? 'active' : ''; ?>"><?= esc((string) $page); ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
