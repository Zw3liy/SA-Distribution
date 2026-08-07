<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use App\Domains\Orders\Models\Payment;
use PDO;
use RuntimeException;

class PaymentRepository implements PaymentRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): Payment
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (order_id, method, status, amount, gateway_reference, created_at)
             VALUES (:order_id, :method, :status, :amount, :gateway_reference, NOW())'
        );
        $stmt->execute([
            'order_id' => $data['order_id'],
            'method' => $data['method'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'gateway_reference' => $data['gateway_reference'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        $payment = $this->findById($id);
        if ($payment === null) {
            throw new RuntimeException('Payment could not be reloaded after creation.');
        }

        return $payment;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function findByOrderId(int $orderId): ?Payment
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $data = $stmt->fetch();

        return $data ? new Payment($data) : null;
    }

    private function findById(int $id): ?Payment
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new Payment($data) : null;
    }
}
