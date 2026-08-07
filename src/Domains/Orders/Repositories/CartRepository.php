<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use PDO;
use Throwable;

/**
 * Moved unchanged from src/Repositories/CartRepository.php
 * (docs/specs/06-orders.md §19) -- namespace-only migration, now
 * implementing the new CartRepositoryInterface. Method bodies are a
 * direct copy of Phase 3's implementation, untouched, plus the new
 * markConverted() / converted-cart exclusion required for checkout.
 */
class CartRepository implements CartRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getCartIdBySession(string $sessionId): ?int
    {
        // Excludes converted carts (docs/specs/06-orders.md §2) so a
        // post-checkout add-to-cart creates a fresh row instead of
        // silently reusing (and polluting) the historical, order-linked
        // one. A no-op filter for every cart that predates this domain
        // (converted_to_order_id was always NULL before checkout
        // existed), so this is a safe, backward-compatible change to
        // Phase 3's original query.
        $stmt = $this->db->prepare('SELECT id FROM cart WHERE session_id = :session_id AND converted_to_order_id IS NULL LIMIT 1');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : (int) $result['id'];
    }

    public function markConverted(string $sessionId, int $orderId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cart SET converted_to_order_id = :order_id WHERE session_id = :session_id AND converted_to_order_id IS NULL'
        );
        $stmt->execute(['order_id' => $orderId, 'session_id' => $sessionId]);
    }

    public function saveCartItems(string $sessionId, array $items): void
    {
        $this->db->beginTransaction();

        try {
            $cartId = $this->getCartIdBySession($sessionId);
            if ($cartId === null) {
                $stmt = $this->db->prepare('INSERT INTO cart (session_id) VALUES (:session_id)');
                $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
                $stmt->execute();
                $cartId = (int) $this->db->lastInsertId();
            }

            $delete = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $delete->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
            $delete->execute();

            $insert = $this->db->prepare(
                'INSERT INTO cart_items (cart_id, product_id, product_slug, product_name, product_sku, unit_price, sale_price, quantity, thumbnail) VALUES (:cart_id, :product_id, :product_slug, :product_name, :product_sku, :unit_price, :sale_price, :quantity, :thumbnail)'
            );

            foreach ($items as $item) {
                $insert->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
                $insert->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
                $insert->bindValue(':product_slug', $item['slug'], PDO::PARAM_STR);
                $insert->bindValue(':product_name', $item['name'], PDO::PARAM_STR);
                $insert->bindValue(':product_sku', $item['sku'], PDO::PARAM_STR);
                $insert->bindValue(':unit_price', number_format((float) $item['price'], 2, '.', ''), PDO::PARAM_STR);
                $insert->bindValue(':sale_price', $item['sale_price'] !== null ? number_format((float) $item['sale_price'], 2, '.', '') : null, PDO::PARAM_STR);
                $insert->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
                $insert->bindValue(':thumbnail', $item['thumbnail'] ?? null, PDO::PARAM_STR);
                $insert->execute();
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function getCartItemsBySession(string $sessionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ci.product_id, ci.product_slug AS slug, ci.product_name AS name, ci.product_sku AS sku, ci.unit_price AS price, ci.sale_price, ci.quantity, ci.thumbnail, p.stock FROM cart_items AS ci LEFT JOIN products AS p ON ci.product_id = p.id JOIN cart AS c ON ci.cart_id = c.id WHERE c.session_id = :session_id ORDER BY ci.id ASC'
        );
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeCartItem(string $sessionId, string $productSlug): void
    {
        $stmt = $this->db->prepare(
            'DELETE ci FROM cart_items AS ci JOIN cart AS c ON ci.cart_id = c.id WHERE c.session_id = :session_id AND ci.product_slug = :product_slug'
        );
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->bindValue(':product_slug', $productSlug, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function clearCart(string $sessionId): void
    {
        $stmt = $this->db->prepare('DELETE ci FROM cart_items AS ci JOIN cart AS c ON ci.cart_id = c.id WHERE c.session_id = :session_id');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $stmt->execute();
    }
}
