-- Warehouse domain migration (docs/specs/07-warehouse.md §19)
--
-- The physical execution layer over Inventory: pick lists generated from
-- Orders (paid->fulfilling), packing slips, goods receipts, inter-warehouse
-- stock transfers, and shipments. Warehouse never writes inventory_items
-- directly -- it calls InventoryServiceInterface (consumeReservation /
-- adjust) per §2 -- but it owns the operational tables below.
--
-- purchase_order_id on goods_receipts is deliberately a plain nullable
-- column with NO foreign key: the suppliers table it would reference does
-- not exist until Suppliers (domain #8) is implemented. Documented on the
-- GoodsReceiptService itself; the column is created now so receipts taken
-- before #8 lands can be linked retroactively.

-- Operational extension of the Inventory-owned `warehouses` table (§3/§4):
-- additive columns only; Inventory's minimal model keeps working unchanged.
ALTER TABLE `warehouses`
    ADD COLUMN `address_id` INT UNSIGNED DEFAULT NULL AFTER `code`,
    ADD COLUMN `zone_count` INT NOT NULL DEFAULT 0 AFTER `address_id`;

CREATE TABLE IF NOT EXISTS `pick_lists` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `status` ENUM('open','picking','picked','packed','shipped','cancelled') NOT NULL DEFAULT 'open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pick_lists_order` (`order_id`),
    KEY `idx_pick_lists_status` (`status`),
    CONSTRAINT `fk_pick_lists_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pick_lists_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pick_list_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pick_list_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL,
    `picked_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pick_list_items_list` (`pick_list_id`),
    KEY `idx_pick_list_items_product` (`product_id`),
    CONSTRAINT `fk_pick_list_items_list` FOREIGN KEY (`pick_list_id`) REFERENCES `pick_lists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pick_list_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One packing slip per pick list (§2: a shipment requires every line
-- picked AND packed).
CREATE TABLE IF NOT EXISTS `packing_slips` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pick_list_id` INT UNSIGNED NOT NULL,
    `packed_by_user_id` INT UNSIGNED NOT NULL,
    `packed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_packing_slips_pick_list` (`pick_list_id`),
    CONSTRAINT `fk_packing_slips_pick_list` FOREIGN KEY (`pick_list_id`) REFERENCES `pick_lists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_packing_slips_user` FOREIGN KEY (`packed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED DEFAULT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `received_by_user_id` INT UNSIGNED NOT NULL,
    `reference` VARCHAR(128) DEFAULT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_goods_receipts_warehouse` (`warehouse_id`),
    KEY `idx_goods_receipts_received_by` (`received_by_user_id`),
    CONSTRAINT `fk_goods_receipts_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_goods_receipts_received_by` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `goods_receipt_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_goods_receipt_items_receipt` (`goods_receipt_id`),
    KEY `idx_goods_receipt_items_product` (`product_id`),
    CONSTRAINT `fk_goods_receipt_items_receipt` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_goods_receipt_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_warehouse_id` INT UNSIGNED NOT NULL,
    `to_warehouse_id` INT UNSIGNED NOT NULL,
    `status` ENUM('in_transit','completed','cancelled') NOT NULL DEFAULT 'in_transit',
    `initiated_by_user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_stock_transfers_from` (`from_warehouse_id`),
    KEY `idx_stock_transfers_to` (`to_warehouse_id`),
    KEY `idx_stock_transfers_status` (`status`),
    CONSTRAINT `fk_stock_transfers_from` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_stock_transfers_to` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_stock_transfers_initiated_by` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stock_transfer_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_stock_transfer_items_transfer` (`stock_transfer_id`),
    KEY `idx_stock_transfer_items_product` (`product_id`),
    CONSTRAINT `fk_stock_transfer_items_transfer` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stock_transfer_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `pick_list_id` INT UNSIGNED NOT NULL,
    `carrier` VARCHAR(120) NOT NULL,
    `tracking_number` VARCHAR(128) DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED NOT NULL,
    `shipped_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shipments_pick_list` (`pick_list_id`),
    KEY `idx_shipments_order` (`order_id`),
    KEY `idx_shipments_carrier` (`carrier`),
    CONSTRAINT `fk_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_shipments_pick_list` FOREIGN KEY (`pick_list_id`) REFERENCES `pick_lists` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_shipments_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission catalog (docs/specs/07-warehouse.md §11/§19), seeded
-- following the exact idempotent pattern established in every prior
-- domain migration. Split per operational role: a picker does not
-- automatically get receiving or transfer access.
INSERT INTO `permissions` (`name`, `description`)
SELECT * FROM (
    SELECT 'warehouse.pick.manage' AS name, 'View and execute pick lists in the admin portal' AS description
    UNION ALL SELECT 'warehouse.ship.manage', 'Create shipments in the admin portal'
    UNION ALL SELECT 'warehouse.receive.manage', 'Enter goods receipts in the admin portal'
    UNION ALL SELECT 'warehouse.transfer.manage', 'Initiate and complete stock transfers in the admin portal'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permissions`.`name` = seed.name
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.name IN ('warehouse.pick.manage', 'warehouse.ship.manage', 'warehouse.receive.manage', 'warehouse.transfer.manage')
WHERE r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
