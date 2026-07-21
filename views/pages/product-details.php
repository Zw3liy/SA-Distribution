<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Models\Product $product
 * @var array $productImages
 * @var array $relatedProducts
 * @var array $recentlyViewed
 */

$seoTitle = sprintf('%s | %s', $product->name, $appConfig['name']);
$seoDescription = $product->shortDescription;
$canonical = $appConfig['base_url'] . 'product-details.php?slug=' . urlencode($product->slug);
$rating = 4.7;
$reviewCount = 38;
$warrantyText = '3-year onsite support with optional extended maintenance and service-level agreements tailored for South African enterprises.';
$downloads = [
    ['label' => 'Product brochure', 'url' => '#'],
    ['label' => 'Technical datasheet', 'url' => '#'],
    ['label' => 'Warranty terms', 'url' => '#'],
];
$features = [
    'Business-grade security and hardware encryption',
    'Local support and installation services',
    'Optimised performance for enterprise workloads',
    'Sustainable power and cooling design',
];
$reviews = [
    [
        'author' => 'Nthabiseng M.',
        'rating' => 5,
        'title' => 'Ideal for our hybrid office',
        'body' => 'The laptop performs reliably under heavy virtual desktop workloads and the local support package helped us deploy quickly.',
        'date' => '2026-03-09',
    ],
    [
        'author' => 'Sipho D.',
        'rating' => 4,
        'title' => 'Strong build and excellent security',
        'body' => 'The ThinkPad is robust, secure, and the battery life is ideal for business travel. Slightly heavier than expected but worth it.',
        'date' => '2026-04-11',
    ],
    [
        'author' => 'Karen P.',
        'rating' => 4,
        'title' => 'Good value for enterprise use',
        'body' => 'The device gave us confidence with advanced management features at a competitive price.',
        'date' => '2026-05-02',
    ],
];
$financeRate = 0.12;
$financeTerm = 36;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="description" content="<?= esc($seoDescription); ?>">
    <meta property="og:title" content="<?= esc($seoTitle); ?>">
    <meta property="og:description" content="<?= esc($seoDescription); ?>">
    <meta property="og:type" content="product">
    <link rel="canonical" href="<?= esc($canonical); ?>">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'image' => array_map(function ($image) {
            return 'images/' . $image['file_name'];
        }, $productImages ?: [['file_name' => basename($product->thumbnail ?? '')]]),
        'description' => $product->shortDescription,
        'sku' => $product->sku,
        'brand' => ['@type' => 'Brand', 'name' => $product->brandName],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'ZAR',
            'price' => $product->salePrice !== null ? number_format($product->salePrice, 2, '.', '') : number_format($product->price, 2, '.', ''),
            'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => $canonical,
        ],
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'reviewCount' => $reviewCount,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= esc($appConfig['base_url']); ?>">Home</a>
            <span aria-hidden="true">/</span>
            <a href="products.php">Products</a>
            <span aria-hidden="true">/</span>
            <span><?= esc($product->name); ?></span>
        </nav>

        <?php if ($flash = getFlashMessage()): ?>
            <div class="flash-message flash-message--product"><?= esc($flash); ?></div>
        <?php endif; ?>

        <section class="product-detail-header">
            <div class="product-detail-grid">
                <div class="product-gallery" aria-label="Product image gallery">
                    <div class="product-main-image">
                        <?php if (!empty($productImages)): ?>
                            <img id="product-main-image" src="images/<?= esc($productImages[0]['file_name']); ?>" alt="<?= esc($productImages[0]['alt_text'] ?: $product->name); ?>">
                        <?php elseif ($product->thumbnail !== null): ?>
                            <img id="product-main-image" src="<?= esc($product->thumbnail); ?>" alt="<?= esc($product->name); ?>">
                        <?php else: ?>
                            <div class="product-card-placeholder">Image unavailable</div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($productImages) && count($productImages) > 1): ?>
                        <div class="product-thumbnails" role="list">
                            <?php foreach ($productImages as $index => $image): ?>
                                <button type="button" class="thumbnail-button <?= $index === 0 ? 'active' : ''; ?>" data-image="images/<?= esc($image['file_name']); ?>" data-alt="<?= esc($image['alt_text'] ?: $product->name); ?>">
                                    <img src="images/<?= esc($image['file_name']); ?>" alt="<?= esc($image['alt_text'] ?: $product->name); ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="product-details">
                    <p class="eyebrow"><?= esc($product->brandName); ?> • <?= esc($product->categoryName); ?></p>
                    <h1><?= esc($product->name); ?></h1>
                    <p class="product-sku"><strong>SKU:</strong> <?= esc($product->sku); ?></p>
                    <div class="product-pricing-detail">
                        <?php if ($product->salePrice !== null): ?>
                            <span class="price price-sale">R<?= number_format($product->salePrice, 2); ?></span>
                            <span class="price price-original">R<?= number_format($product->price, 2); ?></span>
                        <?php else: ?>
                            <span class="price">R<?= number_format($product->price, 2); ?></span>
                        <?php endif; ?>
                        <span class="stock <?= $product->stock > 0 ? 'stock-in' : 'stock-out'; ?>">
                            <?= $product->stock > 0 ? 'In stock' : 'Out of stock'; ?>
                        </span>
                    </div>
                    <p class="product-copy"><?= esc($product->description); ?></p>
                    <div class="product-actions">
                        <form method="post" action="cart.php" class="product-action-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="slug" value="<?= esc($product->slug); ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn-primary" type="submit" <?= $product->stock > 0 ? '' : 'disabled'; ?>>Add to cart</button>
                        </form>
                        <form method="post" action="cart.php" class="product-action-form">
                            <input type="hidden" name="action" value="wishlist">
                            <input type="hidden" name="slug" value="<?= esc($product->slug); ?>">
                            <button class="btn-outline" type="submit">Add to wishlist</button>
                        </form>
                    </div>
                    <div class="product-meta-list">
                        <div>
                            <p class="meta-label">Warranty</p>
                            <p><?= esc($warrantyText); ?></p>
                        </div>
                        <div>
                            <p class="meta-label">Available from</p>
                            <p><?= date('F Y', strtotime($product->createdAt)); ?></p>
                        </div>
                    </div>
                    <div class="product-cta-grid">
                        <button class="btn-outline btn-share" type="button" data-copy-label="Copy link">Copy product link</button>
                        <a class="btn-outline" href="#quote-section">Request a quote</a>
                        <button class="btn-outline" type="button" id="compare-button">Compare</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="product-detail-support">
            <div class="support-card">
                <h2>Product highlights</h2>
                <ul>
                    <?php foreach ($features as $feature): ?>
                        <li><?= esc($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="support-card">
                <h2>Tech specs</h2>
                <dl>
                    <div>
                        <dt>SKU</dt>
                        <dd><?= esc($product->sku); ?></dd>
                    </div>
                    <div>
                        <dt>Brand</dt>
                        <dd><?= esc($product->brandName); ?></dd>
                    </div>
                    <div>
                        <dt>Category</dt>
                        <dd><?= esc($product->categoryName); ?></dd>
                    </div>
                    <div>
                        <dt>Stock status</dt>
                        <dd><?= $product->stock > 0 ? 'Available' : 'Out of stock'; ?></dd>
                    </div>
                    <div>
                        <dt>Delivery</dt>
                        <dd>Local delivery across South Africa within 3–7 business days</dd>
                    </div>
                </dl>
            </div>
            <div class="support-card">
                <h2>Downloads</h2>
                <ul>
                    <?php foreach ($downloads as $download): ?>
                        <li><a href="<?= esc($download['url']); ?>"><?= esc($download['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="calculator-section" id="quote-section">
            <div class="calculator-header">
                <div>
                    <p class="section-label">Finance & rental</p>
                    <h2>Estimate your monthly investment</h2>
                </div>
                <p class="calculator-copy">Use this quick calculator to compare monthly financing with your budget and request a quote from our team.</p>
            </div>
            <div class="calculator-grid">
                <div class="calculator-card">
                    <label for="finance-term">Term (months)</label>
                    <input id="finance-term" type="number" min="6" max="60" value="<?= $financeTerm; ?>">
                </div>
                <div class="calculator-card">
                    <label for="finance-rate">Interest rate (%)</label>
                    <input id="finance-rate" type="number" min="0" max="24" step="0.1" value="<?= ($financeRate * 100); ?>">
                </div>
                <div class="calculator-result">
                    <p>Estimated monthly payment</p>
                    <strong id="monthly-payment">R0.00</strong>
                    <p class="calculator-footnote">Based on listed price and APR. Actual terms available via quote.</p>
                </div>
            </div>
            <div class="quote-form-wrapper">
                <h3>Request a quote</h3>
                <form method="post" action="quote-request.php" class="quote-form">
                    <input type="hidden" name="product_slug" value="<?= esc($product->slug); ?>">
                    <input type="hidden" name="product_name" value="<?= esc($product->name); ?>">
                    <div class="quote-grid">
                        <label>
                            Name
                            <input type="text" name="name" required>
                        </label>
                        <label>
                            Company
                            <input type="text" name="company">
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" required>
                        </label>
                        <label>
                            Phone
                            <input type="tel" name="phone">
                        </label>
                    </div>
                    <label>
                        Message
                        <textarea name="message" rows="4" placeholder="Tell us your delivery preference, quantity and timeline."></textarea>
                    </label>
                    <button class="btn-primary" type="submit">Submit quote request</button>
                </form>
            </div>
        </section>

        <section class="product-reviews">
            <div class="section-header">
                <p class="section-label">Customer reviews</p>
                <h2>What customers say</h2>
            </div>
            <div class="review-grid">
                <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <div class="review-stars" aria-label="Rating: <?= esc((string) $review['rating']); ?> out of 5 stars">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <span class="star <?= $star <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <h3><?= esc($review['title']); ?></h3>
                        <p class="review-body"><?= esc($review['body']); ?></p>
                        <p class="review-meta"><strong><?= esc($review['author']); ?></strong> · <?= esc($review['date']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($relatedProducts)): ?>
            <section class="related-products">
                <div class="section-header">
                    <p class="section-label">Related products</p>
                    <h2>Customers also view</h2>
                </div>
                <div class="product-grid">
                    <?php foreach ($relatedProducts as $related): ?>
                        <?php $product = $related; ?>
                        <?php include APP_BASE_PATH . '/views/products/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($recentlyViewed)): ?>
            <section class="related-products">
                <div class="section-header">
                    <p class="section-label">Recently viewed</p>
                    <h2>Your recent products</h2>
                </div>
                <div class="product-grid">
                    <?php foreach ($recentlyViewed as $recent): ?>
                        <article class="product-card">
                            <?php if ($recent['thumbnail'] !== null): ?>
                                <img src="<?= esc($recent['thumbnail']); ?>" alt="<?= esc($recent['name']); ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="product-card-placeholder">Image unavailable</div>
                            <?php endif; ?>
                            <div class="product-card-body">
                                <p class="product-meta"><?= esc($recent['brand_name']); ?> • <?= esc($recent['category_name']); ?></p>
                                <h3 class="product-title"><?= esc($recent['name']); ?></h3>
                                <p class="product-sku">SKU: <?= esc($recent['sku']); ?></p>
                                <div class="product-pricing">
                                    <?php if ($recent['sale_price'] !== null): ?>
                                        <span class="price price-sale">R<?= number_format((float) $recent['sale_price'], 2); ?></span>
                                        <span class="price price-original">R<?= number_format((float) $recent['price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="price">R<?= number_format((float) $recent['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="btn-outline btn-card" href="product-details.php?slug=<?= esc($recent['slug']); ?>">View details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
