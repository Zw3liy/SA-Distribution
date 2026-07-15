<?php
declare(strict_types=1);

/** @var Product $product */
?>
<article class="product-card">
    <div class="product-card-image">
        <?php if ($product->thumbnail !== null): ?>
            <img src="<?= esc($product->thumbnail); ?>" alt="<?= esc($product->name); ?>" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="product-card-placeholder">Image unavailable</div>
        <?php endif; ?>
        <div class="product-badges">
            <?php if ($product->isNew): ?>
                <span class="badge badge-new">New</span>
            <?php endif; ?>
            <?php if ($product->isOnSale): ?>
                <span class="badge badge-sale">Sale</span>
            <?php endif; ?>
            <?php if ($product->isFeatured): ?>
                <span class="badge badge-featured">Featured</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="product-card-body">
        <p class="product-meta"><?= esc($product->brandName); ?> • <?= esc($product->categoryName); ?></p>
        <h3 class="product-title"><?= esc($product->name); ?></h3>
        <p class="product-sku">SKU: <?= esc($product->sku); ?></p>
        <p class="product-description"><?= esc($product->shortDescription); ?></p>
        <div class="product-pricing">
            <?php if ($product->salePrice !== null): ?>
                <span class="price price-sale">R<?= number_format($product->salePrice, 2); ?></span>
                <span class="price price-original">R<?= number_format($product->price, 2); ?></span>
            <?php else: ?>
                <span class="price">R<?= number_format($product->price, 2); ?></span>
            <?php endif; ?>
        </div>
        <div class="product-footer">
            <span class="stock <?= $product->stock > 0 ? 'stock-in' : 'stock-out'; ?>">
                <?= $product->stock > 0 ? 'In stock' : 'Out of stock'; ?>
            </span>
            <a class="btn-outline btn-card" href="product-details.php?slug=<?= esc($product->slug); ?>" aria-disabled="<?= $product->stock > 0 ? 'false' : 'true'; ?>">View details</a>
        </div>
    </div>
</article>
