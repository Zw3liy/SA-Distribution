<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/repositories/ProductRepository.php';
require_once __DIR__ . '/services/ProductService.php';

$appConfig = require __DIR__ . '/config/app.php';
$productService = new ProductService(new ProductRepository($db));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $slug = trim((string) filter_input(INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    $quantity = (int) filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

    if ($action === 'add' && $slug !== '') {
        $product = $productService->getProductBySlug($slug);
        if ($product !== null) {
            addToCart([
                'slug' => $product->slug,
                'name' => $product->name,
                'price' => $product->price,
                'sale_price' => $product->salePrice,
                'thumbnail' => $product->thumbnail,
            ], $quantity);
            setFlashMessage('Product added to cart.');
        }
    }

    if ($action === 'wishlist' && $slug !== '') {
        $product = $productService->getProductBySlug($slug);
        if ($product !== null) {
            addToWishlist([
                'slug' => $product->slug,
                'name' => $product->name,
                'sku' => $product->sku,
                'thumbnail' => $product->thumbnail,
            ]);
            setFlashMessage('Product added to wishlist.');
        }
    }

    if ($action === 'update' && $slug !== '') {
        updateCartItemQuantity($slug, $quantity);
        setFlashMessage('Cart updated successfully.');
    }

    if ($action === 'remove' && $slug !== '') {
        removeFromCart($slug);
        setFlashMessage('Item removed from cart.');
    }

    header('Location: cart.php');
    exit;
}

$flashMessage = getFlashMessage();
$cartItems = getCartItems();
$cartTotal = getCartTotal();
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
    <?php include __DIR__ . '/components/header.php'; ?>
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
    <?php include __DIR__ . '/components/footer.php'; ?>
</body>
</html>
