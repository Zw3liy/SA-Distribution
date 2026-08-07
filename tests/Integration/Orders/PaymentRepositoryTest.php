<?php

declare(strict_types=1);

namespace Tests\Integration\Orders;

use App\Domains\Orders\Models\Payment;
use App\Domains\Orders\Repositories\PaymentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_orders_domain.sql migration applied.
 *
 * payments is an orchestration record only (docs/specs/06-orders.md
 * §16): no card data is ever stored, just method/status/amount and the
 * gateway's own reference id, keeping the platform out of PCI-DSS
 * card-data scope.
 */
final class PaymentRepositoryTest extends TestCase
{
    private PDO $db;
    private PaymentRepository $repository;
    private int $orderId = 0;
    private int $userId = 0;
    private int $customerId = 0;
    private int $addressId = 0;

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
        $this->repository = new PaymentRepository($this->db);

        $this->cleanup();
        $this->createOrderFixture();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM orders WHERE order_number = 'REPO-TEST-PAY-ORD-1'");
        $this->db->exec("DELETE FROM addresses WHERE label = 'Repo Test PAY Address'");
        $this->db->exec("DELETE FROM customers WHERE id = {$this->customerId}");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-pay-user@example.com'");
        $this->orderId = $this->userId = $this->customerId = $this->addressId = 0;
    }

    private function createOrderFixture(): void
    {
        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified)
             VALUES ('Repo', 'Test', 'repo-test-pay-user@example.com', '0000000000', 'x', 1, 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO customers (account_type, company_name, created_at, updated_at) VALUES ('b2c', NULL, NOW(), NOW())");
        $this->customerId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO addresses (customer_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES ({$this->customerId}, 'Repo Test PAY Address', 0, '1 Main Road', 'Johannesburg', 'Gauteng', '2196', 'South Africa', 'both')"
        );
        $this->addressId = (int) $this->db->lastInsertId();
        $this->db->exec(
            "INSERT INTO orders (order_number, customer_id, user_id, status, subtotal, tax_total, grand_total, shipping_address_id, billing_address_id)
             VALUES ('REPO-TEST-PAY-ORD-1', {$this->customerId}, {$this->userId}, 'pending_payment', 200, 30, 230, {$this->addressId}, {$this->addressId})"
        );
        $this->orderId = (int) $this->db->lastInsertId();
    }

    public function testCreateAndFindByOrderIdRoundTrips(): void
    {
        $payment = $this->repository->create([
            'order_id' => $this->orderId,
            'method' => 'unassigned',
            'status' => Payment::STATUS_PENDING,
            'amount' => 230.0,
            'gateway_reference' => null,
        ]);

        $this->assertGreaterThan(0, $payment->id);
        $this->assertSame($this->orderId, $payment->orderId);
        $this->assertSame('unassigned', $payment->method);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(230.0, $payment->amount);
        $this->assertNull($payment->gatewayReference);

        $found = $this->repository->findByOrderId($this->orderId);
        $this->assertNotNull($found);
        $this->assertSame($payment->id, $found->id);
    }

    public function testFindByOrderIdReturnsLatestPaymentForOrder(): void
    {
        $first = $this->repository->create([
            'order_id' => $this->orderId,
            'method' => 'unassigned',
            'status' => Payment::STATUS_FAILED,
            'amount' => 230.0,
            'gateway_reference' => 'gw-fail-1',
        ]);
        usleep(1100000); // NOW() has 1s precision; ensure distinct created_at
        $second = $this->repository->create([
            'order_id' => $this->orderId,
            'method' => 'unassigned',
            'status' => Payment::STATUS_PENDING,
            'amount' => 230.0,
            'gateway_reference' => 'gw-retry-2',
        ]);

        $latest = $this->repository->findByOrderId($this->orderId);

        $this->assertSame($second->id, $latest->id);
        $this->assertNotSame($first->id, $latest->id);
    }

    public function testUpdateStatusPersists(): void
    {
        $payment = $this->repository->create([
            'order_id' => $this->orderId,
            'method' => 'unassigned',
            'status' => Payment::STATUS_PENDING,
            'amount' => 230.0,
            'gateway_reference' => null,
        ]);

        $this->repository->updateStatus($payment->id, Payment::STATUS_SUCCEEDED);

        $reloaded = $this->repository->findByOrderId($this->orderId);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $reloaded->status);
    }

    public function testFindByOrderIdReturnsNullWhenNoPaymentExists(): void
    {
        $this->assertNull($this->repository->findByOrderId(999999));
    }
}
