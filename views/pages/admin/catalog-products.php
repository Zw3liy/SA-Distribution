<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Catalog\Models\Product[] $products
 * @var array $categories
 * @var array $brands
 * @var int $currentPage
 * @var int $totalPages
 * @var bool $canCreate
 * @var bool $canEdit
 * @var bool $canDeactivate
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Catalog products | ' . $appConfig['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seoTitle); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/admin-nav.php'; ?>
    <main class="container page-content">
        <h1>Catalog products</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <?php if ($canCreate): ?>
        <h2>Add product</h2>
        <form method="post" action="/admin/catalog/products" class="product-action-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
            <input type="hidden" name="form_action" value="create">
            <div class="filters-grid">
                <div class="filter-field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="filter-field">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" required>
                </div>
                <div class="filter-field">
                    <label for="sku">SKU</label>
                    <input type="text" id="sku" name="sku" required>
                </div>
                <div class="filter-field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id']; ?>"><?= esc($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="brand_id">Brand</label>
                    <select id="brand_id" name="brand_id" required>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= (int) $brand['id']; ?>"><?= esc($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="price">Price (R)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>
                <div class="filter-field">
                    <label for="sale_price">Sale price (R)</label>
                    <input type="number" id="sale_price" name="sale_price" step="0.01" min="0">
                </div>
                <div class="filter-field">
                    <label for="stock">Stock</label>
                    <input type="number" id="stock" name="stock" min="0" value="0">
                </div>
                <div class="filter-field">
                    <label for="short_description">Short description</label>
                    <input type="text" id="short_description" name="short_description" required>
                </div>
                <div class="filter-field">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" required>
                </div>
            </div>
            <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button type="submit" class="btn-primary">Create product</button>
        </form>
        <?php endif; ?>

        <h2>Existing products</h2>
        <?php if (empty($products)): ?>
            <div class="empty-state"><p>No products yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <?php if ($canDeactivate): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= esc($product->name); ?></td>
                            <td><?= esc($product->sku); ?></td>
                            <td>R<?= number_format($product->salePrice ?? $product->price, 2); ?></td>
                            <td><?= (int) $product->stock; ?></td>
                            <td><span class="badge <?= $product->isActive ? 'badge-active' : 'badge-inactive'; ?>"><?= $product->isActive ? 'Active' : 'Inactive'; ?></span></td>
                            <?php if ($canDeactivate && $product->isActive): ?>
                            <td>
                                <form method="post" action="/admin/catalog/products">
                                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                    <input type="hidden" name="form_action" value="deactivate">
                                    <input type="hidden" name="product_id" value="<?= (int) $product->id; ?>">
                                    <button type="submit" class="btn-outline">Deactivate</button>
                                </form>
                            </td>
                            <?php elseif ($canDeactivate): ?>
                            <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="/admin/catalog/products?page=<?= $p; ?>" class="<?= $p === $currentPage ? 'active' : ''; ?>"><?= $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
