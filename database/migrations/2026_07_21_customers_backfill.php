<?php

declare(strict_types=1);

/**
 * One-off, idempotent data backfill for the Customers domain migration
 * (docs/specs/04-customers.md §18/§19).
 *
 * Not part of application runtime -- run manually once, after
 * 2026_07_21_customers_domain.sql has been applied, via:
 *
 *   php database/migrations/2026_07_21_customers_backfill.php
 *
 * What it does, for every `users` row with account_kind = 'customer'
 * that has no linked `customers` row yet:
 *   1. Creates a b2c `customers` row.
 *   2. Links it via `customer_users` (is_primary_contact = 1).
 *   3. Re-parents that user's existing `addresses` rows (if any) from
 *      user_id to the new customer_id.
 *
 * Implemented as PHP rather than a pure SQL INSERT...SELECT because step
 * 2 needs each newly-created customer's own auto-increment id to link
 * back to its source user -- a bulk INSERT...SELECT cannot correlate
 * that per-row without a database-specific trick, and this script is
 * clearer and safer than one. Idempotent: re-running it is a no-op for
 * any user that already has a linked customer.
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;

$config = new Config(__DIR__ . '/../../config/app.php');
$pdo = (new Database($config->get('db')))->getConnection();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'SELECT u.id AS user_id
         FROM users u
         LEFT JOIN customer_users cu ON cu.user_id = u.id
         WHERE u.account_kind = :account_kind
           AND cu.user_id IS NULL'
    );
    $stmt->execute(['account_kind' => 'customer']);
    $unlinkedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;
    $addressesReparented = 0;

    $insertCustomer = $pdo->prepare(
        "INSERT INTO customers (account_type, company_name, created_at, updated_at)
         VALUES ('b2c', NULL, NOW(), NOW())"
    );
    $linkUser = $pdo->prepare(
        'INSERT INTO customer_users (customer_id, user_id, is_primary_contact, created_at)
         VALUES (:customer_id, :user_id, 1, NOW())'
    );
    $reparentAddresses = $pdo->prepare(
        'UPDATE addresses SET customer_id = :customer_id WHERE user_id = :user_id AND customer_id IS NULL'
    );

    foreach ($unlinkedUsers as $row) {
        $userId = (int) $row['user_id'];

        $insertCustomer->execute();
        $customerId = (int) $pdo->lastInsertId();

        $linkUser->execute(['customer_id' => $customerId, 'user_id' => $userId]);

        $reparentAddresses->execute(['customer_id' => $customerId, 'user_id' => $userId]);
        $addressesReparented += $reparentAddresses->rowCount();

        $created++;
    }

    $pdo->commit();

    echo "Customers backfill complete.\n";
    echo "  Customer accounts created: {$created}\n";
    echo "  Addresses re-parented:     {$addressesReparented}\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, 'Backfill failed, rolled back: ' . $exception->getMessage() . "\n");
    exit(1);
}
