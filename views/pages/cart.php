<?php
declare(strict_types=1);

/**
 * @var array $appConfig
 * @var string $flashMessage
 * @var array $cartItems
 * @var float $cartTotal
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping cart | <?= esc($appConfig['name']); ?></title>
    <meta name="description" content="Review your shopping cart and proceed with SA Business Distribution quote requests.">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body class="bg-light text-dark font-sans">
    <?php include APP_BASE_PATH . '/components/header.php'; ?>
    <main class="container page-content">
        <section class="products-hero">
            <div>
                <p class="eyebrow">Shopping cart</p>
                <h1>Your selected products</h1>
                <p>Review quantities, remove items, or continue browsing for more enterprise IT solutions.</p>
            </div>
        </section>

        <?php if ($flashMessage): ?>
            <div class="flash-message"><?= esc($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Browse our catalog and add products to start a quote or checkout process.</p>
                <a class="btn-primary" href="products.php">Browse products</a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <div class="cart-product">
                                    <?php if (!empty($item['thumbnail'])): ?>
                                        <img src="<?= esc($item['thumbnail']); ?>" alt="<?= esc($item['name']); ?>" class="cart-thumbnail">
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= esc($item['name']); ?></strong>
                                        <p class="product-sku">SKU: <?= esc($item['slug']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>R<?= number_format((float) ($item['sale_price'] ?? $item['price']), 2); ?></td>
                            <td>
                                <form method="post" action="cart.php" class="cart-quantity-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="slug" value="<?= esc($item['slug']); ?>">
                                    <input class="cart-quantity-input" type="number" name="quantity" min="1" value="<?= esc((string) $item['quantity']); ?>">
                                    <button class="btn-secondary btn-quantity" type="submit">Update</button>
                                </form>
                            </td>
                            <td>R<?= number_format(((float) ($item['sale_price'] ?? $item['price'])) * (int) $item['quantity'], 2); ?></td>
                            <td>
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="slug" value="<?= esc($item['slug']); ?>">
                                    <button class="btn-outline" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="cart-total">
                <span>Total</span>
                <strong>R<?= number_format($cartTotal, 2); ?></strong>
            </div>
        <?php endif; ?>
    </main>
    <?php include APP_BASE_PATH . '/components/footer.php'; ?>
</body>
</html>
