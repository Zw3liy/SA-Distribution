-- Orders domain migration (docs/specs/06-orders.md §19)
--
-- Introduces the full order lifecycle: orders, order_items (each line
-- carrying a nullable inventory_reservation_id back-reference so
-- OrderService::transition() can consume/release the correct Inventory
-- reservation at the right point in the state machine),
-- order_status_history (the immutable audit trail of every transition),
-- and payments (an orchestration record only -- no card data, per §16).
-- Also adds converted_to_order_id to the existing `cart` table so a
-- checked-out cart is marked converted rather than deleted (§2/§13),
-- and CartRepository's getCartIdBySession() can exclude it so a
-- post-checkout add-to-cart creates a fresh cart row.

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(64) NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending_payment','paid','fulfilling','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending_payment',
    `subtotal` DECIMAL(12,2) NOT NULL,
    `tax_total` DECIMAL(12,2) NOT NULL,
    `grand_total` DECIMAL(12,2) NOT NULL,
    `shipping_address_id` INT UNSIGNED NOT NULL,
    `billing_address_id` INT UNSIGNED NOT NULL,
    `placed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_order_number` (`order_number`),
    KEY `idx_orders_customer` (`customer_id`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_status` (`status`),
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_shipping_address` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_billing_address` FOREIGN KEY (`billing_address_id`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- inventory_reservation_id is a documented extension beyond §3's literal
-- column list (docs/specs/06-orders.md), required so OrderService can
-- consume the correct StockReservation on transition to `fulfilling`
-- and release it on `cancelled`, without guessing by product_id alone
-- (which breaks if the same product appears twice on one order, or a
-- reservation was already partially consumed elsewhere).
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(128) NOT NULL,
    `name_snapshot` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price_snapshot` DECIMAL(12,2) NOT NULL,
    `line_total` DECIMAL(12,2) NOT NULL,
    `inventory_reservation_id` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    KEY `idx_order_items_reservation` (`inventory_reservation_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_order_items_reservation` FOREIGN KEY (`inventory_reservation_id`) REFERENCES `stock_reservations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `from_status` VARCHAR(32) DEFAULT NULL,
    `to_status` VARCHAR(32) NOT NULL,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `note` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_status_history_order` (`order_id`),
    CONSTRAINT `fk_order_status_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orchestration record only -- no card data (docs/specs/06-orders.md
-- §16). method='unassigned' until a real payment gateway is integrated
-- (explicitly out of scope for this domain per §19).
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `method` VARCHAR(64) NOT NULL,
    `status` ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    `amount` DECIMAL(12,2) NOT NULL,
    `gateway_reference` VARCHAR(128) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payments_order` (`order_id`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart-to-order conversion tracking (docs/specs/06-orders.md §2/§13):
-- carts are marked converted, not deleted, when checkout succeeds.
ALTER TABLE `cart`
    ADD COLUMN `converted_to_order_id` INT UNSIGNED DEFAULT NULL AFTER `session_id`,
    ADD KEY `idx_cart_converted_order` (`converted_to_order_id`),
    ADD CONSTRAINT `fk_cart_converted_order` FOREIGN KEY (`converted_to_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

-- Permission catalog (docs/specs/06-orders.md §16/§19), seeded following
-- the exact idempotent pattern established in every prior domain
-- migration.
INSERT INTO `permissions` (`name`, `description`)
SELECT * FROM (
    SELECT 'orders.order.view' AS name, 'View customer orders in the admin portal' AS description
    UNION ALL SELECT 'orders.order.manage', 'Transition order status and manage fulfillment in the admin portal'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `permissions`.`name` = seed.name
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.name IN ('orders.order.view', 'orders.order.manage')
WHERE r.name = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
