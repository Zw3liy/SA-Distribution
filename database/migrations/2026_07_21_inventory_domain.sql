-- Inventory domain migration (docs/specs/05-inventory.md §19)
--
-- Introduces multi-location stock tracking (warehouses, inventory_items),
-- reservation lifecycle (stock_reservations), and a full audit trail of
-- every on-hand quantity change (stock_movements). products.stock is
-- kept as a read-only compatibility mirror per §2 -- this migration
-- backfills it as an exact InventoryItem for every existing active
-- product against a single default warehouse, so Catalog's existing
-- read paths keep working unchanged.

CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `code` VARCHAR(32) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_warehouses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `quantity_on_hand` INT NOT NULL DEFAULT 0,
    `quantity_reserved` INT NOT NULL DEFAULT 0,
    `reorder_threshold` INT NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inventory_items_product_warehouse` (`product_id`, `warehouse_id`),
    KEY `idx_inventory_items_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_inventory_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inventory_items_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_reservations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `order_reference` VARCHAR(64) NOT NULL,
    `quantity` INT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `status` ENUM('active','consumed','released') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_stock_reservations_item` (`inventory_item_id`),
    KEY `idx_stock_reservations_status_expiry` (`status`, `expires_at`),
    CONSTRAINT `fk_stock_reservations_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inventory_item_id` INT UNSIGNED NOT NULL,
    `delta` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `reference_type` VARCHAR(64) DEFAULT NULL,
    `reference_id` VARCHAR(64) DEFAULT NULL,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_stock_movements_item` (`inventory_item_id`),
    CONSTRAINT `fk_stock_movements_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stock_movements_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a single default warehouse (docs/specs/05-inventory.md §19:
-- "Inventory's warehouses table here is intentionally minimal"). This is
-- the "first active, lowest id" default used by
-- WarehouseRepository::findDefault() until the Warehouse domain (#7)
-- introduces a real default-location concept.
INSERT INTO `warehouses` (`name`, `code`, `is_active`)
SELECT 'Main Warehouse', 'MAIN', 1
WHERE NOT EXISTS (SELECT 1 FROM `warehouses` WHERE `code` = 'MAIN');

-- Backfill: one InventoryItem per existing product against the default
-- warehouse, seeded from the current products.stock value so the
-- transition preserves whatever stock counts already existed --
-- reversing the usual direction (Inventory becomes the source of truth
-- going forward, but must start from Catalog's existing numbers, not
-- zero, or every product would appear instantly out-of-stock).
INSERT INTO `inventory_items` (`product_id`, `warehouse_id`, `quantity_on_hand`, `quantity_reserved`, `reorder_threshold`, `updated_at`)
SELECT p.id, w.id, p.stock, 0, 0, NOW()
FROM `products` p
CROSS JOIN (SELECT id FROM `warehouses` WHERE `code` = 'MAIN' LIMIT 1) w
WHERE NOT EXISTS (
    SELECT 1 FROM `inventory_items` ii WHERE ii.product_id = p.id AND ii.warehouse_id = w.id
);

-- Permission catalog (docs/specs/05-inventory.md §11/§19), seeded
-- following the same idempotent pattern established in the Catalog and
-- Customers migrations.
INSERT INTO `permissions` (`name`, `description`)
SELECT * FROM (
    SELECT 'inventory.stock.view' AS name, 'View stock levels in the admin portal' AS description
    UNION ALL SELECT 'inventory.stock.adjust', 'Adjust on-hand stock levels (audit-trailed)'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permissions`.`name` = seed.name
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.name IN ('inventory.stock.view', 'inventory.stock.adjust')
WHERE r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
