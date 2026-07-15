<?php
declare(strict_types=1);

class CartService
{
    /** @var ProductRepository */
    private $productRepository;

    /** @var CartRepository */
    private $cartRepository;

    public function __construct(ProductRepository $productRepository, CartRepository $cartRepository)
    {
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;
    }

    public function getCartItems(string $sessionId): array
    {
        $items = $this->cartRepository->getCartItemsBySession($sessionId);

        return array_map(function (array $item) {
            return new CartItem([
                'product_id' => $item['product_id'],
                'slug' => $item['slug'],
                'name' => $item['name'],
                'sku' => $item['sku'],
                'price' => $item['price'],
                'sale_price' => $item['sale_price'],
                'quantity' => $item['quantity'],
                'stock' => $item['stock'],
                'thumbnail' => $item['thumbnail'],
            ]);
        }, $items);
    }

    public function getCartSummary(array $items): array
    {
        $subtotal = 0.0;
        $quantity = 0;

        foreach ($items as $item) {
            $subtotal += $item->getLineTotal();
            $quantity += $item->quantity;
        }

        $vat = round($subtotal * 0.15, 2);
        $grandTotal = round($subtotal + $vat, 2);

        return [
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'grand_total' => $grandTotal,
        ];
    }

    public function addProductToCart(string $sessionId, string $slug, int $quantity): void
    {
        $quantity = max(1, $quantity);
        $product = $this->productRepository->getProductBySlug($slug);
        if ($product === null) {
            throw new InvalidArgumentException('Product not found.');
        }

        $items = $this->getPersistedCartItems($sessionId);
        foreach ($items as &$item) {
            if ($item['slug'] === $slug) {
                $item['quantity'] = min($product->stock, $item['quantity'] + $quantity);
                $this->cartRepository->saveCartItems($sessionId, $items);
                return;
            }
        }
        unset($item);

        $items[] = [
            'product_id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'sale_price' => $product->salePrice,
            'quantity' => min($quantity, $product->stock),
            'thumbnail' => $product->thumbnail,
        ];

        $this->cartRepository->saveCartItems($sessionId, $items);
    }

    public function updateCartItemQuantity(string $sessionId, string $slug, int $quantity): void
    {
        $quantity = max(0, $quantity);
        $items = $this->getPersistedCartItems($sessionId);
        foreach ($items as $index => $item) {
            if ($item['slug'] === $slug) {
                if ($quantity === 0) {
                    unset($items[$index]);
                } else {
                    $product = $this->productRepository->getProductBySlug($slug);
                    if ($product === null) {
                        throw new InvalidArgumentException('Product not found.');
                    }

                    $items[$index]['quantity'] = min($quantity, $product->stock);
                }

                $this->cartRepository->saveCartItems($sessionId, array_values($items));
                return;
            }
        }
    }

    public function removeProductFromCart(string $sessionId, string $slug): void
    {
        $this->cartRepository->removeCartItem($sessionId, $slug);
    }

    public function clearCart(string $sessionId): void
    {
        $this->cartRepository->clearCart($sessionId);
    }

    private function getPersistedCartItems(string $sessionId): array
    {
        return $this->cartRepository->getCartItemsBySession($sessionId);
    }
}
