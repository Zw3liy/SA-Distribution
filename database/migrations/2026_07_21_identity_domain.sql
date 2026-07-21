-- Phase 5 / Identity domain migration
-- See docs/specs/01-identity.md §3, §19

-- Staff vs customer identity split (§2, §16).
ALTER TABLE `users`
    ADD COLUMN `account_kind` ENUM('customer','staff') NOT NULL DEFAULT 'customer' AFTER `notifications_updates`;

-- Composite index for the rate-limit lookup added in AuthService --
-- the existing single-column indexes on email/ip_address alone don't
-- cover the (email, ip, attempted_at) query pattern efficiently (§17).
ALTER TABLE `login_attempts`
    ADD INDEX `idx_login_attempts_email_ip_time` (`email`, `ip_address`, `attempted_at`);

-- API credentials for the future API Platform domain, built ahead of
-- need per the Phase 4 blueprint (§3, §19). Only a hash of the token is
-- ever stored -- see ApiCredentialService::issue().
CREATE TABLE IF NOT EXISTS `api_credentials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `scopes` JSON NOT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_credentials_token_hash` (`token_hash`),
    KEY `idx_api_credentials_user` (`user_id`),
    CONSTRAINT `fk_api_credentials_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
