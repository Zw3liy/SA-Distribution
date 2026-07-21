<?php
declare(strict_types=1);

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\StockReservation;
use DateTimeInterface;
use PDO;
use RuntimeException;

class StockReservationRepository implements StockReservationRepositoryInterface
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): StockReservation
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stock_reservations (inventory_item_id, order_reference, quantity, expires_at, status, created_at)
             VALUES (:inventory_item_id, :order_reference, :quantity, :expires_at, :status, NOW())'
        );
        $stmt->execute([
            'inventory_item_id' => $data['inventory_item_id'],
            'order_reference' => $data['order_reference'],
            'quantity' => $data['quantity'],
            'expires_at' => $data['expires_at'],
            'status' => $data['status'] ?? StockReservation::STATUS_ACTIVE,
        ]);

        $id = (int) $this->db->lastInsertId();
        $reservation = $this->findById($id);
        if ($reservation === null) {
            throw new RuntimeException('Stock reservation could not be reloaded after creation.');
        }

        return $reservation;
    }

    public function findById(int $id): ?StockReservation
    {
        $stmt = $this->db->prepare('SELECT * FROM stock_reservations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        return $data ? new StockReservation($data) : null;
    }

    public function markConsumed(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE stock_reservations SET status = 'consumed' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function markReleased(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE stock_reservations SET status = 'released' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * A bounded-batch bulk maintenance operation (docs/specs/05-inventory.md
     * §17), not a per-row service loop: releases the reserved-quantity
     * allocation on inventory_items for every still-active, expired
     * reservation via a single JOIN update, then flips those
     * reservations to 'released', both inside one transaction so a
     * reservation is never left "released" with its quantity still
     * locked (or vice versa).
     */
    public function expireOlderThan(DateTimeInterface $cutoff): int
    {
        $this->db->beginTransaction();

        try {
            // Aggregated by inventory_item_id first, not a plain
            // UPDATE...JOIN against stock_reservations directly: MySQL's
            // multi-table UPDATE only applies one matching joined row per
            // target row when several join, so an item with two or more
            // expired reservations would silently lose all but one
            // release if summed inline. The GROUP BY subquery guarantees
            // every expired reservation's quantity is actually released.
            $releaseStmt = $this->db->prepare(
                "UPDATE inventory_items ii
                 JOIN (
                     SELECT inventory_item_id, SUM(quantity) AS total_quantity
                     FROM stock_reservations
                     WHERE status = 'active' AND expires_at < :cutoff
                     GROUP BY inventory_item_id
                 ) expired ON expired.inventory_item_id = ii.id
                 SET ii.quantity_reserved = GREATEST(0, ii.quantity_reserved - expired.total_quantity), ii.updated_at = NOW()"
            );
            $releaseStmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);

            $expireStmt = $this->db->prepare(
                "UPDATE stock_reservations SET status = 'released' WHERE status = 'active' AND expires_at < :cutoff"
            );
            $expireStmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
            $count = $expireStmt->rowCount();

            $this->db->commit();

            return $count;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
