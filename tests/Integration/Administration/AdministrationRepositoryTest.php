<?php
declare(strict_types=1);

namespace Tests\Integration\Administration;

use App\Domains\Administration\Repositories\AuditLogRepository;
use App\Domains\Administration\Repositories\FeatureFlagRepository;
use App\Domains\Administration\Repositories\SystemSettingRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Requires a real MariaDB/MySQL instance with the sa_business schema
 * plus the 2026_07_21_administration_domain.sql migration applied,
 * reachable via the DB_HOST/DB_NAME/DB_USER/DB_PASS env vars (same
 * convention as tests/Integration/Identity/UserRepositoryTest.php).
 */
final class AdministrationRepositoryTest extends TestCase
{
    private PDO $db;
    private int $fixtureUserId;

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

        $this->db->exec("DELETE FROM audit_log_entries WHERE domain = 'admin-repo-test'");
        $this->db->exec("DELETE FROM system_settings WHERE `key` = 'admin-repo-test.setting'");
        $this->db->exec("DELETE FROM feature_flags WHERE `key` = 'admin-repo-test.flag'");
        $this->db->exec("DELETE FROM users WHERE email = 'admin-repo-test-fixture@example.co.za'");

        // The updated_by columns on system_settings/feature_flags are real
        // foreign keys into users (docs/specs/02-administration.md §3) --
        // a self-contained fixture row is required rather than a guessed id.
        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_active, is_verified, account_kind, created_at, updated_at)
             VALUES ('Fixture', 'User', 'admin-repo-test-fixture@example.co.za', '0000000000', 'x', 1, 0, 'staff', NOW(), NOW())"
        );
        $this->fixtureUserId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->db->exec("DELETE FROM audit_log_entries WHERE domain = 'admin-repo-test'");
        $this->db->exec("DELETE FROM system_settings WHERE `key` = 'admin-repo-test.setting'");
        $this->db->exec("DELETE FROM feature_flags WHERE `key` = 'admin-repo-test.flag'");
        $this->db->exec("DELETE FROM users WHERE email = 'admin-repo-test-fixture@example.co.za'");
    }

    public function testAuditLogInsertQueryAndCountRoundTrip(): void
    {
        $repo = new AuditLogRepository($this->db);

        $repo->insert([
            'actor_user_id' => $this->fixtureUserId,
            'domain' => 'admin-repo-test',
            'action' => 'thing.happened',
            'entity_type' => 'thing',
            'entity_id' => '42',
            'before' => ['a' => 1],
            'after' => ['a' => 2],
            'ip' => '127.0.0.1',
        ]);

        $entries = $repo->query(['domain' => 'admin-repo-test'], 10, 0);
        $this->assertCount(1, $entries);
        $this->assertSame('thing.happened', $entries[0]->action);
        $this->assertSame(['a' => 1], $entries[0]->before);
        $this->assertSame(['a' => 2], $entries[0]->after);
        $this->assertSame(1, $repo->count(['domain' => 'admin-repo-test']));
    }

    public function testSystemSettingUpsertIsIdempotentOnKey(): void
    {
        $repo = new SystemSettingRepository($this->db);

        $repo->upsert('admin-repo-test.setting', 'first', 'string', $this->fixtureUserId);
        $repo->upsert('admin-repo-test.setting', 'second', 'string', $this->fixtureUserId);

        $setting = $repo->get('admin-repo-test.setting');
        $this->assertNotNull($setting);
        $this->assertSame('second', $setting->value);
        $this->assertSame($this->fixtureUserId, $setting->updatedBy);
    }

    public function testFeatureFlagUpsertIsIdempotentOnKey(): void
    {
        $repo = new FeatureFlagRepository($this->db);

        $repo->upsert('admin-repo-test.flag', false, ['staff_only' => false], $this->fixtureUserId);
        $repo->upsert('admin-repo-test.flag', true, ['staff_only' => true], $this->fixtureUserId);

        $flag = $repo->get('admin-repo-test.flag');
        $this->assertNotNull($flag);
        $this->assertTrue($flag->isEnabled);
        $this->assertSame(['staff_only' => true], $flag->rolloutRules);
    }
}
