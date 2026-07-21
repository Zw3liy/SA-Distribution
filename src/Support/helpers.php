<?php
declare(strict_types=1);

/**
 * Global view/session helper functions. Kept as plain functions (loaded
 * via Composer's "files" autoload) rather than converted to a static
 * class, since view templates throughout the app call them directly
 * (esc(), getCartCount(), etc.) and this preserves that exactly.
 */

if (!function_exists('esc')) {
    function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('seo_title')) {
    function seo_title(string $title = null): string
    {
        $config = $GLOBALS['appConfig'] ?? require __DIR__ . '/../../config/app.php';
        $default = $config['meta']['title'];

        if ($title === null || trim($title) === '') {
            return $default;
        }

        return sprintf('%s | %s', esc($title), esc($config['name']));
    }
}

if (!function_exists('seo_description')) {
    function seo_description(string $description = null): string
    {
        $config = $GLOBALS['appConfig'] ?? require __DIR__ . '/../../config/app.php';
        return esc($description ?: $config['meta']['description']);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

if (!function_exists('getCartItems')) {
    function getCartItems(): array
    {
        return $_SESSION['cart'] ?? [];
    }
}

if (!function_exists('getCartCount')) {
    function getCartCount(): int
    {
        $items = getCartItems();
        return array_sum(array_map(function ($item) {
            return (int) ($item['quantity'] ?? 0);
        }, $items));
    }
}

if (!function_exists('getCartTotal')) {
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
}

if (!function_exists('addToCart')) {
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
}

if (!function_exists('removeFromCart')) {
    function removeFromCart(string $slug): void
    {
        if (isset($_SESSION['cart'][$slug])) {
            unset($_SESSION['cart'][$slug]);
        }
    }
}

if (!function_exists('clearCart')) {
    function clearCart(): void
    {
        $_SESSION['cart'] = [];
    }
}

if (!function_exists('getWishlistCount')) {
    function getWishlistCount(): int
    {
        return count($_SESSION['wishlist'] ?? []);
    }
}

if (!function_exists('addToWishlist')) {
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
}

if (!function_exists('getWishlistItems')) {
    function getWishlistItems(): array
    {
        return $_SESSION['wishlist'] ?? [];
    }
}

if (!function_exists('removeFromWishlist')) {
    function removeFromWishlist(string $slug): void
    {
        if (isset($_SESSION['wishlist'][$slug])) {
            unset($_SESSION['wishlist'][$slug]);
        }
    }
}

if (!function_exists('updateCartItemQuantity')) {
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
}

if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $message): void
    {
        $_SESSION['flash_message'] = $message;
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage(): string
    {
        $message = $_SESSION['flash_message'] ?? '';
        unset($_SESSION['flash_message']);
        return $message;
    }
}
