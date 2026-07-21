<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Customers\Models\Customer $customer
 * @var array $buyers
 * @var bool $canManageBuyers
 * @var string $error
 * @var string $flashMessage
 */

$seoTitle = 'Customer #' . $customer->id . ' | ' . $appConfig['name'];
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
        <h1>Customer #<?= (int) $customer->id; ?></h1>
        <p><a href="/admin/customers">&larr; Back to customers</a></p>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message"><?= esc($error); ?></div>
        <?php endif; ?>

        <section>
            <h2>Account details</h2>
            <dl>
                <dt>Account type</dt>
                <dd><span class="badge <?= $customer->isB2b() ? 'badge-b2b' : 'badge-b2c'; ?>"><?= esc(strtoupper($customer->accountType)); ?></span></dd>
                <dt>Company</dt>
                <dd><?= esc($customer->companyName ?? '—'); ?></dd>
                <dt>Credit terms</dt>
                <dd><?= esc($customer->creditTerms ?? '—'); ?></dd>
                <dt>Created</dt>
                <dd><?= esc($customer->createdAt); ?></dd>
            </dl>
        </section>

        <?php if ($customer->isB2b()): ?>
        <section>
            <h2>Linked buyers</h2>
            <?php if (empty($buyers)): ?>
                <p>No buyers linked to this account yet.</p>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buyers as $buyer): ?>
                            <tr>
                                <td><?= esc($buyer['first_name'] . ' ' . $buyer['last_name']); ?></td>
                                <td><?= esc($buyer['email']); ?></td>
                                <td><?= !empty($buyer['is_primary_contact']) ? 'Primary contact' : 'Buyer'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($canManageBuyers): ?>
                <h3>Add a buyer</h3>
                <form method="post" action="/admin/customers/view?id=<?= (int) $customer->id; ?>" class="product-action-form">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                    <input type="hidden" name="customer_id" value="<?= (int) $customer->id; ?>">
                    <div class="filter-field">
                        <label for="buyer_email">Buyer email</label>
                        <input type="email" id="buyer_email" name="buyer_email" required>
                    </div>
                    <button type="submit" class="btn-primary">Add buyer</button>
                </form>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section>
            <p>Buyer management is only available for B2B accounts.</p>
        </section>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
