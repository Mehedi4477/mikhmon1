
-- Add telegram_chat_id to agents table if not exists
SET @dbname = DATABASE();
SET @tablename = "agents";
SET @columnname = "telegram_chat_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " VARCHAR(50) DEFAULT NULL AFTER phone")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add telegram_chat_id to billing_customers table if not exists
SET @tablename = "billing_customers";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " VARCHAR(50) DEFAULT NULL AFTER phone")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for faster lookup
CREATE INDEX IF NOT EXISTS idx_agent_telegram_chat_id ON agents(telegram_chat_id);
-- Note: MySQL < 5.7 doesn't support IF NOT EXISTS for INDEX, but we'll assume modern MySQL/MariaDB or ignore error
