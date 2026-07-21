<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Customers\Models\Customer[] $customers
 * @var int $currentPage
 * @var int $totalPages
 * @var string $flashMessage
 */

$seoTitle = 'Customers | ' . $appConfig['name'];
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
        <h1>Customers</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($customers)): ?>
            <div class="empty-state"><p>No customer accounts yet.</p></div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Account type</th>
                        <th>Company</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>#<?= (int) $customer->id; ?></td>
                            <td><span class="badge <?= $customer->isB2b() ? 'badge-b2b' : 'badge-b2c'; ?>"><?= esc(strtoupper($customer->accountType)); ?></span></td>
                            <td><?= esc($customer->companyName ?? '—'); ?></td>
                            <td><?= esc($customer->createdAt); ?></td>
                            <td><a class="btn-outline" href="/admin/customers/view?id=<?= (int) $customer->id; ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="/admin/customers?page=<?= $p; ?>" class="<?= $p === $currentPage ? 'active' : ''; ?>"><?= $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
