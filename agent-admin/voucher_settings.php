<?php
/*
 * Admin Panel - Voucher Generation Settings
 * Konfigurasi format voucher untuk agent
 */

include_once('./include/db_config.php');

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

$error = '';
$success = '';

// Get current settings
$db = getDBConnection();
$stmt = $db->query("SELECT * FROM agent_settings WHERE setting_key LIKE 'voucher_%'");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values if not set
$defaults = [
    'voucher_username_password_same' => '0',
    'voucher_username_type' => 'alphanumeric',
    'voucher_username_length' => '8',
    'voucher_password_type' => 'alphanumeric',
    'voucher_password_length' => '6',
    'voucher_prefix_enabled' => '1',
    'voucher_prefix' => 'AG',
    'voucher_uppercase' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get payment settings
$stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'payment_%'");
$paymentSettings = [];
while ($row = $stmt->fetch()) {
    $paymentSettings[$row['setting_key']] = $row['setting_value'];
}

// Default payment values
$paymentDefaults = [
    'payment_bank_name' => 'BCA',
    'payment_account_number' => '1234567890',
    'payment_account_name' => 'Nama Pemilik',
    'payment_wa_confirm' => '08123456789'
];

foreach ($paymentDefaults as $key => $value) {
    if (!isset($paymentSettings[$key])) {
        $paymentSettings[$key] = $value;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $db->beginTransaction();
        
        $settingsToSave = [
            'voucher_username_password_same' => $_POST['username_password_same'] ?? '0',
            'voucher_username_type' => $_POST['username_type'] ?? 'alphanumeric',
            'voucher_username_length' => intval($_POST['username_length'] ?? 8),
            'voucher_password_type' => $_POST['password_type'] ?? 'alphanumeric',
            'voucher_password_length' => intval($_POST['password_length'] ?? 6),
            'voucher_prefix_enabled' => $_POST['prefix_enabled'] ?? '0',
            'voucher_prefix' => $_POST['prefix'] ?? 'AG',
            'voucher_uppercase' => $_POST['uppercase'] ?? '0'
        ];
        
        // Payment settings
        $paymentToSave = [
            'payment_bank_name' => $_POST['payment_bank_name'] ?? 'BCA',
            'payment_account_number' => $_POST['payment_account_number'] ?? '1234567890',
            'payment_account_name' => $_POST['payment_account_name'] ?? 'Nama Pemilik',
            'payment_wa_confirm' => $_POST['payment_wa_confirm'] ?? '08123456789'
        ];
        
        // Save voucher settings
        foreach ($settingsToSave as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (1, ?, ?, 'string', 'Voucher generation setting', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Save payment settings
        foreach ($paymentToSave as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO agent_settings (agent_id, setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (1, ?, ?, 'string', 'Payment information setting', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $db->commit();
        $success = 'Pengaturan berhasil disimpan!';
        
        // Reload settings
        $settings = $settingsToSave;
        $paymentSettings = $paymentToSave;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}
?>

<style>
/* Minimal custom styles - using MikhMon classes for most elements */
.form-group {
    margin-bottom: 20px;
}

.form-group .help-text {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.preview-box {
    background: #f8f9fa;
    border: 2px dashed #667eea;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    margin-top: 20px;
}

.preview-box h4 {
    margin-top: 0;
    color: #667eea;
}

.preview-voucher {
    background: white;
    padding: 15px;
    border-radius: 5px;
    display: inline-block;
    margin: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.preview-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.preview-value {
    font-family: monospace;
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-cog"></i> Pengaturan Format Voucher</h3>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>

    <form method="POST" id="settingsForm">
        <!-- Username & Password Configuration -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-key"></i> Konfigurasi Username & Password</h3>
            </div>
            <div class="card-body">
            
            <div class="form-group">
                <label>Username dan Password</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="username_password_same" value="1" id="same_yes" 
                               <?= $settings['voucher_username_password_same'] == '1' ? 'checked' : ''; ?>
                               onchange="togglePasswordSettings()">
                        <label for="same_yes" style="margin: 0; font-weight: normal;">Sama</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="username_password_same" value="0" id="same_no"
                               <?= $settings['voucher_username_password_same'] == '0' ? 'checked' : ''; ?>
                               onchange="togglePasswordSettings()">
                        <label for="same_no" style="margin: 0; font-weight: normal;">Berbeda</label>
                    </div>
                </div>
                <div class="help-text">Jika "Sama", password akan sama dengan username</div>
            </div>
            </div>
        </div>

        <!-- Username Settings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-user"></i> Pengaturan Username</h3>
            </div>
            <div class="card-body">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipe Karakter Username</label>
                    <select name="username_type" class="form-control" onchange="updatePreview()">
                        <option value="numeric" <?= $settings['voucher_username_type'] == 'numeric' ? 'selected' : ''; ?>>
                            Angka Saja (0-9)
                        </option>
                        <option value="alpha" <?= $settings['voucher_username_type'] == 'alpha' ? 'selected' : ''; ?>>
                            Huruf Saja (A-Z)
                        </option>
                        <option value="alphanumeric" <?= $settings['voucher_username_type'] == 'alphanumeric' ? 'selected' : ''; ?>>
                            Kombinasi Angka & Huruf
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Jumlah Digit Username</label>
                    <input type="number" name="username_length" class="form-control" 
                           min="4" max="20" value="<?= $settings['voucher_username_length']; ?>"
                           onchange="updatePreview()">
                    <div class="help-text">Minimal 4, maksimal 20 karakter</div>
                </div>
            </div>
        </div>

        <!-- Password Settings -->
        <div class="card" id="passwordSettings">
            <div class="card-header">
                <h3><i class="fa fa-lock"></i> Pengaturan Password</h3>
            </div>
            <div class="card-body">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipe Karakter Password</label>
                    <select name="password_type" class="form-control" onchange="updatePreview()">
                        <option value="numeric" <?= $settings['voucher_password_type'] == 'numeric' ? 'selected' : ''; ?>>
                            Angka Saja (0-9)
                        </option>
                        <option value="alpha" <?= $settings['voucher_password_type'] == 'alpha' ? 'selected' : ''; ?>>
                            Huruf Saja (A-Z)
                        </option>
                        <option value="alphanumeric" <?= $settings['voucher_password_type'] == 'alphanumeric' ? 'selected' : ''; ?>>
                            Kombinasi Angka & Huruf
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Jumlah Digit Password</label>
                    <input type="number" name="password_length" class="form-control" 
                           min="4" max="20" value="<?= $settings['voucher_password_length']; ?>"
                           onchange="updatePreview()">
                    <div class="help-text">Minimal 4, maksimal 20 karakter</div>
                </div>
            </div>
            </div>
        </div>

        <!-- Additional Settings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-wrench"></i> Pengaturan Tambahan</h3>
            </div>
            <div class="card-body">
            
            <div class="form-group">
                <div class="checkbox-option">
                    <input type="checkbox" name="prefix_enabled" value="1" id="prefix_enabled"
                           <?= $settings['voucher_prefix_enabled'] == '1' ? 'checked' : ''; ?>
                           onchange="togglePrefix(); updatePreview();">
                    <label for="prefix_enabled" style="margin: 0; font-weight: normal;">
                        Gunakan Prefix untuk Username
                    </label>
                </div>
            </div>
            
            <div class="form-group" id="prefixInput">
                <label>Prefix Username</label>
                <input type="text" name="prefix" class="form-control" 
                       value="<?= $settings['voucher_prefix']; ?>" 
                       maxlength="5" placeholder="AG"
                       onkeyup="updatePreview()">
                <div class="help-text">Contoh: AG akan menghasilkan AG12345678</div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-option">
                    <input type="checkbox" name="uppercase" value="1" id="uppercase"
                           <?= $settings['voucher_uppercase'] == '1' ? 'checked' : ''; ?>
                           onchange="updatePreview()">
                    <label for="uppercase" style="margin: 0; font-weight: normal;">
                        Huruf Kapital (Uppercase)
                    </label>
                </div>
                <div class="help-text">Jika dicentang, semua huruf akan menjadi kapital</div>
            </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="card">
            <div class="card-body">
        <div class="preview-box">
            <h4><i class="fa fa-eye"></i> Preview Voucher</h4>
            <p style="color: #666; font-size: 13px;">Contoh voucher yang akan di-generate</p>
            
            <div class="preview-voucher">
                <div class="preview-label">Username</div>
                <div class="preview-value" id="previewUsername">AG12AB34CD</div>
            </div>
            
            <div class="preview-voucher" id="previewPasswordBox">
                <div class="preview-label">Password</div>
                <div class="preview-value" id="previewPassword">XY56ZW</div>
            </div>
            </div>
        </div>

        <!-- Payment Settings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-credit-card"></i> Informasi Pembayaran</h3>
            </div>
            <div class="card-body">
                <p style="color: #666; margin-bottom: 20px; font-size: 13px;">Informasi pembayaran yang akan dikirim ke customer via WhatsApp</p>
            
            <div class="form-group">
                <label>Nama Bank</label>
                <input type="text" name="payment_bank_name" class="form-control" 
                       value="<?= htmlspecialchars($paymentSettings['payment_bank_name']); ?>" 
                       placeholder="BCA" required>
                <div class="help-text">Contoh: BCA, Mandiri, BRI, BNI</div>
            </div>
            
            <div class="form-group">
                <label>Nomor Rekening</label>
                <input type="text" name="payment_account_number" class="form-control" 
                       value="<?= htmlspecialchars($paymentSettings['payment_account_number']); ?>" 
                       placeholder="1234567890" required>
                <div class="help-text">Nomor rekening untuk transfer</div>
            </div>
            
            <div class="form-group">
                <label>Nama Pemilik Rekening</label>
                <input type="text" name="payment_account_name" class="form-control" 
                       value="<?= htmlspecialchars($paymentSettings['payment_account_name']); ?>" 
                       placeholder="Nama Pemilik" required>
                <div class="help-text">Nama pemilik rekening (a.n.)</div>
            </div>
            
            <div class="form-group">
                <label>Nomor WhatsApp Konfirmasi</label>
                <input type="text" name="payment_wa_confirm" class="form-control" 
                       value="<?= htmlspecialchars($paymentSettings['payment_wa_confirm']); ?>" 
                       placeholder="08123456789" required>
                <div class="help-text">Nomor WhatsApp untuk konfirmasi pembayaran</div>
            </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div style="margin-top: 30px; text-align: center;">
            <button type="submit" name="save_settings" class="btn btn-primary">
                <i class="fa fa-save"></i> Simpan Pengaturan
            </button>
            <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </div>
    </form>
</div>
</div>
</div>
</div>

<script>
function togglePasswordSettings() {
    const isSame = document.getElementById('same_yes').checked;
    const passwordSettings = document.getElementById('passwordSettings');
    const previewPasswordBox = document.getElementById('previewPasswordBox');
    
    if (isSame) {
        passwordSettings.style.display = 'none';
        previewPasswordBox.style.display = 'none';
    } else {
        passwordSettings.style.display = 'block';
        previewPasswordBox.style.display = 'inline-block';
    }
    
    updatePreview();
}

function togglePrefix() {
    const enabled = document.getElementById('prefix_enabled').checked;
    const prefixInput = document.getElementById('prefixInput');
    prefixInput.style.display = enabled ? 'block' : 'none';
}

function generateRandomString(type, length) {
    let chars = '';
    
    switch(type) {
        case 'numeric':
            chars = '0123456789';
            break;
        case 'alpha':
            chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'alphanumeric':
            chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
    }
    
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    return result;
}

function updatePreview() {
    const isSame = document.getElementById('same_yes').checked;
    const usernameType = document.querySelector('select[name="username_type"]').value;
    const usernameLength = parseInt(document.querySelector('input[name="username_length"]').value) || 8;
    const passwordType = document.querySelector('select[name="password_type"]').value;
    const passwordLength = parseInt(document.querySelector('input[name="password_length"]').value) || 6;
    const prefixEnabled = document.getElementById('prefix_enabled').checked;
    const prefix = document.querySelector('input[name="prefix"]').value || '';
    const uppercase = document.getElementById('uppercase').checked;
    
    // Generate username
    let username = generateRandomString(usernameType, usernameLength);
    if (prefixEnabled && prefix) {
        username = prefix + username;
    }
    if (!uppercase && usernameType !== 'numeric') {
        username = username.toLowerCase();
    }
    
    // Generate password
    let password = '';
    if (isSame) {
        password = username;
    } else {
        password = generateRandomString(passwordType, passwordLength);
        if (!uppercase && passwordType !== 'numeric') {
            password = password.toLowerCase();
        }
    }
    
    document.getElementById('previewUsername').textContent = username;
    document.getElementById('previewPassword').textContent = password;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePasswordSettings();
    togglePrefix();
    updatePreview();
});
</script>
