<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use PDO;
use RuntimeException;
use Throwable;

class OrderRepository implements OrderRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data, array $items): Order
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO orders (order_number, customer_id, user_id, status, subtotal, tax_total, grand_total, shipping_address_id, billing_address_id, placed_at, updated_at)
                 VALUES (:order_number, :customer_id, :user_id, :status, :subtotal, :tax_total, :grand_total, :shipping_address_id, :billing_address_id, NOW(), NOW())'
            );
            $stmt->execute([
                'order_number' => $data['order_number'],
                'customer_id' => $data['customer_id'],
                'user_id' => $data['user_id'],
                'status' => $data['status'],
                'subtotal' => $data['subtotal'],
                'tax_total' => $data['tax_total'],
                'grand_total' => $data['grand_total'],
                'shipping_address_id' => $data['shipping_address_id'],
                'billing_address_id' => $data['billing_address_id'],
            ]);

            $orderId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO order_items (order_id, product_id, sku, name_snapshot, quantity, unit_price_snapshot, line_total, inventory_reservation_id)
                 VALUES (:order_id, :product_id, :sku, :name_snapshot, :quantity, :unit_price_snapshot, :line_total, :inventory_reservation_id)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'sku' => $item['sku'],
                    'name_snapshot' => $item['name_snapshot'],
                    'quantity' => $item['quantity'],
                    'unit_price_snapshot' => $item['unit_price_snapshot'],
                    'line_total' => $item['line_total'],
                    'inventory_reservation_id' => $item['inventory_reservation_id'] ?? null,
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $order = $this->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Order could not be reloaded after creation.');
        }

        return $order;
    }

    public function findById(int $id): ?Order
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if ($data === false) {
            return null;
        }

        $order = new Order($data);
        $order->items = $this->loadItems($id);

        return $order;
    }

    public function findByCustomer(int $customerId, int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE customer_id = :customer_id ORDER BY placed_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function (array $row): Order {
            $order = new Order($row);
            $order->items = $this->loadItems($order->id);

            return $order;
        }, $stmt->fetchAll());
    }

    public function countByCustomer(int $customerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = :customer_id');
        $stmt->execute(['customer_id' => $customerId]);

        return (int) $stmt->fetchColumn();
    }

    public function findAllForAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders ORDER BY placed_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): Order {
            return new Order($row);
        }, $stmt->fetchAll());
    }

    public function countAllForAdmin(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    private function loadItems(int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute(['order_id' => $orderId]);

        return array_map(static function (array $row): OrderItem {
            return new OrderItem($row);
        }, $stmt->fetchAll());
    }
}
