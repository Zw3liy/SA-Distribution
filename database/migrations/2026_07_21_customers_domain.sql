-- Customers domain migration (docs/specs/04-customers.md §19)
--
-- Introduces the `customers` bounded context as the new source of truth
-- for account-type (b2c/b2b), company identity, and credit terms, and
-- re-parents `addresses` from `users` to `customers` so a single B2B
-- account can have multiple linked buyers sharing one address book.
-- `users.company_name` is deliberately left in place and unmodified per
-- §2's compatibility requirement -- it remains the Identity domain's own
-- field, read by pre-existing views, while `customers.company_name`
-- becomes the new parallel source of truth going forward.

CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_type` ENUM('b2c','b2b') NOT NULL DEFAULT 'b2c',
    `company_name` VARCHAR(255) DEFAULT NULL,
    `parent_customer_id` INT UNSIGNED DEFAULT NULL,
    `credit_terms` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customers_parent` (`parent_customer_id`),
    CONSTRAINT `fk_customers_parent` FOREIGN KEY (`parent_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links Identity users to Customer accounts. A b2c Customer normally has
-- exactly one linked user (its owner, is_primary_contact=1); a b2b
-- Customer can have several linked buyers, one of which is primary
-- (docs/specs/04-customers.md §2/§10). Indexed on user_id per §17 so
-- CustomerRepository::findByUserId() is a single indexed lookup, not a
-- table scan.
CREATE TABLE IF NOT EXISTS `customer_users` (
    `customer_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `is_primary_contact` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`customer_id`, `user_id`),
    KEY `idx_customer_users_user` (`user_id`),
    CONSTRAINT `fk_customer_users_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_customer_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-parent addresses from users to customers (docs/specs/04-customers.md
-- §2/§18). user_id is kept as a nullable, documented-deprecated
-- compatibility column rather than dropped outright, since dropping it
-- outright is a one-way door this migration does not need to take.
-- `type` gains 'both' (address usable for either billing or shipping),
-- required by AddressService's clearDefaultForType() semantics.
ALTER TABLE `addresses`
    MODIFY COLUMN `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'Deprecated: superseded by customer_id. Kept for backward compatibility only.',
    ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`,
    MODIFY COLUMN `type` ENUM('billing','shipping','both') NOT NULL DEFAULT 'shipping';

ALTER TABLE `addresses`
    ADD KEY `idx_addresses_customer` (`customer_id`),
    ADD CONSTRAINT `fk_addresses_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

-- Permission catalog (docs/specs/04-customers.md §16/§19), seeded
-- following the exact pattern established in
-- 2026_07_21_catalog_domain.sql: insert if missing, grant to the
-- baseline `staff` role if not already granted.
INSERT INTO `permissions` (`name`, `description`)
SELECT * FROM (
    SELECT 'customers.account.view' AS name, 'View customer accounts in the admin portal' AS description
    UNION ALL SELECT 'customers.account.edit', 'Edit customer account details in the admin portal'
    UNION ALL SELECT 'customers.b2b.manage', 'Manage B2B buyer linkage for customer accounts'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permissions`.`name` = seed.name
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.name IN ('customers.account.view', 'customers.account.edit', 'customers.b2b.manage')
WHERE r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
