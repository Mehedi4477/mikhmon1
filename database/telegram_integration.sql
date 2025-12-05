-- Telegram Integration Schema
-- Add Telegram support to MikhMon Agent System
-- This file will be integrated into install.php

-- Add Telegram columns to agents table
ALTER TABLE `agents` 
ADD COLUMN `telegram_chat_id` VARCHAR(50) NULL AFTER `phone`,
ADD COLUMN `telegram_username` VARCHAR(100) NULL AFTER `telegram_chat_id`,
ADD COLUMN `preferred_channel` ENUM('whatsapp', 'telegram', 'both') DEFAULT 'whatsapp' AFTER `telegram_username`,
ADD INDEX `idx_telegram_chat_id` (`telegram_chat_id`);

-- Create telegram_settings table
CREATE TABLE IF NOT EXISTS `telegram_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Telegram settings
INSERT INTO `telegram_settings` (`setting_key`, `setting_value`) VALUES
('telegram_enabled', '0'),
('telegram_bot_token', ''),
('telegram_webhook_mode', '1'),
('telegram_admin_chat_ids', ''),
('telegram_welcome_message', '🤖 *Selamat datang di Bot MikhMon*\n\nKetik /help untuk melihat perintah yang tersedia.');

-- Create telegram_webhook_log table
CREATE TABLE IF NOT EXISTS `telegram_webhook_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `chat_id` VARCHAR(50) NOT NULL,
  `username` VARCHAR(100),
  `first_name` VARCHAR(100),
  `last_name` VARCHAR(100),
  `message` TEXT,
  `command` VARCHAR(50),
  `response` TEXT,
  `status` ENUM('success', 'error') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_chat_id` (`chat_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add telegram_chat_id to billing_customers table (if exists)
ALTER TABLE `billing_customers`
ADD COLUMN `telegram_chat_id` VARCHAR(50) NULL AFTER `phone`,
ADD INDEX `idx_telegram_chat_id` (`telegram_chat_id`);

-- Create telegram_user_mapping table for linking phone numbers to chat IDs
CREATE TABLE IF NOT EXISTS `telegram_user_mapping` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone` VARCHAR(20) NOT NULL,
  `telegram_chat_id` VARCHAR(50) NOT NULL,
  `telegram_username` VARCHAR(100),
  `first_name` VARCHAR(100),
  `last_name` VARCHAR(100),
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_code` VARCHAR(10),
  `verification_expires` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_phone` (`phone`),
  UNIQUE KEY `unique_chat_id` (`telegram_chat_id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
