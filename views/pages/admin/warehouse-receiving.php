<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Inventory\Models\Warehouse[] $warehouses
 * @var \App\Domains\Warehouse\Models\GoodsReceipt[] $receipts
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Receiving | ' . $appConfig['name'];
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
        <h1>Goods Receiving</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <?php if (empty($warehouses)): ?>
            <div class="empty-state"><p>No active warehouses configured.</p></div>
        <?php else: ?>
            <form method="post" action="/admin/warehouse/receiving" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                <label for="warehouse_id">Receiving warehouse</label>
                <select id="warehouse_id" name="warehouse_id" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?= (int) $warehouse->id; ?>"><?= esc($warehouse->name); ?> (<?= esc($warehouse->code); ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label for="purchase_order_id">Purchase order ID (optional — links the receipt to a PO once Suppliers is live)</label>
                <input id="purchase_order_id" name="purchase_order_id" type="number" min="1" placeholder="Leave blank for standalone receipts">

                <fieldset>
                    <legend>Line items</legend>
                    <div class="receipt-row">
                        <label for="product_id_1">Product ID</label>
                        <input id="product_id_1" name="product_id[]" type="number" min="1" placeholder="e.g. 42" required>
                        <label for="quantity_1">Quantity</label>
                        <input id="quantity_1" name="quantity[]" type="number" min="1" placeholder="e.g. 25" required>
                    </div>
                    <div class="receipt-row">
                        <label for="product_id_2">Product ID</label>
                        <input id="product_id_2" name="product_id[]" type="number" min="1" placeholder="optional">
                        <label for="quantity_2">Quantity</label>
                        <input id="quantity_2" name="quantity[]" type="number" min="1" placeholder="optional">
                    </div>
                    <div class="receipt-row">
                        <label for="product_id_3">Product ID</label>
                        <input id="product_id_3" name="product_id[]" type="number" min="1" placeholder="optional">
                        <label for="quantity_3">Quantity</label>
                        <input id="quantity_3" name="quantity[]" type="number" min="1" placeholder="optional">
                    </div>
                </fieldset>

                <div class="checkbox-field">
                    <label><input type="checkbox" name="allow_over_receipt" value="1"> Allow over-receipt against the purchase order (staff override)</label>
                </div>

                <button class="btn-primary" type="submit">Record receipt &amp; update stock</button>
            </form>
        <?php endif; ?>

        <h2>Recent receipts</h2>
        <?php if (empty($receipts)): ?>
            <div class="empty-state"><p>No goods receipts recorded yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Warehouse</th>
                        <th>PO</th>
                        <th>Received by</th>
                        <th>Received at</th>
                        <th>Lines</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><?= (int) $receipt->id; ?></td>
                            <td><?= (int) $receipt->warehouseId; ?></td>
                            <td><?= $receipt->purchaseOrderId !== null ? (int) $receipt->purchaseOrderId : '—'; ?></td>
                            <td><?= (int) $receipt->receivedByUserId; ?></td>
                            <td><?= esc($receipt->receivedAt); ?></td>
                            <td><?= count($receipt->items); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
