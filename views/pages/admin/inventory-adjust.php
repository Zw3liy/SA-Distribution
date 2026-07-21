<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Catalog\Models\Product|null $product
 * @var int $productId
 * @var \App\Domains\Inventory\Models\Warehouse[] $warehouses
 * @var int $selectedWarehouseId
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Adjust stock | ' . $appConfig['name'];
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
        <h1>Adjust stock</h1>
        <p><a href="/admin/inventory">&larr; Back to inventory</a></p>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <?php if ($product !== null): ?>
            <p><strong><?= esc($product->name); ?></strong> (<?= esc($product->sku); ?>)</p>
        <?php endif; ?>

        <form method="post" action="/admin/inventory/adjust" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

            <label for="product_id">Product ID</label>
            <input id="product_id" name="product_id" type="number" min="1" value="<?= (int) $productId; ?>" required>

            <label for="warehouse_id">Warehouse</label>
            <select id="warehouse_id" name="warehouse_id" required>
                <?php foreach ($warehouses as $warehouse): ?>
                    <option value="<?= (int) $warehouse->id; ?>" <?= $warehouse->id === $selectedWarehouseId ? 'selected' : ''; ?>><?= esc($warehouse->name); ?> (<?= esc($warehouse->code); ?>)</option>
                <?php endforeach; ?>
            </select>

            <label for="delta">Quantity change</label>
            <input id="delta" name="delta" type="number" placeholder="e.g. 50 to add, -5 to remove" required>

            <label for="reason">Reason</label>
            <input id="reason" name="reason" type="text" placeholder="e.g. Received PO #1234, Damaged stock write-off" required>

            <div class="checkbox-field">
                <label><input type="checkbox" name="allow_negative" value="1"> Allow this adjustment to take on-hand stock negative (reconciliation override)</label>
            </div>

            <button class="btn-primary" type="submit">Apply adjustment</button>
        </form>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
