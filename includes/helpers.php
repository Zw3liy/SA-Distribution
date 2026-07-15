<?php
declare(strict_types=1);

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

function seo_title(string $title = null): string
{
    $config = require __DIR__ . '/../config/app.php';
    $default = $config['meta']['title'];

    if ($title === null || trim($title) === '') {
        return $default;
    }

    return sprintf('%s | %s', esc($title), esc($config['name']));
}

function seo_description(string $description = null): string
{
    $config = require __DIR__ . '/../config/app.php';
    return esc($description ?: $config['meta']['description']);
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function verify_csrf_token(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getCartItems(): array
{
    return $_SESSION['cart'] ?? [];
}

function getCartCount(): int
{
    $items = getCartItems();
    return array_sum(array_map(function ($item) {
        return (int) ($item['quantity'] ?? 0);
    }, $items));
}

function getCartTotal(): float
{
    $items = getCartItems();
    $total = 0.0;
    foreach ($items as $item) {
        $price = isset($item['sale_price']) && $item['sale_price'] !== null ? (float) $item['sale_price'] : (float) $item['price'];
        $quantity = (int) ($item['quantity'] ?? 0);
        $total += $price * $quantity;
    }

    return $total;
}

function addToCart(array $product, int $quantity = 1): void
{
    $slug = (string) $product['slug'];
    if (!isset($_SESSION['cart'][$slug])) {
        $_SESSION['cart'][$slug] = [
            'slug' => $slug,
            'name' => $product['name'],
            'price' => $product['price'],
            'sale_price' => $product['sale_price'],
            'quantity' => 0,
            'thumbnail' => $product['thumbnail'] ?? null,
        ];
    }

    $_SESSION['cart'][$slug]['quantity'] += $quantity;
}

function removeFromCart(string $slug): void
{
    if (isset($_SESSION['cart'][$slug])) {
        unset($_SESSION['cart'][$slug]);
    }
}

function clearCart(): void
{
    $_SESSION['cart'] = [];
}

function getWishlistCount(): int
{
    return count($_SESSION['wishlist'] ?? []);
}

function addToWishlist(array $product): void
{
    $slug = (string) $product['slug'];
    if (!isset($_SESSION['wishlist'][$slug])) {
        $_SESSION['wishlist'][$slug] = [
            'slug' => $slug,
            'name' => $product['name'],
            'sku' => $product['sku'],
            'thumbnail' => $product['thumbnail'] ?? null,
        ];
    }
}

function getWishlistItems(): array
{
    return $_SESSION['wishlist'] ?? [];
}

function removeFromWishlist(string $slug): void
{
    if (isset($_SESSION['wishlist'][$slug])) {
        unset($_SESSION['wishlist'][$slug]);
    }
}

function updateCartItemQuantity(string $slug, int $quantity): void
{
    if ($quantity <= 0) {
        removeFromCart($slug);
        return;
    }

    if (isset($_SESSION['cart'][$slug])) {
        $_SESSION['cart'][$slug]['quantity'] = $quantity;
    }
}

function setFlashMessage(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage(): string
{
    $message = $_SESSION['flash_message'] ?? '';
    unset($_SESSION['flash_message']);
    return $message;
}
