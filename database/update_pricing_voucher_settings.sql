-- Add voucher generation settings to agent_profile_pricing table
-- Run this SQL to add new columns for voucher/member settings

ALTER TABLE `agent_profile_pricing` 
ADD COLUMN `user_type` ENUM('voucher', 'member') DEFAULT 'voucher' AFTER `color`,
ADD COLUMN `username_format` ENUM('letters', 'numbers', 'mixed') DEFAULT 'mixed' AFTER `user_type`,
ADD COLUMN `username_length` INT(2) DEFAULT 8 AFTER `username_format`,
ADD COLUMN `password_format` ENUM('letters', 'numbers', 'mixed') DEFAULT 'mixed' AFTER `username_length`,
ADD COLUMN `password_length` INT(2) DEFAULT 8 AFTER `password_format`;

-- Update existing records to have default values
UPDATE `agent_profile_pricing` 
SET `user_type` = 'voucher',
    `username_format` = 'mixed',
    `username_length` = 8,
    `password_format` = 'mixed',
    `password_length` = 8
WHERE `user_type` IS NULL;
