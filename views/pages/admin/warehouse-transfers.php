<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Inventory\Models\Warehouse[] $warehouses
 * @var \App\Domains\Warehouse\Models\StockTransfer[] $transfers
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Stock Transfers | ' . $appConfig['name'];
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
        <h1>Stock Transfers</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <?php if (count($warehouses) >= 2): ?>
            <form method="post" action="/admin/warehouse/transfers" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="initiate">

                <label for="from_warehouse_id">From warehouse</label>
                <select id="from_warehouse_id" name="from_warehouse_id" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= (int) $warehouse->id; ?>"><?= esc($warehouse->name); ?> (<?= esc($warehouse->code); ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label for="to_warehouse_id">To warehouse</label>
                <select id="to_warehouse_id" name="to_warehouse_id" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= (int) $warehouse->id; ?>"><?= esc($warehouse->name); ?> (<?= esc($warehouse->code); ?>)</option>
                    <?php endforeach; ?>
                </select>

                <fieldset>
                    <legend>Line items</legend>
                    <div class="receipt-row">
                        <label for="transfer_product_1">Product ID</label>
                        <input id="transfer_product_1" name="product_id[]" type="number" min="1" placeholder="e.g. 42" required>
                        <label for="transfer_quantity_1">Quantity</label>
                        <input id="transfer_quantity_1" name="quantity[]" type="number" min="1" placeholder="e.g. 10" required>
                    </div>
                    <div class="receipt-row">
                        <label for="transfer_product_2">Product ID</label>
                        <input id="transfer_product_2" name="product_id[]" type="number" min="1" placeholder="optional">
                        <label for="transfer_quantity_2">Quantity</label>
                        <input id="transfer_quantity_2" name="quantity[]" type="number" min="1" placeholder="optional">
                    </div>
                </fieldset>

                <button class="btn-primary" type="submit">Initiate transfer</button>
            </form>
        <?php else: ?>
            <div class="empty-state"><p>At least two active warehouses are required to transfer stock.</p></div>
        <?php endif; ?>

        <h2>Transfers</h2>
        <?php if (empty($transfers)): ?>
            <div class="empty-state"><p>No stock transfers yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Initiated by</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td><?= (int) $transfer->id; ?></td>
                            <td><?= (int) $transfer->fromWarehouseId; ?></td>
                            <td><?= (int) $transfer->toWarehouseId; ?></td>
                            <td><span class="badge"><?= esc($transfer->status); ?></span></td>
                            <td><?= (int) $transfer->initiatedByUserId; ?></td>
                            <td><?= esc($transfer->createdAt); ?></td>
                            <td>
                                <?php if ($transfer->status === 'in_transit'): ?>
                                    <form method="post" action="/admin/warehouse/transfers" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="transfer_id" value="<?= (int) $transfer->id; ?>">
                                        <button class="btn-primary" type="submit">Complete</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
