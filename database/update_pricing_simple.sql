-- Simple update: Only add user_type column
-- Rollback previous complex schema and add simple one

-- Drop columns if they exist (from previous attempt)
ALTER TABLE `agent_profile_pricing` 
DROP COLUMN IF EXISTS `username_format`,
DROP COLUMN IF EXISTS `username_length`,
DROP COLUMN IF EXISTS `password_format`,
DROP COLUMN IF EXISTS `password_length`;

-- Add only user_type column
ALTER TABLE `agent_profile_pricing` 
ADD COLUMN IF NOT EXISTS `user_type` ENUM('voucher', 'member') DEFAULT 'voucher' AFTER `color`;

-- Update existing records
UPDATE `agent_profile_pricing` 
SET `user_type` = 'voucher'
WHERE `user_type` IS NULL;
