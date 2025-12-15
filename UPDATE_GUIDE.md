# Auto-Update System

## 📥 Fitur Auto-Update dari GitHub

Sistem MikhMon Agent sekarang dilengkapi dengan fitur **Auto-Update** yang memudahkan Anda untuk mendapatkan versi terbaru langsung dari GitHub repository.

---

## 🚀 Cara Menggunakan

### **Metode 1: Via Browser (Recommended)**

1. **Login sebagai Admin** di sistem MikhMon
2. **Akses URL**: `http://your-domain.com/update.php`
3. **Klik tombol "Mulai Update"**
4. **Tunggu proses selesai** (otomatis download, backup, dan update)
5. **Selesai!** Sistem akan redirect ke dashboard

### **Metode 2: Via Command Line** *(Coming Soon)*

```bash
php update.php --auto
```

---

## 🛡️ File yang Dilindungi

File berikut **TIDAK AKAN DI-UPDATE** (konfigurasi Anda tetap aman):

- ✅ `include/db_config.php` - Konfigurasi database
- ✅ `update.php` - Script update itu sendiri
- ✅ `.git/` - Git folder (jika ada)
- ✅ `backups/` - Folder backup
- ✅ `logs/` - Folder log
- ✅ `cache/` - Folder cache

---

## 📦 Apa yang Dilakukan Saat Update

### **Proses Otomatis:**

1. **📋 Backup Otomatis**
   - Semua file sistem di-backup ke folder `backups/`
   - Format: `backup_YYYY-MM-DD_HH-MM-SS.zip`
   
2. **📥 Download dari GitHub**
   - Download ZIP dari repository: `alijayanet/mikhmon-agent`
   - Branch: `main`
   
3. **📦 Extract Files**
   - Extract file ZIP ke temporary folder
   
4. **🔄 Update Files**
   - Copy semua file baru
   - **Skip** file yang dilindungi
   
5. **🧹 Cleanup**
   - Hapus temporary files
   - Hapus ZIP download

---

## ⚠️ Hal yang Perlu Diperhatikan

### **Sebelum Update:**

- ✅ Pastikan tidak ada user yang sedang menggunakan sistem
- ✅ Pastikan koneksi internet stabil
- ✅ Pastikan disk space cukup untuk backup

### **Jika Update Gagal:**

1. **Backup tersimpan di** `backups/backup_YYYY-MM-DD_HH-MM-SS.zip`
2. **Restore manual:**
   - Extract file backup
   - Copy ke root folder
   - Overwrite semua file
3. **Coba update lagi** atau hubungi support

---

## 🔧 Konfigurasi

Edit file `update.php` untuk mengubah:

```php
// Repository GitHub
define('GITHUB_REPO', 'alijayanet/mikhmon-agent');

// Branch yang digunakan
define('GITHUB_BRANCH', 'main'); // atau 'master'

// File yang dikecualikan
$excludedFiles = [
    'include/db_config.php',
    // tambahkan file lain jika perlu
];
```

---

## 📊 Monitoring Update

### **Log Update:**

Semua proses update ditampilkan real-time di browser:

```
🚀 Starting update process...
💾 Creating backup...
✅ Backup created: backup_2025-12-15_13-25-00.zip (1234 files)
📥 Downloading from GitHub...
✅ Download complete
📦 Extracting files...
✅ Files extracted
🔄 Updating files...
⏭️ Skipped: include/db_config.php (protected)
✅ Update complete: 1230 files updated, 4 files skipped
🧹 Cleaning up...
✅ Cleanup complete
✅ UPDATE COMPLETED SUCCESSFULLY!
```

---

## 🆘 Troubleshooting

### **Error: "Failed to download update"**

**Penyebab:**
- Koneksi internet bermasalah
- GitHub repository tidak accessible
- Firewall memblokir

**Solusi:**
- Cek koneksi internet
- Coba lagi beberapa saat
- Pastikan firewall allow akses GitHub

### **Error: "Failed to create backup ZIP"**

**Penyebab:**
- Disk space penuh
- Permission folder tidak cukup

**Solusi:**
- Bersihkan disk space
- Set permission folder `backups/` ke 755

### **Error: "Permission denied"**

**Penyebab:**
- Permission file/folder tidak cukup

**Solusi:**
```bash
chmod 755 -R /path/to/mikhmon-15
chown www-data:www-data -R /path/to/mikhmon-15
```

---

## 📱 Akses Cepat

### **Tambahkan Link di Menu Admin:**

Edit file sidebar/menu admin Anda:

```php
<a href="update.php">
    <i class="fa fa-cloud-download"></i> System Update
</a>
```

### **Atau buat shortcut:**

```html
<a href="/update.php" class="btn btn-primary">
    <i class="fa fa-refresh"></i> Check for Updates
</a>
```

---

## 📝 Change Log

### **Version 1.0.0** (2025-12-15)

- ✅ Auto-download dari GitHub
- ✅ Backup otomatis sebelum update
- ✅ Proteksi file konfigurasi
- ✅ Real-time update progress
- ✅ Rollback support via backup

---

## 📞 Support

Jika mengalami masalah saat update:

- **GitHub Issues**: https://github.com/alijayanet/mikhmon-agent/issues
- **Email Support**: (masukkan email support)
- **Telegram**: (masukkan channel/group)

---

## ⚖️ License

Same as MikhMon Agent main license.

---

**Developed with ❤️ by MikhMon Team**
