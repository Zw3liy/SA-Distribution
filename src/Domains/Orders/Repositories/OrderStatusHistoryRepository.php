<?php
declare(strict_types=1);

namespace App\Domains\Orders\Repositories;

use App\Domains\Orders\Models\OrderStatusHistory;
use PDO;

class OrderStatusHistoryRepository implements OrderStatusHistoryRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function record(int $orderId, string $from, string $to, ?int $actorUserId, ?string $note): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, actor_user_id, note, created_at)
             VALUES (:order_id, :from_status, :to_status, :actor_user_id, :note, NOW())'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actorUserId,
            'note' => $note,
        ]);
    }

    public function historyFor(int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_status_history WHERE order_id = :order_id ORDER BY created_at ASC');
        $stmt->execute(['order_id' => $orderId]);

        return array_map(static function (array $row): OrderStatusHistory {
            return new OrderStatusHistory($row);
        }, $stmt->fetchAll());
    }
}
