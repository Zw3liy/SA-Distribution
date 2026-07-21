-- Phase 5 / Catalog (PIM) domain migration
-- See docs/specs/03-catalog.md §3, §17, §19

CREATE TABLE IF NOT EXISTS `tax_classes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(128) NOT NULL,
    `default_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tax_classes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tax_classes` (`name`, `default_rate`) VALUES ('Standard VAT', 15.00);

-- §2/§4: attributes stored as JSON (EAV deliberately rejected for now),
-- plus the new tax classification link. Both nullable/additive so
-- every existing row remains valid without a backfill.
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `attributes_json` JSON NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `tax_class_id` INT UNSIGNED NULL AFTER `brand_id`;

-- Idempotency note: MySQL/MariaDB < 10.5 doesn't support
-- "ADD CONSTRAINT IF NOT EXISTS", so this migration assumes a single
-- clean apply per environment (matching the convention already
-- established by the Identity/Administration migrations).
ALTER TABLE `products`
    ADD CONSTRAINT `fk_products_tax_class` FOREIGN KEY (`tax_class_id`) REFERENCES `tax_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- §17: composite index for the listing/filter query's WHERE clause,
-- which previously only had single-column indexes implicitly via the
-- category/brand foreign keys.
ALTER TABLE `products`
    ADD INDEX `idx_products_active_cat_brand` (`is_active`, `category_id`, `brand_id`);

-- §3: schema-only for this phase -- see §19 for why no service/UI logic
-- populates or reads this table yet.
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(64) NOT NULL,
    `attributes_json` JSON NULL,
    `price_override` DECIMAL(12,2) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_variants_sku` (`sku`),
    KEY `idx_product_variants_product` (`product_id`),
    CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- §11/§16: permission catalog for Catalog's admin write paths, seeded
-- here since this is the first domain whose admin controllers actually
-- enforce the second defense-in-depth layer (account_kind=staff AND
-- permission). Also closes the permission-catalog gap flagged as a
-- deferred risk in docs/reports/PHASE5-ADMINISTRATION-COMPLETION-REPORT.md
-- by seeding Administration's own screen permissions at the same time --
-- purely additive, no behavior change to Administration's guard (which
-- already enforces account_kind=staff on its own).
INSERT IGNORE INTO `permissions` (`name`, `description`) VALUES
    ('catalog.product.view', 'View products in the admin catalog'),
    ('catalog.product.create', 'Create new products'),
    ('catalog.product.edit', 'Edit existing products'),
    ('catalog.product.deactivate', 'Deactivate products'),
    ('catalog.category.manage', 'Manage categories'),
    ('catalog.brand.manage', 'Manage brands'),
    ('staff.manage', 'Create and deactivate staff accounts'),
    ('settings.manage', 'Edit system settings'),
    ('feature_flags.manage', 'Toggle feature flags'),
    ('audit_log.view', 'View the audit log');

INSERT IGNORE INTO `roles` (`name`, `description`) VALUES
    ('staff', 'Baseline internal staff role -- granted every admin-portal permission defined so far');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.name IN (
    'catalog.product.view', 'catalog.product.create', 'catalog.product.edit', 'catalog.product.deactivate',
    'catalog.category.manage', 'catalog.brand.manage',
    'staff.manage', 'settings.manage', 'feature_flags.manage', 'audit_log.view'
)
WHERE r.name = 'staff';

-- Backfill: any user already marked account_kind='staff' before this
-- role existed gets it now, so permission enforcement doesn't lock out
-- accounts created during the Identity/Administration phases.
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id
FROM `users` u
JOIN `roles` r ON r.name = 'staff'
WHERE u.account_kind = 'staff';
