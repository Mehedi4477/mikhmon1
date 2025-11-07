-- Public Voucher Sales System
-- Payment Gateway Integration (Tripay, Xendit, Midtrans)

-- Payment Gateway Configuration
CREATE TABLE IF NOT EXISTS payment_gateway_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) NOT NULL, -- 'tripay', 'xendit', 'midtrans'
    is_active TINYINT(1) DEFAULT 0,
    is_sandbox TINYINT(1) DEFAULT 1,
    api_key VARCHAR(255),
    api_secret VARCHAR(255),
    merchant_code VARCHAR(100),
    callback_token VARCHAR(255),
    config_json TEXT, -- Additional config as JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_gateway (gateway_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agent Profile Pricing (extends existing profiles)
CREATE TABLE IF NOT EXISTS agent_profile_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    profile_name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) NOT NULL, -- Nama yang ditampilkan ke customer
    description TEXT, -- Deskripsi profile (speed, quota, validity)
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2), -- Harga coret (optional)
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0, -- Highlight card
    icon VARCHAR(50) DEFAULT 'fa-wifi', -- FontAwesome icon
    color VARCHAR(20) DEFAULT 'blue', -- Card color
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_agent_profile (agent_id, profile_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public Sales Transactions
CREATE TABLE IF NOT EXISTS public_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) UNIQUE NOT NULL, -- Our internal ID
    payment_reference VARCHAR(100), -- Payment gateway reference
    agent_id INT NOT NULL,
    profile_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    
    -- Pricing
    profile_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    admin_fee DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    
    -- Payment Gateway
    gateway_name VARCHAR(50) NOT NULL,
    payment_method VARCHAR(50), -- 'QRIS', 'BRIVA', 'OVO', etc
    payment_channel VARCHAR(50), -- Specific channel
    
    -- Payment Details
    payment_url TEXT,
    qr_url TEXT,
    virtual_account VARCHAR(50),
    payment_instructions TEXT,
    expired_at DATETIME,
    paid_at DATETIME,
    
    -- Status
    status VARCHAR(20) DEFAULT 'pending', -- pending, paid, expired, failed, refunded
    
    -- Voucher
    voucher_code VARCHAR(50),
    voucher_password VARCHAR(50),
    voucher_generated_at DATETIME,
    voucher_sent_at DATETIME,
    
    -- Metadata
    ip_address VARCHAR(50),
    user_agent TEXT,
    callback_data TEXT, -- Raw callback JSON
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES agent_profile_pricing(id) ON DELETE CASCADE,
    
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_payment_reference (payment_reference),
    INDEX idx_status (status),
    INDEX idx_customer_phone (customer_phone),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment Methods Configuration
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) NOT NULL,
    method_code VARCHAR(50) NOT NULL, -- 'QRIS', 'BRIVA', 'OVO', etc
    method_name VARCHAR(100) NOT NULL,
    method_type VARCHAR(20) NOT NULL, -- 'ewallet', 'va', 'qris', 'retail'
    icon_url VARCHAR(255),
    admin_fee_type VARCHAR(20) DEFAULT 'flat', -- 'flat' or 'percent'
    admin_fee_value DECIMAL(10,2) DEFAULT 0,
    min_amount DECIMAL(10,2) DEFAULT 0,
    max_amount DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_gateway_method (gateway_name, method_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terms of Service / Privacy Policy
CREATE TABLE IF NOT EXISTS site_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(50) UNIQUE NOT NULL, -- 'tos', 'privacy', 'faq'
    page_title VARCHAR(200) NOT NULL,
    page_content TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default TOS page
INSERT INTO site_pages (page_slug, page_title, page_content) VALUES 
('tos', 'Syarat dan Ketentuan', '<h3>Syarat dan Ketentuan Pembelian Voucher</h3>
<p>Dengan melakukan pembelian voucher WiFi di situs ini, Anda menyetujui syarat dan ketentuan berikut:</p>

<h4>1. Pembelian Voucher</h4>
<ul>
<li>Voucher hanya dapat digunakan untuk akses internet WiFi sesuai dengan paket yang dipilih</li>
<li>Harga voucher sudah termasuk pajak yang berlaku</li>
<li>Pembayaran dilakukan melalui payment gateway yang tersedia</li>
<li>Voucher akan dikirim otomatis ke WhatsApp setelah pembayaran berhasil</li>
</ul>

<h4>2. Masa Berlaku</h4>
<ul>
<li>Voucher berlaku sesuai dengan durasi paket yang dipilih</li>
<li>Masa berlaku dimulai sejak voucher pertama kali digunakan</li>
<li>Voucher yang sudah expired tidak dapat digunakan kembali</li>
</ul>

<h4>3. Pengembalian Dana</h4>
<ul>
<li>Voucher yang sudah dibeli tidak dapat dikembalikan</li>
<li>Pengembalian dana hanya dilakukan jika terjadi kesalahan sistem</li>
<li>Proses pengembalian dana membutuhkan waktu 3-7 hari kerja</li>
</ul>

<h4>4. Penggunaan Voucher</h4>
<ul>
<li>Satu voucher hanya dapat digunakan untuk satu perangkat</li>
<li>Dilarang menyalahgunakan voucher untuk aktivitas ilegal</li>
<li>Kami berhak memblokir voucher yang terindikasi penyalahgunaan</li>
</ul>

<h4>5. Privasi</h4>
<ul>
<li>Data pribadi Anda akan dijaga kerahasiaannya</li>
<li>Data hanya digunakan untuk keperluan pengiriman voucher</li>
<li>Kami tidak akan membagikan data Anda kepada pihak ketiga</li>
</ul>

<p><strong>Hubungi Kami:</strong><br>
Jika ada pertanyaan, silakan hubungi customer service kami.</p>'),

('privacy', 'Kebijakan Privasi', '<h3>Kebijakan Privasi</h3>
<p>Kami menghormati privasi Anda dan berkomitmen untuk melindungi data pribadi Anda.</p>

<h4>Data yang Kami Kumpulkan</h4>
<ul>
<li>Nama lengkap</li>
<li>Nomor WhatsApp</li>
<li>Email (opsional)</li>
<li>Riwayat transaksi</li>
</ul>

<h4>Penggunaan Data</h4>
<p>Data Anda digunakan untuk:</p>
<ul>
<li>Memproses pembelian voucher</li>
<li>Mengirimkan voucher ke WhatsApp Anda</li>
<li>Memberikan dukungan pelanggan</li>
<li>Meningkatkan layanan kami</li>
</ul>

<h4>Keamanan Data</h4>
<p>Kami menggunakan enkripsi dan protokol keamanan standar industri untuk melindungi data Anda.</p>'),

('faq', 'FAQ - Pertanyaan Umum', '<h3>Pertanyaan yang Sering Diajukan</h3>

<h4>Bagaimana cara membeli voucher?</h4>
<p>Pilih paket yang diinginkan, isi nama dan nomor WhatsApp, pilih metode pembayaran, lalu selesaikan pembayaran.</p>

<h4>Berapa lama voucher dikirim?</h4>
<p>Voucher akan dikirim otomatis ke WhatsApp Anda dalam 1-5 menit setelah pembayaran berhasil.</p>

<h4>Bagaimana cara menggunakan voucher?</h4>
<p>Hubungkan ke WiFi, buka browser, masukkan kode voucher dan password yang dikirim ke WhatsApp Anda.</p>

<h4>Voucher tidak bisa digunakan?</h4>
<p>Pastikan Anda sudah terhubung ke WiFi dan kode voucher dimasukkan dengan benar. Jika masih bermasalah, hubungi customer service.</p>

<h4>Apakah bisa refund?</h4>
<p>Voucher yang sudah dibeli tidak dapat di-refund, kecuali terjadi kesalahan sistem.</p>')
ON DUPLICATE KEY UPDATE page_content = VALUES(page_content);
