-- Phase 5 / Administration domain migration
-- See docs/specs/02-administration.md §3, §19

CREATE TABLE IF NOT EXISTS `audit_log_entries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `domain` VARCHAR(64) NOT NULL,
    `action` VARCHAR(128) NOT NULL,
    `entity_type` VARCHAR(64) NOT NULL,
    `entity_id` VARCHAR(64) NOT NULL,
    `before_json` JSON DEFAULT NULL,
    `after_json` JSON DEFAULT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_log_domain` (`domain`),
    KEY `idx_audit_log_actor` (`actor_user_id`),
    KEY `idx_audit_log_created_at` (`created_at`),
    CONSTRAINT `fk_audit_log_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(160) NOT NULL,
    `value` TEXT NOT NULL,
    `value_type` ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_system_settings_key` (`key`),
    CONSTRAINT `fk_system_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feature_flags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(160) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `rollout_rules_json` JSON DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_feature_flags_key` (`key`),
    CONSTRAINT `fk_feature_flags_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
