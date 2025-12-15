<?php
/**
 * Auto-Update System from GitHub
 * Repository: https://github.com/alijayanet/mikhmon-agent
 * 
 * PROTECTED FILES (will not be updated):
 * - include/db_config.php
 */

session_start();

// Only allow admin access
if (!isset($_SESSION['mikhmon_session']) || $_SESSION['mikhmon_session'] !== 'YWRtaW').split('').reverse().join('')) {
    die('Access denied. Admin login required.');
}

define('GITHUB_REPO', 'alijayanet/mikhmon-agent');
define('GITHUB_BRANCH', 'main'); // atau 'master' sesuai branch default
define('BASE_PATH', __DIR__);
define('BACKUP_PATH', BASE_PATH . '/backups');

// Files to EXCLUDE from update (user configuration)
$excludedFiles = [
    'include/db_config.php',
    'update.php',
    '.git',
    '.gitignore'
];

// Directories to exclude
$excludedDirs = [
    'backups',
    'logs',
    'cache',
    '.git'
];

/**
 * Download latest version from GitHub
 */
function downloadLatestVersion() {
    $zipUrl = 'https://github.com/' . GITHUB_REPO . '/archive/refs/heads/' . GITHUB_BRANCH . '.zip';
    $zipFile = BASE_PATH . '/update_temp.zip';
    
    echo "<div class='log-item'>📥 Downloading from GitHub...</div>";
    echo "<div class='log-detail'>URL: $zipUrl</div>";
    
    // Download using cURL
    $ch = curl_init($zipUrl);
    $fp = fopen($zipFile, 'w');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For XAMPP local dev
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    fclose($fp);
    
    if (!$result || $httpCode !== 200) {
        unlink($zipFile);
        throw new Exception("Failed to download update. HTTP Code: $httpCode");
    }
    
    echo "<div class='log-item success'>✅ Download complete</div>";
    return $zipFile;
}

/**
 * Extract ZIP file
 */
function extractZip($zipFile) {
    $extractPath = BASE_PATH . '/update_temp';
    
    echo "<div class='log-item'>📦 Extracting files...</div>";
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new Exception('Failed to open ZIP file');
    }
    
    $zip->extractTo($extractPath);
    $zip->close();
    
    // Find the extracted folder (usually repo-name-branch)
    $dirs = glob($extractPath . '/*', GLOB_ONLYDIR);
    if (empty($dirs)) {
        throw new Exception('No directory found in extracted ZIP');
    }
    
    echo "<div class='log-item success'>✅ Files extracted</div>";
    return $dirs[0]; // Return first directory
}

/**
 * Create backup of current installation
 */
function createBackup() {
    echo "<div class='log-item'>💾 Creating backup...</div>";
    
    if (!file_exists(BACKUP_PATH)) {
        mkdir(BACKUP_PATH, 0755, true);
    }
    
    $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
    $backupFile = BACKUP_PATH . '/' . $backupName;
    
    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE) !== true) {
        throw new Exception('Failed to create backup ZIP');
    }
    
    // Add all files to backup (excluding backups folder itself)
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    $count = 0;
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(BASE_PATH) + 1);
            
            // Skip backups folder
            if (strpos($relativePath, 'backups' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }
            
            $zip->addFile($filePath, $relativePath);
            $count++;
        }
    }
    
    $zip->close();
    
    echo "<div class='log-item success'>✅ Backup created: $backupName ($count files)</div>";
    return $backupFile;
}

/**
 * Copy files from extracted folder to base, excluding protected files
 */
function updateFiles($sourcePath) {
    global $excludedFiles, $excludedDirs;
    
    echo "<div class='log-item'>🔄 Updating files...</div>";
    
    $updated = 0;
    $skipped = 0;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        $sourceFile = $file->getRealPath();
        $relativePath = substr($sourceFile, strlen($sourcePath) + 1);
        
        if ($file->isDir()) {
            // Check if directory is excluded
            $dirName = basename($relativePath);
            if (in_array($dirName, $excludedDirs)) {
                continue;
            }
            
            // Create directory if not exists
            $targetDir = BASE_PATH . '/' . $relativePath;
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        } else {
            // Check if file is excluded
            if (in_array($relativePath, $excludedFiles)) {
                echo "<div class='log-detail'>⏭️ Skipped: $relativePath (protected)</div>";
                $skipped++;
                continue;
            }
            
            // Copy file
            $targetFile = BASE_PATH . '/' . $relativePath;
            $targetDir = dirname($targetFile);
            
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            if (copy($sourceFile, $targetFile)) {
                $updated++;
            }
        }
    }
    
    echo "<div class='log-item success'>✅ Update complete: $updated files updated, $skipped files skipped</div>";
}

/**
 * Cleanup temporary files
 */
function cleanup() {
    echo "<div class='log-item'>🧹 Cleaning up...</div>";
    
    // Remove ZIP file
    if (file_exists(BASE_PATH . '/update_temp.zip')) {
        unlink(BASE_PATH . '/update_temp.zip');
    }
    
    // Remove extracted folder
    $tempPath = BASE_PATH . '/update_temp';
    if (file_exists($tempPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($tempPath);
    }
    
    echo "<div class='log-item success'>✅ Cleanup complete</div>";
}

// Process update
$step = $_GET['step'] ?? 'start';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Update - MikhMon Agent</title>
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .update-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .warning-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .warning-box ul {
            margin-left: 20px;
            color: #856404;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .log-container {
            background: #1e1e1e;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
        }
        
        .log-item {
            color: #00ff00;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .log-detail {
            color: #888;
            margin-left: 20px;
            font-size: 12px;
        }
        
        .log-item.success {
            color: #00ff00;
        }
        
        .log-item.error {
            color: #ff4444;
        }
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="update-container">
        <div class="header">
            <h1><i class="fa fa-cloud-download"></i> System Update</h1>
            <p>MikhMon Agent Auto-Update from GitHub</p>
        </div>
        
        <div class="content">
            <?php if ($step === 'start'): ?>
                <div class="warning-box">
                    <h3><i class="fa fa-exclamation-triangle"></i> Peringatan</h3>
                    <ul>
                        <li>Backup otomatis akan dibuat sebelum update</li>
                        <li>Proses update akan menimpa semua file sistem</li>
                        <li>Pastikan tidak ada yang menggunakan sistem saat update</li>
                    </ul>
                </div>
                
                <div class="info-box">
                    <h3><i class="fa fa-shield"></i> File yang Dilindungi</h3>
                    <p>File berikut TIDAK akan di-update (konfigurasi Anda tetap aman):</p>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach ($excludedFiles as $file): ?>
                            <li><code><?= htmlspecialchars($file); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <p style="text-align: center; color: #666; margin-bottom: 20px;">
                    <strong>Repository:</strong> <?= GITHUB_REPO; ?><br>
                    <strong>Branch:</strong> <?= GITHUB_BRANCH; ?>
                </p>
                
                <div class="button-group">
                    <a href="?step=update" class="btn btn-primary">
                        <i class="fa fa-download"></i> Mulai Update
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa fa-times"></i> Batal
                    </a>
                </div>
                
            <?php elseif ($step === 'update'): ?>
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Proses update sedang berjalan... Mohon tunggu.</p>
                </div>
                
                <div class="log-container">
                    <?php
                    try {
                        echo "<div class='log-item'>🚀 Starting update process...</div>";
                        
                        // Step 1: Create backup
                        $backupFile = createBackup();
                        
                        // Step 2: Download latest version
                        $zipFile = downloadLatestVersion();
                        
                        // Step 3: Extract
                        $extractedPath = extractZip($zipFile);
                        
                        // Step 4: Update files
                        updateFiles($extractedPath);
                        
                        // Step 5: Cleanup
                        cleanup();
                        
                        echo "<div class='log-item success'>✅ UPDATE COMPLETED SUCCESSFULLY!</div>";
                        echo "<script>setTimeout(() => window.location.href='?step=success', 2000);</script>";
                        
                    } catch (Exception $e) {
                        echo "<div class='log-item error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
                        echo "<div class='log-item'>💡 Backup tersimpan di folder backups/</div>";
                        echo "<script>setTimeout(() => window.location.href='?step=error&msg=" . urlencode($e->getMessage()) . "', 3000);</script>";
                    }
                    ?>
                </div>
                
            <?php elseif ($step === 'success'): ?>
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fa fa-check-circle"></i>
                    </div>
                    <h2>Update Berhasil!</h2>
                    <p style="margin: 20px 0; color: #666;">
                        Sistem telah berhasil di-update ke versi terbaru dari GitHub.<br>
                        Konfigurasi database Anda tetap aman.
                    </p>
                    <div class="button-group">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa fa-home"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
                
            <?php elseif ($step === 'error'): ?>
                <div class="warning-box">
                    <h3><i class="fa fa-times-circle"></i> Update Gagal</h3>
                    <p><strong>Error:</strong> <?= htmlspecialchars($_GET['msg'] ?? 'Unknown error'); ?></p>
                    <p style="margin-top: 15px;">
                        Backup sistem Anda tersimpan di folder <code>backups/</code><br>
                        Silakan restore manual jika diperlukan atau coba update lagi.
                    </p>
                </div>
                <div class="button-group">
                    <a href="?step=start" class="btn btn-primary">
                        <i class="fa fa-refresh"></i> Coba Lagi
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa fa-home"></i> Kembali
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
