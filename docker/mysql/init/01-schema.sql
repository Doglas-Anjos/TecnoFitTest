-- TecnoFit Digital Account Database Schema
-- This script runs automatically on first MySQL container initialization
-- Note: Tables are created in the database specified by MYSQL_DATABASE env var

-- Account table
CREATE TABLE IF NOT EXISTS `account` (
    `id` CHAR(36) NOT NULL,
    `cpf` CHAR(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `locked` BOOLEAN NOT NULL DEFAULT FALSE,
    `locked_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_account_cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account Withdraw table
CREATE TABLE IF NOT EXISTS `account_withdraw` (
    `id` CHAR(36) NOT NULL,
    `account_id` CHAR(36) NOT NULL,
    `method` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL,
    `scheduled` BOOLEAN NOT NULL DEFAULT FALSE,
    `scheduled_for` DATETIME NULL,
    `done` BOOLEAN NOT NULL DEFAULT FALSE,
    `error` BOOLEAN NOT NULL DEFAULT FALSE,
    `error_reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_account_withdraw_account_id` (`account_id`),
    INDEX `idx_account_withdraw_scheduled` (`scheduled`, `done`, `error`, `scheduled_for`),
    CONSTRAINT `fk_account_withdraw_account`
        FOREIGN KEY (`account_id`) REFERENCES `account`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account Withdraw PIX table (snapshot of PIX used for this withdrawal)
CREATE TABLE IF NOT EXISTS `account_withdraw_pix` (
    `id` CHAR(36) NOT NULL,
    `account_withdraw_id` CHAR(36) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `key` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_account_withdraw_pix_withdraw_id` (`account_withdraw_id`),
    CONSTRAINT `fk_account_withdraw_pix_withdraw`
        FOREIGN KEY (`account_withdraw_id`) REFERENCES `account_withdraw`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments to tables
ALTER TABLE `account` COMMENT = 'Digital account with balance';
ALTER TABLE `account_withdraw` COMMENT = 'Withdrawal requests from accounts';
ALTER TABLE `account_withdraw_pix` COMMENT = 'PIX details snapshot for withdrawals';
