<?php
declare(strict_types=1);

/** @var Product $product */
$effectivePrice = $product->salePrice ?? $product->price;
$discountPercent = $product->salePrice !== null && $product->price > 0
    ? (int) round((1 - ($product->salePrice / $product->price)) * 100)
    : 0;
?>
<article class="product-card takealot-card">
    <a class="product-image-link" href="product-details.php?slug=<?= esc($product->slug); ?>" aria-label="View <?= esc($product->name); ?>">
        <div class="product-card-image">
            <?php if ($product->thumbnail !== null): ?>
                <img src="<?= esc($product->thumbnail); ?>" alt="<?= esc($product->name); ?>" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="product-card-placeholder"><i class="bi bi-image"></i><span>Image unavailable</span></div>
            <?php endif; ?>
            <div class="product-badges">
                <?php if ($discountPercent > 0): ?><span class="badge badge-sale"><?= $discountPercent; ?>% off</span><?php endif; ?>
                <?php if ($product->isNew): ?><span class="badge badge-new">New</span><?php endif; ?>
                <?php if ($product->isFeatured): ?><span class="badge badge-featured">Top pick</span><?php endif; ?>
            </div>
        </div>
    </a>

    <div class="product-card-body">
        <p class="product-meta"><?= esc($product->brandName); ?></p>
        <a class="product-title-link" href="product-details.php?slug=<?= esc($product->slug); ?>"><h3 class="product-title"><?= esc($product->name); ?></h3></a>
        <p class="product-sku">SKU: <?= esc($product->sku); ?> · <?= esc($product->categoryName); ?></p>
        <p class="product-description"><?= esc($product->shortDescription); ?></p>

        <div class="product-pricing takealot-pricing">
            <span class="price <?= $product->salePrice !== null ? 'price-sale' : ''; ?>">R <?= number_format($effectivePrice, 2); ?></span>
            <?php if ($product->salePrice !== null): ?><span class="price price-original">R <?= number_format($product->price, 2); ?></span><?php endif; ?>
        </div>

        <div class="product-fulfilment">
            <?php if ($product->stock > 0): ?>
                <span class="stock stock-in"><i class="bi bi-check-circle-fill"></i> In stock</span>
                <small><i class="bi bi-truck"></i> Nationwide delivery available</small>
            <?php else: ?>
                <span class="stock stock-out"><i class="bi bi-x-circle-fill"></i> Out of stock</span>
                <small>Request availability from our sales team</small>
            <?php endif; ?>
        </div>

        <div class="card-commerce-actions">
            <form method="post" action="cart.php" class="product-action-form">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="slug" value="<?= esc($product->slug); ?>">
                <input type="hidden" name="quantity" value="1">
                <button class="btn-primary card-add" type="submit" <?= $product->stock > 0 ? '' : 'disabled'; ?>><i class="bi bi-cart-plus"></i> Add to Cart</button>
            </form>
            <a class="card-wishlist" href="wishlist.php" aria-label="View wishlist"><i class="bi bi-heart"></i></a>
        </div>
    </div>
</article>
