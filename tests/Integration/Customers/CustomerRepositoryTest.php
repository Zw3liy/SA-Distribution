<?php
declare(strict_types=1);

namespace Tests\Integration\Customers;

use App\Domains\Customers\Repositories\CustomerRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_customers_domain.sql migration applied, reachable
 * via the DB_HOST/DB_NAME/DB_USER/DB_PASS env vars (same convention as
 * tests/Integration/Catalog/ProductRepositoryTest.php).
 */
final class CustomerRepositoryTest extends TestCase
{
    private PDO $db;
    private CustomerRepository $repository;
    private int $userIdA;
    private int $userIdB;

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
        $this->repository = new CustomerRepository($this->db);

        $this->cleanup();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, account_kind)
             VALUES ('Repo', 'TestUserA', 'repo-test-customer-a@example.co.za', '0110000001', 'x', 'customer')"
        );
        $this->userIdA = (int) $this->db->lastInsertId();

        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, account_kind)
             VALUES ('Repo', 'TestUserB', 'repo-test-customer-b@example.co.za', '0110000002', 'x', 'customer')"
        );
        $this->userIdB = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM customer_users WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'repo-test-customer-%')");
        $this->db->exec("DELETE FROM customers WHERE company_name LIKE 'Repo Test%'");
        $this->db->exec("DELETE FROM users WHERE email LIKE 'repo-test-customer-%'");
    }

    public function testCreateAndFindByIdRoundTrips(): void
    {
        $customer = $this->repository->create(['account_type' => 'b2c', 'company_name' => null]);

        $this->assertGreaterThan(0, $customer->id);

        $found = $this->repository->findById($customer->id);
        $this->assertNotNull($found);
        $this->assertSame('b2c', $found->accountType);
    }

    public function testFindByUserIdIsASingleIndexedLookup(): void
    {
        $customer = $this->repository->create(['account_type' => 'b2c', 'company_name' => null]);
        $this->repository->linkUser($customer->id, $this->userIdA, true);

        $found = $this->repository->findByUserId($this->userIdA);
        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);

        $this->assertNull($this->repository->findByUserId($this->userIdB));
    }

    public function testIsUserLinkedReflectsCustomerUsersTable(): void
    {
        $customer = $this->repository->create(['account_type' => 'b2b', 'company_name' => 'Repo Test Co']);
        $this->repository->linkUser($customer->id, $this->userIdA, true);

        $this->assertTrue($this->repository->isUserLinked($customer->id, $this->userIdA));
        $this->assertFalse($this->repository->isUserLinked($customer->id, $this->userIdB));
    }

    public function testBuyersForReturnsPrimaryContactFirst(): void
    {
        $customer = $this->repository->create(['account_type' => 'b2b', 'company_name' => 'Repo Test Co']);
        $this->repository->linkUser($customer->id, $this->userIdA, true);
        $this->repository->linkUser($customer->id, $this->userIdB, false);

        $buyers = $this->repository->buyersFor($customer->id);

        $this->assertCount(2, $buyers);
        $this->assertTrue($buyers[0]['is_primary_contact']);
        $this->assertSame('repo-test-customer-a@example.co.za', $buyers[0]['email']);
    }

    public function testFindAllAndCountAllReflectInsertedRows(): void
    {
        $before = $this->repository->countAll();

        $this->repository->create(['account_type' => 'b2c', 'company_name' => null]);
        $this->repository->create(['account_type' => 'b2b', 'company_name' => 'Repo Test Co']);

        $this->assertSame($before + 2, $this->repository->countAll());

        $all = $this->repository->findAll(100, 0);
        $this->assertGreaterThanOrEqual($before + 2, count($all));
    }
}
