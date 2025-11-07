-- Fix Tables Script - Safe Version
-- Script ini akan drop dan recreate table jika ada masalah

-- Drop tables jika ada (hati-hati: data akan hilang!)
-- Urutan drop harus sesuai dengan foreign key dependencies
SET FOREIGN_KEY_CHECKS = 0;

-- Drop child tables first (yang punya foreign key)
DROP TABLE IF EXISTS `agent_profile_pricing`;
DROP TABLE IF EXISTS `public_sales`;
DROP TABLE IF EXISTS `payment_methods`;
DROP TABLE IF EXISTS `agent_settings`;

-- Drop parent table last
DROP TABLE IF EXISTS `agents`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Create agents table
CREATE TABLE `agents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_code` varchar(20) NOT NULL,
  `agent_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_agent_code` (`agent_code`)
);

-- 2. Create agent_profile_pricing table
CREATE TABLE `agent_profile_pricing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `icon` varchar(50) DEFAULT 'fa-wifi',
  `color` varchar(20) DEFAULT 'blue',
  `user_type` enum('voucher','member') DEFAULT 'voucher',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`),
  FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
);

-- 3. Create agent_settings table
CREATE TABLE `agent_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
);

-- 4. Create public_sales table
CREATE TABLE `public_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(50) NOT NULL,
  `agent_code` varchar(20) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `package_name` varchar(100) NOT NULL,
  `package_price` decimal(10,2) NOT NULL,
  `admin_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','paid','failed','expired') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_url` text,
  `voucher_username` varchar(50) DEFAULT NULL,
  `voucher_password` varchar(50) DEFAULT NULL,
  `voucher_generated_at` timestamp NULL DEFAULT NULL,
  `voucher_sent_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_transaction_id` (`transaction_id`),
  KEY `idx_agent_code` (`agent_code`),
  KEY `idx_payment_status` (`payment_status`)
);

-- 5. Create payment_methods table
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `admin_fee_type` enum('percentage','fixed') DEFAULT 'percentage',
  `admin_fee_value` decimal(10,2) DEFAULT 0.00,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_amount` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `config` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Insert default data
INSERT INTO `agents` (`id`, `agent_code`, `agent_name`, `status`) VALUES
(1, 'AG001', 'Agent Demo', 'active'),
(2, 'AG5136', 'tester', 'active'),
(3, 'PUBLIC', 'Public Catalog', 'active');

-- Insert default settings
INSERT INTO `agent_settings` (`setting_key`, `setting_value`, `description`) VALUES
('voucher_username_password_same', '0', 'Username dan password sama'),
('voucher_username_type', 'random', 'Tipe username: random, numeric, alphabetic'),
('voucher_username_length', '8', 'Panjang username'),
('voucher_password_type', 'random', 'Tipe password: random, numeric, alphabetic'),
('voucher_password_length', '8', 'Panjang password'),
('voucher_prefix_enabled', '0', 'Enable prefix untuk voucher'),
('voucher_prefix', '', 'Prefix untuk voucher'),
('voucher_uppercase', '0', 'Uppercase voucher'),
('whatsapp_api_url', '', 'WhatsApp API URL'),
('whatsapp_api_key', '', 'WhatsApp API Key');

-- Insert default payment method
INSERT INTO `payment_methods` (`id`, `name`, `type`, `display_name`, `icon`, `admin_fee_type`, `admin_fee_value`, `is_active`) VALUES
(1, 'Tripay', 'tripay', 'Tripay Payment', 'fa-credit-card', 'percentage', 2.50, 1);

-- Show created tables
SHOW TABLES;

-- Verify table structures
SELECT 'agents' as table_name, COUNT(*) as row_count FROM agents
UNION ALL
SELECT 'agent_profile_pricing', COUNT(*) FROM agent_profile_pricing
UNION ALL
SELECT 'agent_settings', COUNT(*) FROM agent_settings
UNION ALL
SELECT 'public_sales', COUNT(*) FROM public_sales
UNION ALL
SELECT 'payment_methods', COUNT(*) FROM payment_methods;
