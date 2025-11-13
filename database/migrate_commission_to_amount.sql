-- Migrasi untuk mengganti commission_percent dengan commission_amount

-- 1. Tambah kolom commission_amount ke tabel agents
ALTER TABLE agents ADD COLUMN commission_amount DECIMAL(15,2) DEFAULT 0.00 AFTER level;

-- 2. Update nilai commission_amount berdasarkan commission_percent yang ada
UPDATE agents SET commission_amount = (SELECT AVG(sell_price) FROM agent_vouchers WHERE agent_id = agents.id) * (commission_percent / 100) 
WHERE commission_percent > 0;

-- 3. Hapus kolom commission_percent
ALTER TABLE agents DROP COLUMN commission_percent;

-- 4. Modifikasi trigger after_agent_voucher_insert
DELIMITER //
DROP TRIGGER IF EXISTS after_agent_voucher_insert //
CREATE TRIGGER after_agent_voucher_insert
AFTER INSERT ON agent_vouchers
FOR EACH ROW
BEGIN
    -- Calculate commission if enabled
    DECLARE v_commission_enabled BOOLEAN;
    DECLARE v_commission_amount DECIMAL(15,2);
    
    SELECT CAST(setting_value AS UNSIGNED) INTO v_commission_enabled
    FROM agent_settings WHERE setting_key = 'commission_enabled';
    
    IF v_commission_enabled THEN
        SELECT commission_amount INTO v_commission_amount
        FROM agents WHERE id = NEW.agent_id;
        
        IF v_commission_amount > 0 AND NEW.sell_price IS NOT NULL THEN
            INSERT INTO agent_commissions (
                agent_id, voucher_id, commission_amount,
                voucher_price
            ) VALUES (
                NEW.agent_id, NEW.id, v_commission_amount,
                NEW.sell_price
            );
        END IF;
    END IF;
END //
DELIMITER ;

-- 5. Update tabel agent_commissions (opsional)
ALTER TABLE agent_commissions DROP COLUMN commission_percent;

-- 6. Update pengaturan default
UPDATE agent_settings 
SET setting_key = 'default_commission_amount', 
    setting_value = '5000', 
    description = 'Default commission amount (nominal)'
WHERE setting_key = 'default_commission_percent';
