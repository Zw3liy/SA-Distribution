<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Warehouse\Models\PickList $pickList
 * @var \App\Domains\Warehouse\Models\Shipment|null $shipment
 * @var bool $canShip
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Pick List #' . $pickList->id . ' | ' . $appConfig['name'];
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
        <h1>Pick List #<?= (int) $pickList->id; ?></h1>
        <p>Order #<?= (int) $pickList->orderId; ?> &middot; Warehouse #<?= (int) $pickList->warehouseId; ?> &middot; Status: <strong><?= esc($pickList->status); ?></strong></p>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Quantity</th>
                    <th>Picked</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pickList->items as $item): ?>
                    <tr>
                        <td><?= (int) $item->productId; ?></td>
                        <td><?= (int) $item->quantity; ?></td>
                        <td><?= $item->pickedAt !== null ? esc($item->pickedAt) : '—'; ?></td>
                        <td>
                            <?php if ($item->pickedAt === null && !in_array($pickList->status, ['picked', 'packed', 'shipped', 'cancelled'], true)): ?>
                                <form method="post" action="/admin/warehouse/pick-list?id=<?= (int) $pickList->id; ?>" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="mark_picked">
                                    <input type="hidden" name="item_id" value="<?= (int) $item->id; ?>">
                                    <button class="btn-primary" type="submit">Mark picked</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (in_array($pickList->status, ['open', 'picking'], true)): ?>
            <form method="post" action="/admin/warehouse/pick-list?id=<?= (int) $pickList->id; ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="mark_packed">
                <button class="btn-primary" type="submit">Mark packed (all lines must be picked)</button>
            </form>
        <?php endif; ?>

        <?php if ($pickList->status === 'packed' && $canShip && $shipment === null): ?>
            <form method="post" action="/admin/warehouse/pick-list?id=<?= (int) $pickList->id; ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                <input type="hidden" name="action" value="create_shipment">

                <label for="carrier">Carrier</label>
                <input id="carrier" name="carrier" type="text" placeholder="e.g. The Courier Guy, DHL" required>

                <label for="tracking_number">Tracking number (optional)</label>
                <input id="tracking_number" name="tracking_number" type="text" placeholder="e.g. 1Z999AA10123456784">

                <button class="btn-primary" type="submit">Create shipment &amp; mark order shipped</button>
            </form>
        <?php endif; ?>

        <?php if ($shipment !== null): ?>
            <div class="empty-state">
                <p>Shipped via <strong><?= esc($shipment->carrier); ?></strong><?= $shipment->trackingNumber !== null ? ' — tracking ' . esc($shipment->trackingNumber) : ''; ?> on <?= esc($shipment->shippedAt); ?>.</p>
            </div>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
