-- ========================================
-- COMPLETE DATABASE INSTALLATION SCRIPT
-- MikhMon Agent System - Full Setup for Hosting
-- Updated with all fixes and compatibility patches
-- ========================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (optional - uncomment if needed)
-- DROP TABLE IF EXISTS agent_transactions;
-- DROP TABLE IF EXISTS agent_prices;
-- DROP TABLE IF EXISTS agent_profile_pricing;
-- DROP TABLE IF EXISTS public_sales;
-- DROP TABLE IF EXISTS payment_methods;
-- DROP TABLE IF EXISTS payment_gateway_config;
-- DROP TABLE IF EXISTS agent_settings;
-- DROP TABLE IF EXISTS agents;

-- ========================================
-- 1. AGENTS TABLE (Main table)
-- ========================================
CREATE TABLE IF NOT EXISTS `agents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_code` varchar(20) NOT NULL,
  `agent_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `address` text,
  `balance` decimal(15,2) DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `level` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_agent_code` (`agent_code`),
  UNIQUE KEY `unique_phone` (`phone`),
  KEY `idx_agent_code` (`agent_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 2. AGENT SETTINGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `agent_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(20) DEFAULT 'string',
  `description` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 3. AGENT PRICES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `agent_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `buy_price` decimal(15,2) NOT NULL,
  `sell_price` decimal(15,2) NOT NULL,
  `stock_limit` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_profile` (`profile_name`),
  CONSTRAINT `fk_agent_prices_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 4. AGENT TRANSACTIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `agent_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `transaction_type` enum('topup','generate_voucher','commission','withdrawal','penalty') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) DEFAULT 0.00,
  `balance_after` decimal(15,2) DEFAULT 0.00,
  `description` text,
  `reference_id` varchar(100) DEFAULT NULL,
  `voucher_code` varchar(50) DEFAULT NULL,
  `profile_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_reference_id` (`reference_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_agent_transactions_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 5. PAYMENT GATEWAY CONFIG TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `payment_gateway_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_sandbox` tinyint(1) DEFAULT 1,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `merchant_code` varchar(100) DEFAULT NULL,
  `callback_token` varchar(255) DEFAULT NULL,
  `config_json` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gateway` (`gateway_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 6. AGENT PROFILE PRICING TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `agent_profile_pricing` (
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
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_agent_profile` (`agent_id`,`profile_name`),
  KEY `idx_agent_id` (`agent_id`),
  CONSTRAINT `fk_agent_profile_pricing_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 7. PUBLIC SALES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `public_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(100) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `agent_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `profile_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','expired','failed','cancelled') DEFAULT 'pending',
  `voucher_code` varchar(50) DEFAULT NULL,
  `voucher_password` varchar(50) DEFAULT NULL,
  `voucher_generated` tinyint(1) DEFAULT 0,
  `payment_url` text,
  `payment_data` text,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_transaction_id` (`transaction_id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_customer_phone` (`customer_phone`),
  CONSTRAINT `fk_public_sales_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_public_sales_profile` FOREIGN KEY (`profile_id`) REFERENCES `agent_profile_pricing` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 8. PAYMENT METHODS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `fee_flat` decimal(10,2) DEFAULT 0.00,
  `fee_percent` decimal(5,2) DEFAULT 0.00,
  `minimum_amount` decimal(10,2) DEFAULT 0.00,
  `maximum_amount` decimal(10,2) DEFAULT 0.00,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gateway_code` (`gateway`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 9. VOUCHER SETTINGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS `voucher_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `validity` varchar(50) DEFAULT NULL,
  `data_limit` varchar(50) DEFAULT NULL,
  `speed_limit` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_profile_name` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- INSERT DEFAULT DATA
-- ========================================

-- Insert default agent settings
INSERT IGNORE INTO `agent_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('system_name', 'MikhMon Agent System', 'string', 'Nama sistem'),
('default_commission', '10.00', 'number', 'Komisi default untuk agent baru (%)'),
('min_topup_amount', '10000', 'number', 'Minimum amount untuk topup'),
('max_topup_amount', '1000000', 'number', 'Maximum amount untuk topup'),
('voucher_prefix', 'VM-', 'string', 'Prefix untuk kode voucher'),
('whatsapp_enabled', '1', 'boolean', 'Enable WhatsApp notifications'),
('email_enabled', '0', 'boolean', 'Enable email notifications'),
('auto_generate_voucher', '1', 'boolean', 'Auto generate voucher setelah payment'),
('payment_expiry_hours', '24', 'number', 'Jam expiry untuk payment pending');

-- Insert default payment gateway config (Tripay sandbox)
INSERT IGNORE INTO `payment_gateway_config` (`gateway_name`, `is_active`, `is_sandbox`, `api_key`, `merchant_code`) VALUES
('tripay', 0, 1, 'your-tripay-api-key', 'your-merchant-code');

-- Insert sample payment methods (Tripay)
INSERT IGNORE INTO `payment_methods` (`gateway`, `code`, `name`, `type`, `fee_flat`, `fee_percent`, `minimum_amount`, `maximum_amount`, `is_active`) VALUES
('tripay', 'BRIVA', 'BRI Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
('tripay', 'BNIVA', 'BNI Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
('tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
('tripay', 'PERMATAVA', 'Permata Virtual Account', 'virtual_account', 4000, 0, 10000, 50000000, 1),
('tripay', 'QRIS', 'QRIS (Quick Response Code)', 'qris', 750, 0.7, 1500, 10000000, 1),
('tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 0, 2.5, 10000, 10000000, 1),
('tripay', 'DANA', 'DANA', 'ewallet', 0, 2.5, 10000, 10000000, 1),
('tripay', 'OVO', 'OVO', 'ewallet', 0, 2.5, 10000, 10000000, 1),
('tripay', 'GOPAY', 'GoPay', 'ewallet', 0, 2.5, 10000, 10000000, 1);

-- ========================================
-- VERIFICATION QUERIES
-- ========================================
-- Uncomment these to verify installation:
-- SELECT 'agents' as table_name, COUNT(*) as record_count FROM agents
-- UNION ALL
-- SELECT 'agent_settings', COUNT(*) FROM agent_settings
-- UNION ALL
-- SELECT 'agent_prices', COUNT(*) FROM agent_prices
-- UNION ALL
-- SELECT 'agent_transactions', COUNT(*) FROM agent_transactions
-- UNION ALL
-- SELECT 'payment_gateway_config', COUNT(*) FROM payment_gateway_config
-- UNION ALL
-- SELECT 'agent_profile_pricing', COUNT(*) FROM agent_profile_pricing
-- UNION ALL
-- SELECT 'public_sales', COUNT(*) FROM public_sales
-- UNION ALL
-- SELECT 'payment_methods', COUNT(*) FROM payment_methods
-- UNION ALL
-- SELECT 'voucher_settings', COUNT(*) FROM voucher_settings;

-- ========================================
-- INSTALLATION COMPLETE
-- ========================================
