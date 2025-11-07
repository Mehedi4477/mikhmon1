-- Insert Tripay Payment Methods for Sandbox Testing
-- Run this to populate payment_methods table

INSERT INTO `payment_methods` (`gateway_name`, `method_code`, `method_name`, `method_type`, `admin_fee_type`, `admin_fee_value`, `min_amount`, `max_amount`, `is_active`, `sort_order`) VALUES
-- Virtual Account
('tripay', 'MYBVA', 'Maybank Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 1),
('tripay', 'PERMATAVA', 'Permata Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 2),
('tripay', 'BNIVA', 'BNI Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 3),
('tripay', 'BRIVA', 'BRI Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 4),
('tripay', 'MANDIRIVA', 'Mandiri Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 5),
('tripay', 'BCAVA', 'BCA Virtual Account', 'bank_transfer', 'flat', 4000, 10000, 50000000, 1, 6),

-- E-Wallet
('tripay', 'SHOPEEPAY', 'ShopeePay', 'ewallet', 'percentage', 2.5, 1000, 20000000, 1, 10),
('tripay', 'OVO', 'OVO', 'ewallet', 'percentage', 2.5, 10000, 10000000, 1, 11),
('tripay', 'DANA', 'DANA', 'ewallet', 'percentage', 2.5, 1000, 20000000, 1, 12),
('tripay', 'LINKAJA', 'LinkAja', 'ewallet', 'percentage', 2.5, 1000, 10000000, 1, 13),
('tripay', 'GOPAY', 'GoPay', 'ewallet', 'percentage', 2.5, 1000, 20000000, 1, 14),

-- Convenience Store
('tripay', 'ALFAMART', 'Alfamart', 'retail', 'flat', 2500, 10000, 2500000, 1, 20),
('tripay', 'INDOMARET', 'Indomaret', 'retail', 'flat', 2500, 10000, 5000000, 1, 21),

-- QRIS
('tripay', 'QRIS', 'QRIS (Scan to Pay)', 'qris', 'percentage', 0.7, 1000, 10000000, 1, 30);
