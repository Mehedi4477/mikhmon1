# Update: Format Command Digiflazz Lebih Praktis

## 🎉 Fitur Baru: Langsung Ketik SKU!

Sekarang user **tidak perlu** mengetik kata "PULSA" lagi. Cukup ketik **SKU + Nomor** langsung!

---

## 📱 Format Command Baru

### ✅ Format Praktis (BARU - Tanpa kata PULSA):
```
as10 081234567890
xl5 087828060222
telkom_50k 081234567890
gopay_10k 081234567890
ff_50k 081234567890
dana_10k 081234567890
ml_50k 081234567890
```

### ✅ Format Lengkap (LAMA - Masih bisa dipakai):
```
PULSA as10 081234567890
PULSA xl5 087828060222
PULSA telkom_50k 081234567890
```

**Kedua format tetap berfungsi!** User bebas pilih mana yang lebih nyaman.

---

## 🔧 Cara Kerja Teknis

### Alur Deteksi Command:

```
User mengirim: "as10 081234567890"
    ↓
1. Cek apakah ada command khusus (VOUCHER, HELP, dll) → TIDAK
    ↓
2. Split message menjadi 2 bagian: ["as10", "081234567890"]
    ↓
3. Validasi format:
   - Part 1 (as10): Apakah alphanumeric 2-20 karakter? → YA
   - Part 2 (081234567890): Apakah nomor telepon valid? → YA
    ↓
4. Cari di database: SELECT * FROM digiflazz_products WHERE buyer_sku_code = 'as10'
    ↓
5. Produk ditemukan? → YA
    ↓
6. Proses transaksi Digiflazz
    ↓
7. Kirim notifikasi hasil
```

---

## 💡 Keuntungan Format Baru

### 1. **Lebih Cepat**
```
Sebelum: PULSA as10 081234567890 (26 karakter)
Sesudah: as10 081234567890 (18 karakter)
Hemat: 8 karakter (30% lebih pendek!)
```

### 2. **Lebih Mudah Diingat**
User cukup ingat SKU + Nomor, tanpa perlu ingat kata "PULSA"

### 3. **Lebih Natural**
Seperti chat biasa, tidak terasa seperti command

### 4. **Backward Compatible**
Format lama (dengan PULSA) tetap berfungsi

---

## 🛡️ Validasi Keamanan

Sistem akan memvalidasi:

### 1. **Format SKU**
```php
preg_match('/^[a-z0-9_-]{2,20}$/i', $sku)
```
- Hanya huruf, angka, underscore, dan dash
- Panjang 2-20 karakter
- Case insensitive

### 2. **Format Nomor Telepon**
```php
preg_match('/^[0-9]{10,15}$/', $cleanNumber)
```
- Hanya angka
- Panjang 10-15 digit

### 3. **Produk Harus Ada di Database**
```sql
SELECT * FROM digiflazz_products 
WHERE LOWER(buyer_sku_code) = LOWER('as10') 
AND status = 'active'
```

---

## 📊 Contoh Penggunaan

### Pulsa:
```
as10 081234567890          → Axis 10K
xl5 087828060222           → XL 5K
telkom_50k 081234567890    → Telkomsel 50K
isat_25k 081234567890      → Indosat 25K
three_10k 081234567890     → Tri 10K
smart_5k 081234567890      → Smartfren 5K
```

### Paket Data:
```
axis_data_1gb 081234567890
xl_data_2gb 087828060222
telkom_data_5gb 081234567890
```

### E-Money:
```
gopay_10k 081234567890
ovo_20k 087828060222
dana_50k 081234567890
shopeepay_10k 081234567890
linkaja_25k 081234567890
```

### Voucher Game:
```
ff_50k 081234567890        → Free Fire 50K
ml_100k 087828060222       → Mobile Legends 100K
pubg_60k 081234567890      → PUBG 60K
codm_50k 081234567890      → Call of Duty Mobile 50K
```

### Token Listrik:
```
pln_20k 12345678901        → Token PLN 20K
pln_50k 12345678901        → Token PLN 50K
pln_100k 12345678901       → Token PLN 100K
```

---

## ⚠️ Catatan Penting

### 1. **SKU Harus Valid**
Jika SKU tidak ditemukan di database, sistem akan mengabaikan command (tidak ada response).

### 2. **Nomor Harus Valid**
Nomor telepon harus 10-15 digit. Jika kurang atau lebih, akan ditolak.

### 3. **Tidak Bentrok dengan Command Lain**
Sistem akan cek command khusus (VOUCHER, HELP, dll) terlebih dahulu sebelum cek SKU.

### 4. **Case Insensitive**
```
as10 081234567890  → OK
AS10 081234567890  → OK
As10 081234567890  → OK
```

---

## 🔄 Prioritas Command

Urutan pengecekan command:

1. **Command Khusus** (VOUCHER, HELP, HARGA, dll)
2. **Command WiFi** (GANTI WIFI, GANTI SANDI)
3. **Command Billing** (TAGIHAN, BAYAR)
4. **Command Admin** (TAMBAH, EDIT, HAPUS, dll)
5. **Command PULSA** (PULSA as10 081234567890)
6. **Fallback SKU** (as10 081234567890) ← **BARU!**
7. **Invalid Command** (diabaikan)

---

## 📝 Update Menu HELP

Menu HELP sekarang menampilkan:

```
📱 *<SKU> <NOMOR>* atau *PULSA <SKU> <NOMOR>*
Beli pulsa/data/e-money/games via Digiflazz

Format Praktis (tanpa kata PULSA):
• as10 081234567890
• xl5 087828060222
• gopay_10k 081234567890
• ff_50k 081234567890

Format Lengkap (dengan kata PULSA):
• PULSA as10 081234567890
• PULSA xl5 087828060222
```

---

## 🧪 Testing

### Test Case 1: Format Baru (Tanpa PULSA)
```
Input: as10 081234567890
Expected: Transaksi berhasil
Actual: ✅ Berhasil
```

### Test Case 2: Format Lama (Dengan PULSA)
```
Input: PULSA as10 081234567890
Expected: Transaksi berhasil
Actual: ✅ Berhasil
```

### Test Case 3: SKU Tidak Valid
```
Input: xyz123 081234567890
Expected: Diabaikan (tidak ada response)
Actual: ✅ Diabaikan
```

### Test Case 4: Nomor Tidak Valid
```
Input: as10 123
Expected: Diabaikan (nomor terlalu pendek)
Actual: ✅ Diabaikan
```

### Test Case 5: Case Insensitive
```
Input: AS10 081234567890
Expected: Transaksi berhasil
Actual: ✅ Berhasil
```

---

## 📚 File yang Dimodifikasi

### 1. `api/whatsapp_webhook.php`
**Fungsi:** `processCommand()`

**Perubahan:**
- Ditambahkan fallback detection untuk SKU Digiflazz
- Validasi format SKU dan nomor telepon
- Query ke database untuk cek produk
- Jika valid, panggil `purchaseDigiflazz()`

**Lokasi:** Baris ~656-678

---

## 🎯 Kesimpulan

### ✅ Keuntungan:
1. User lebih cepat transaksi
2. Lebih mudah diingat
3. Lebih natural
4. Backward compatible

### ⚠️ Perhatian:
1. SKU harus valid (ada di database)
2. Nomor harus valid (10-15 digit)
3. Tidak bentrok dengan command lain

---

**Update:** 2025-12-14  
**Versi:** 2.0  
**Status:** ✅ Aktif dan Berfungsi
