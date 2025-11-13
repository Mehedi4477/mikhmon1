<?php
// Skrip untuk menjalankan migrasi perubahan komisi

try {
    // Include konfigurasi database
    require_once('../include/db_config.php');
    
    // Dapatkan koneksi database
    $db = getDBConnection();
    
    if (!$db) {
        throw new Exception('Koneksi database gagal');
    }
    
    // Baca file migrasi
    $migrationFile = __DIR__ . '/migrate_commission_to_amount.sql';
    $migrationSql = file_get_contents($migrationFile);
    
    if (!$migrationSql) {
        throw new Exception('Gagal membaca file migrasi');
    }
    
    // Eksekusi perintah SQL
    $db->exec($migrationSql);
    
    echo "Migrasi berhasil dijalankan!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
