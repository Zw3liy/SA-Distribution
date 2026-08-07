<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var \App\Domains\Orders\Models\CartItem[] $cartItems
 * @var array $summary
 * @var \App\Domains\Customers\Models\Address[] $addresses
 * @var string $error
 * @var string $flashMessage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | <?= esc($appConfig['name']); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content">
        <h1>Checkout</h1>

        <?php if ($flashMessage): ?>
            <div class="flash-message success"><?= esc($flashMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-message error"><?= esc($error); ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Add products to your cart before checking out.</p>
                <a class="btn-primary" href="products.php">Browse products</a>
            </div>
        <?php else: ?>
            <section>
                <h2>Order summary</h2>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit price</th>
                            <th>Qty</th>
                            <th>Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($item->name); ?></strong>
                                    <p class="product-sku">SKU: <?= esc($item->sku); ?></p>
                                </td>
                                <td>R<?= number_format($item->getUnitPrice(), 2); ?></td>
                                <td><?= (int) $item->quantity; ?></td>
                                <td>R<?= number_format($item->getLineTotal(), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <dl class="order-totals">
                    <dt>Subtotal</dt>
                    <dd>R<?= number_format((float) $summary['subtotal'], 2); ?></dd>
                    <dt>VAT (15%)</dt>
                    <dd>R<?= number_format((float) $summary['vat'], 2); ?></dd>
                    <dt>Grand total</dt>
                    <dd><strong>R<?= number_format((float) $summary['grand_total'], 2); ?></strong></dd>
                </dl>
            </section>

            <?php if (empty($addresses)): ?>
                <section>
                    <h2>Delivery details</h2>
                    <p>You don't have any saved addresses yet. <a href="/account-edit.php">Add an address</a> before placing your order.</p>
                </section>
            <?php else: ?>
                <section>
                    <h2>Delivery details</h2>
                    <form method="post" action="/checkout.php" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">

                        <label for="shipping_address_id">Shipping address</label>
                        <select id="shipping_address_id" name="shipping_address_id" required>
                            <option value="">Select an address</option>
                            <?php foreach ($addresses as $address): ?>
                                <option value="<?= (int) $address->id; ?>" <?= $address->isDefault && $address->type !== 'billing' ? 'selected' : ''; ?>>
                                    <?= esc($address->label); ?> — <?= esc($address->addressLine1); ?>, <?= esc($address->city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="billing_address_id">Billing address</label>
                        <select id="billing_address_id" name="billing_address_id" required>
                            <option value="">Select an address</option>
                            <?php foreach ($addresses as $address): ?>
                                <option value="<?= (int) $address->id; ?>" <?= $address->isDefault && $address->type !== 'shipping' ? 'selected' : ''; ?>>
                                    <?= esc($address->label); ?> — <?= esc($address->addressLine1); ?>, <?= esc($address->city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="btn-primary" type="submit">Place order</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
