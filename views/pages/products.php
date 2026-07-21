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

$seoTitle = 'Products | ' . $appConfig['name'];
$seoDescription = 'Browse enterprise-grade IT hardware, networking equipment, cybersecurity tools, and business infrastructure from SA Business Distribution.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <meta name="keywords" content="business laptops, servers, networking, cybersecurity, enterprise IT, products, SA Business Distribution">
    <link rel="canonical" href="<?= esc($appConfig['base_url'] . 'products.php'); ?>">
    <meta property="og:title" content="<?= esc($seoTitle); ?>">
    <meta property="og:description" content="<?= esc($seoDescription); ?>">
    <meta property="og:type" content="website">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content">
        <section class="products-hero">
            <div>
                <p class="eyebrow">Product catalog</p>
                <h1>Discover tailored IT products for every business need</h1>
                <p>Filter by category, brand, availability, and sort by price, name, newest, or featured items.</p>
            </div>
        </section>
        <section class="product-filters">
            <form method="get" action="products.php" class="filters-grid" aria-label="Product filters">
                <div class="filter-field">
                    <label for="search">Search products</label>
                    <input id="search" name="search" type="search" value="<?= esc($search); ?>" placeholder="Search by name, SKU or description">
                </div>
                <div class="filter-field">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= esc((string) $category['id']); ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?= esc($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="brand">Brand</label>
                    <select id="brand" name="brand">
                        <option value="">All brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= esc((string) $brand['id']); ?>" <?= $selectedBrand === (int) $brand['id'] ? 'selected' : ''; ?>>
                                <?= esc($brand['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="sort">Sort by</label>
                    <select id="sort" name="sort">
                        <option value="featured" <?= $sort === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : ''; ?>>Price: low to high</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : ''; ?>>Price: high to low</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest arrivals</option>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <button type="submit" class="btn-primary">Apply filters</button>
                    <a class="btn-outline" href="products.php">Reset</a>
                </div>
            </form>
        </section>
        <section class="product-summary">
            <p><?= esc((string) $totalProducts); ?> products found</p>
            <p>Page <?= esc((string) $currentPage); ?> of <?= esc((string) $totalPages); ?></p>
        </section>
        <section class="product-grid" aria-label="Product results">
            <?php if (empty($products)): ?>
                <p class="empty-state">No products matched your search. Try a broader filter or a different keyword.</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php include APP_BASE_PATH . '/views/products/product-card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Product pagination">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <a href="<?= esc(sprintf('products.php?page=%d&search=%s&category=%s&brand=%s&sort=%s', $page, urlencode($search), (string) $selectedCategory, (string) $selectedBrand, $sort)); ?>" class="pagination-link <?= $page === $currentPage ? 'active' : ''; ?>">
                        <?= esc((string) $page); ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
