<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Inventory\Models\Warehouse[] $warehouses
 * @var int $currentWarehouseId
 * @var array $items
 * @var string $search
 * @var int $currentPage
 * @var int $totalPages
 * @var bool $canAdjust
 * @var string $flashMessage
 */

$seoTitle = 'Inventory | ' . $appConfig['name'];
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
        <h1>Inventory</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($warehouses)): ?>
            <div class="empty-state"><p>No active warehouses configured.</p></div>
        <?php else: ?>
            <form method="get" action="/admin/inventory" class="filters-grid">
                <div class="filter-field">
                    <label for="warehouse_id">Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" onchange="this.form.submit()">
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int) $warehouse->id; ?>" <?= $warehouse->id === $currentWarehouseId ? 'selected' : ''; ?>><?= esc($warehouse->name); ?> (<?= esc($warehouse->code); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="search">Search product</label>
                    <input type="text" id="search" name="search" value="<?= esc($search); ?>" placeholder="Name or SKU">
                </div>
                <button type="submit" class="btn-secondary">Filter</button>
            </form>

            <?php if (empty($items)): ?>
                <div class="empty-state"><p>No stock items for this warehouse.</p></div>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>On hand</th>
                            <th>Reserved</th>
                            <th>Available</th>
                            <th>Reorder threshold</th>
                            <?php if ($canAdjust): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $available = (int) $item['quantity_on_hand'] - (int) $item['quantity_reserved']; ?>
                            <tr>
                                <td><?= esc($item['product_name']); ?></td>
                                <td><?= esc($item['product_sku']); ?></td>
                                <td><?= (int) $item['quantity_on_hand']; ?></td>
                                <td><?= (int) $item['quantity_reserved']; ?></td>
                                <td>
                                    <span class="badge <?= $available <= 0 ? 'badge-inactive' : ($available < (int) $item['reorder_threshold'] ? 'badge-sale' : 'badge-active'); ?>">
                                        <?= $available; ?>
                                    </span>
                                </td>
                                <td><?= (int) $item['reorder_threshold']; ?></td>
                                <?php if ($canAdjust): ?>
                                <td>
                                    <a class="btn-outline" href="/admin/inventory/adjust?product_id=<?= (int) $item['product_id']; ?>&warehouse_id=<?= (int) $item['warehouse_id']; ?>">Adjust</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="/admin/inventory?warehouse_id=<?= $currentWarehouseId; ?>&search=<?= urlencode($search); ?>&page=<?= $p; ?>" class="<?= $p === $currentPage ? 'active' : ''; ?>"><?= $p; ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
