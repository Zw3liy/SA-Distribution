<?php
declare(strict_types=1);

namespace Tests\Integration\Identity;

use App\Domains\Identity\Repositories\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * loaded, reachable via the DB_HOST/DB_NAME/DB_USER/DB_PASS env vars
 * (same convention as the app itself -- see config/app.php). Run as
 * part of the same live-database verification pass used for every
 * phase of this project, not against a mock.
 */
final class UserRepositoryTest extends TestCase
{
    private PDO $db;
    private UserRepository $repository;

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
        $this->repository = new UserRepository($this->db);

        // Clean slate for this test's fixture email so re-runs are idempotent.
        $this->db->exec("DELETE FROM login_attempts WHERE email = 'repo-test@example.co.za'");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test@example.co.za'");
    }

    protected function tearDown(): void
    {
        $this->db->exec("DELETE FROM login_attempts WHERE email = 'repo-test@example.co.za'");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test@example.co.za'");
    }

    public function testCreateAndFindByEmailRoundTrips(): void
    {
        $id = $this->repository->create([
            'first_name' => 'Repo',
            'last_name' => 'Test',
            'email' => 'repo-test@example.co.za',
            'phone' => '0123456789',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'account_kind' => 'staff',
        ]);

        $this->assertGreaterThan(0, $id);

        $found = $this->repository->findByEmail('repo-test@example.co.za');
        $this->assertNotNull($found);
        $this->assertSame('Repo', $found->firstName);
        $this->assertSame('staff', $found->accountKind);
        $this->assertTrue($found->isStaff());
    }

    public function testRecentFailedAttemptsCountsOnlyWithinWindow(): void
    {
        $this->repository->create([
            'first_name' => 'Repo', 'last_name' => 'Test', 'email' => 'repo-test@example.co.za',
            'phone' => '0123456789', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $this->assertSame(0, $this->repository->recentFailedAttempts('repo-test@example.co.za', '10.0.0.1', 900));

        $this->repository->recordLoginAttempt('repo-test@example.co.za', '10.0.0.1', false);
        $this->repository->recordLoginAttempt('repo-test@example.co.za', '10.0.0.1', false);
        $this->repository->recordLoginAttempt('repo-test@example.co.za', '10.0.0.1', true); // success doesn't count

        $this->assertSame(2, $this->repository->recentFailedAttempts('repo-test@example.co.za', '10.0.0.1', 900));
        // A different IP for the same email must not count toward that IP's lockout.
        $this->assertSame(0, $this->repository->recentFailedAttempts('repo-test@example.co.za', '10.0.0.2', 900));
    }

    public function testDefaultAccountKindIsCustomer(): void
    {
        $this->repository->create([
            'first_name' => 'Repo', 'last_name' => 'Test', 'email' => 'repo-test@example.co.za',
            'phone' => '0123456789', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
        ]);

        $found = $this->repository->findByEmail('repo-test@example.co.za');
        $this->assertSame('customer', $found->accountKind);
        $this->assertFalse($found->isStaff());
    }
}
