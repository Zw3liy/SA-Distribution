<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Warehouse\Models\PickList[] $pickLists
 * @var int $currentPage
 * @var int $totalPages
 * @var string $flashMessage
 */

$seoTitle = 'Pick Lists | ' . $appConfig['name'];
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
        <h1>Pick Lists</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($pickLists)): ?>
            <div class="empty-state"><p>No pick lists yet. They are generated automatically when an order moves to fulfilling.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Warehouse</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pickLists as $pickList): ?>
                        <tr>
                            <td><?= (int) $pickList->id; ?></td>
                            <td>#<?= (int) $pickList->orderId; ?></td>
                            <td><?= (int) $pickList->warehouseId; ?></td>
                            <td><span class="badge"><?= esc($pickList->status); ?></span></td>
                            <td><?= esc($pickList->createdAt); ?></td>
                            <td><a class="btn-secondary" href="/admin/warehouse/pick-list?id=<?= (int) $pickList->id; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn-secondary" href="/admin/warehouse/pick-lists?page=<?= $currentPage - 1; ?>">&laquo; Prev</a>
                    <?php endif; ?>
                    <span>Page <?= (int) $currentPage; ?> of <?= (int) $totalPages; ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn-secondary" href="/admin/warehouse/pick-lists?page=<?= $currentPage + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
