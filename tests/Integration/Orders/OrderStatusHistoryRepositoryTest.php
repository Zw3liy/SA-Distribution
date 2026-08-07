<?php

declare(strict_types=1);

namespace Tests\Integration\Orders;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Orders\Repositories\OrderStatusHistoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_orders_domain.sql migration applied.
 *
 * order_status_history is the immutable audit trail of every status
 * transition (docs/specs/06-orders.md §15): one row per transition,
 * oldest first, with the optional staff actor and note preserved.
 */
final class OrderStatusHistoryRepositoryTest extends TestCase
{
    private PDO $db;
    private OrderStatusHistoryRepository $repository;
    private int $orderId = 0;
    private int $customerId = 0;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'sa_business';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $this->db = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->repository = new OrderStatusHistoryRepository($this->db);

        $this->cleanup();
        $this->createOrderFixture();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM orders WHERE order_number = 'REPO-TEST-HIST-ORD-1'");
        $this->db->exec("DELETE FROM addresses WHERE label = 'Repo Test HIST Address'");
        if ($this->customerId > 0) {
            $this->db->exec('DELETE FROM customers WHERE id = ' . $this->customerId);
            $this->customerId = 0;
        }
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-hist-user@example.com'");
        $this->orderId = 0;
    }

    private function createOrderFixture(): void
    {
        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-hist-user@example.com', '0000000000', 'x', 1, 1)"
        );
        $userId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO customers (account_type, company_name, created_at, updated_at) VALUES ('b2c', 'REPO-TEST-HIST', NOW(), NOW())");
        $customerId = (int) $this->db->lastInsertId();
        $this->customerId = $customerId;
        $this->db->exec(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES ({$customerId}, 'Repo Test HIST Address', 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $addressId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO orders (order_number, customer_id, user_id, status, subtotal, tax_total, grand_total, shipping_address_id, billing_address_id)
             VALUES ('REPO-TEST-HIST-ORD-1', {$customerId}, {$userId}, 'pending_payment', 200, 30, 230, {$addressId}, {$addressId})"
        );
        $this->orderId = (int) $this->db->lastInsertId();
    }

    public function testRecordAndHistoryForRoundTripsWithActorAndNote(): void
    {
        $this->repository->record($this->orderId, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, 42, 'Payment confirmed');

        $history = $this->repository->historyFor($this->orderId);

        $this->assertCount(1, $history);
        $this->assertInstanceOf(OrderStatusHistory::class, $history[0]);
        $this->assertSame($this->orderId, $history[0]->orderId);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $history[0]->fromStatus);
        $this->assertSame(Order::STATUS_PAID, $history[0]->toStatus);
        $this->assertSame(42, $history[0]->actorUserId);
        $this->assertSame('Payment confirmed', $history[0]->note);
    }

    public function testHistoryIsReturnedOldestFirst(): void
    {
        $this->repository->record($this->orderId, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, null, null);
        usleep(1100);
        $this->repository->record($this->orderId, Order::STATUS_PAID, Order::STATUS_FULFILLING, 42, 'Fulfillment started');
        usleep(1100);
        $this->repository->record($this->orderId, Order::STATUS_FULFILLING, Order::STATUS_SHIPPED, 42, 'Handed to courier');

        $history = $this->repository->historyFor($this->orderId);

        $this->assertCount(3, $history);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $history[0]->fromStatus);
        $this->assertSame(Order::STATUS_PAID, $history[1]->fromStatus);
        $this->assertSame(Order::STATUS_FULFILLING, $history[2]->fromStatus);
    }

    public function testHistoryForUnknownOrderReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repository->historyFor(999999));
    }

    public function testNullActorAndNoteArePreservedAsNull(): void
    {
        $this->repository->record($this->orderId, Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED, null, null);

        $history = $this->repository->historyFor($this->orderId);

        $this->assertNull($history[0]->actorUserId);
        $this->assertNull($history[0]->note);
    }
}
