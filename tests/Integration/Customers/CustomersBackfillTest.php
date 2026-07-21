<?php
declare(strict_types=1);

namespace Tests\Integration\Customers;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Dedicated migration/backfill regression test, per
 * docs/specs/04-customers.md §18: proves the user_id -> customer_id
 * re-parenting behaves correctly against a real database, using the
 * exact statements database/migrations/2026_07_21_customers_backfill.php
 * runs (kept in sync manually, since the script is a one-off CLI tool,
 * not an autoloaded class, and is not meant to be re-run as part of
 * normal application code).
 */
final class CustomersBackfillTest extends TestCase
{
    private PDO $db;
    private int $legacyUserId;
    private int $legacyAddressId;

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

        $this->cleanup();

        // Simulate a pre-migration "legacy" customer user: has an
        // address rows still keyed by user_id, and no customers/
        // customer_users rows yet -- exactly the state every real
        // account_kind='customer' user was in before this migration.
        $this->db->exec(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, account_kind)
             VALUES ('Repo', 'BackfillUser', 'repo-test-backfill-user@example.co.za', '0110000009', 'x', 'customer')"
        );
        $this->legacyUserId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO addresses (user_id, label, is_default, address_line_1, city, region, postal_code, country, type)
             VALUES (:user_id, 'Repo Test Backfill Address', 1, '1 Legacy Street', 'Cape Town', 'Western Cape', '8001', 'South Africa', 'shipping')"
        );
        $stmt->execute(['user_id' => $this->legacyUserId]);
        $this->legacyAddressId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->db->exec("DELETE FROM addresses WHERE label = 'Repo Test Backfill Address'");
        $this->db->exec("DELETE FROM customer_users WHERE user_id IN (SELECT id FROM users WHERE email = 'repo-test-backfill-user@example.co.za')");
        $this->db->exec("DELETE FROM customers WHERE id IN (SELECT customer_id FROM customer_users cu JOIN users u ON u.id = cu.user_id WHERE u.email = 'repo-test-backfill-user@example.co.za')");
        $this->db->exec("DELETE FROM users WHERE email = 'repo-test-backfill-user@example.co.za'");
    }

    /**
     * Runs the same logic as
     * database/migrations/2026_07_21_customers_backfill.php for a
     * single unlinked user and asserts the resulting state: a b2c
     * Customer created, linked as primary contact, and the user's
     * pre-existing address re-parented to the new customer_id while its
     * deprecated user_id is left untouched (compatibility, per §3).
     */
    public function testBackfillCreatesCustomerLinksUserAndReparentsAddresses(): void
    {
        $stmt = $this->db->prepare(
            'SELECT u.id AS user_id
             FROM users u
             LEFT JOIN customer_users cu ON cu.user_id = u.id
             WHERE u.account_kind = :account_kind AND cu.user_id IS NULL AND u.id = :user_id'
        );
        $stmt->execute(['account_kind' => 'customer', 'user_id' => $this->legacyUserId]);
        $unlinked = $stmt->fetchAll();
        $this->assertCount(1, $unlinked, 'Fixture user should be unlinked before backfill runs.');

        $this->db->exec("INSERT INTO customers (account_type, company_name) VALUES ('b2c', NULL)");
        $customerId = (int) $this->db->lastInsertId();

        $link = $this->db->prepare('INSERT INTO customer_users (customer_id, user_id, is_primary_contact) VALUES (:customer_id, :user_id, 1)');
        $link->execute(['customer_id' => $customerId, 'user_id' => $this->legacyUserId]);

        $reparent = $this->db->prepare('UPDATE addresses SET customer_id = :customer_id WHERE user_id = :user_id AND customer_id IS NULL');
        $reparent->execute(['customer_id' => $customerId, 'user_id' => $this->legacyUserId]);
        $this->assertSame(1, $reparent->rowCount());

        $addressStmt = $this->db->prepare('SELECT user_id, customer_id FROM addresses WHERE id = :id');
        $addressStmt->execute(['id' => $this->legacyAddressId]);
        $address = $addressStmt->fetch();

        $this->assertSame($customerId, (int) $address['customer_id']);
        $this->assertSame($this->legacyUserId, (int) $address['user_id'], 'user_id must remain populated for backward compatibility, not nulled out.');

        $linkStmt = $this->db->prepare('SELECT is_primary_contact FROM customer_users WHERE customer_id = :customer_id AND user_id = :user_id');
        $linkStmt->execute(['customer_id' => $customerId, 'user_id' => $this->legacyUserId]);
        $this->assertSame(1, (int) $linkStmt->fetchColumn());
    }

    public function testBackfillIsIdempotentForAlreadyLinkedUsers(): void
    {
        $this->db->exec("INSERT INTO customers (account_type, company_name) VALUES ('b2c', NULL)");
        $customerId = (int) $this->db->lastInsertId();
        $link = $this->db->prepare('INSERT INTO customer_users (customer_id, user_id, is_primary_contact) VALUES (:customer_id, :user_id, 1)');
        $link->execute(['customer_id' => $customerId, 'user_id' => $this->legacyUserId]);

        $stmt = $this->db->prepare(
            'SELECT u.id AS user_id
             FROM users u
             LEFT JOIN customer_users cu ON cu.user_id = u.id
             WHERE u.account_kind = :account_kind AND cu.user_id IS NULL AND u.id = :user_id'
        );
        $stmt->execute(['account_kind' => 'customer', 'user_id' => $this->legacyUserId]);

        $this->assertCount(0, $stmt->fetchAll(), 'Already-linked users must be excluded from a re-run, making the backfill a no-op for them.');
    }
}
