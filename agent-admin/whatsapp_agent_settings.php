<?php
/*
 * Admin Panel - WhatsApp Agent Settings
 * Manage admin numbers and message templates
 */

include_once('./include/db_config.php');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $db = getDBConnection();
        $db->beginTransaction();
        
        // Admin Numbers
        $adminNumbers = $_POST['admin_numbers'] ?? '';
        if (!empty($adminNumbers)) {
            $numbers = explode(',', $adminNumbers);
            $numbers = array_map('trim', $numbers);
            $numbers = array_filter($numbers);
            
            // Validate format
            $validNumbers = [];
            foreach ($numbers as $number) {
                if (preg_match('/^62\d{9,13}$/', $number)) {
                    $validNumbers[] = $number;
                }
            }
            
            $numbersString = implode(',', $validNumbers);
            
            $stmt = $db->prepare("
                INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by) 
                VALUES ('admin_whatsapp_numbers', ?, 'string', 'Admin WhatsApp numbers', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$numbersString, $numbersString]);
        }
        
        // Message Settings
        $settings = [
            'wa_message_header' => $_POST['message_header'] ?? '',
            'wa_message_footer' => $_POST['message_footer'] ?? '',
            'wa_business_name' => $_POST['business_name'] ?? '',
            'wa_business_phone' => $_POST['business_phone'] ?? '',
            'wa_business_address' => $_POST['business_address'] ?? '',
            'wa_enable_emoji' => isset($_POST['enable_emoji']) ? '1' : '0',
            'wa_enable_formatting' => isset($_POST['enable_formatting']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by) 
                VALUES (?, ?, 'string', 'WhatsApp message setting', 'admin')
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = 'admin'
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $db->commit();
        $success = 'Pengaturan berhasil disimpan!';
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

// Load current settings
$db = getDBConnection();
$stmt = $db->query("SELECT setting_key, setting_value FROM agent_settings WHERE setting_key LIKE 'admin_whatsapp_numbers' OR setting_key LIKE 'wa_%'");
$currentSettings = [];
while ($row = $stmt->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'admin_whatsapp_numbers' => '',
    'wa_message_header' => '╔═══════════════════╗
║  🎫 VOUCHER WIFI  ║
╚═══════════════════╝',
    'wa_message_footer' => '
━━━━━━━━━━━━━━━━━━━━
📞 Customer Service
WA: {business_phone}
📍 {business_address}

Terima kasih! 🙏',
    'wa_business_name' => 'WiFi Hotspot',
    'wa_business_phone' => '08123456789',
    'wa_business_address' => 'Jl. Contoh No. 123',
    'wa_enable_emoji' => '1',
    'wa_enable_formatting' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Ensure card headers and labels are visible */
.card-header h3 {
    color: inherit !important;
    font-weight: 600 !important;
    margin: 0 !important;
}

.card-body {
    color: inherit !important;
}

.form-group label {
    color: inherit !important;
    font-weight: 600 !important;
    margin-bottom: 8px !important;
    display: block !important;
}

.help-text {
    font-size: 12px;
    color: #999 !important;
    margin-top: 5px;
    line-height: 1.5;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.checkbox-group label {
    margin: 0;
    font-weight: normal !important;
    cursor: pointer;
    color: inherit !important;
}

textarea.form-control {
    font-family: monospace;
    resize: vertical;
    min-height: 120px;
    color: #333 !important;
    background: #fff !important;
}

textarea.form-control:focus {
    color: #333 !important;
    background: #fff !important;
}

input.form-control {
    color: #333 !important;
    background: #fff !important;
}

input.form-control:focus {
    color: #333 !important;
    background: #fff !important;
}

.preview-box {
    background: #f8f9fa !important;
    border: 2px solid #25D366;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.preview-box h4 {
    margin-top: 0;
    color: #25D366 !important;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600 !important;
}

.preview-content {
    background: #fff !important;
    color: #333 !important;
    padding: 15px;
    border-radius: 8px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.6;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.admin-numbers-list {
    background: #f8f9fa !important;
    padding: 15px;
    border-radius: 5px;
    margin-top: 10px;
    border: 1px solid #ddd;
}

.admin-numbers-list strong {
    color: #333 !important;
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
}

.admin-numbers-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.admin-numbers-list li {
    padding: 8px 12px;
    background: #fff !important;
    color: #333 !important;
    margin: 5px 0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #ddd;
}

.admin-numbers-list li span {
    color: #333 !important;
    font-weight: 500;
}

.admin-numbers-list li i {
    color: #25D366 !important;
}

.variable-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.variable-tag {
    background: #e3f2fd !important;
    color: #1976d2 !important;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-family: monospace;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid #1976d2;
}

.variable-tag:hover {
    background: #1976d2 !important;
    color: #fff !important;
}

@media (max-width: 768px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-whatsapp"></i> Pengaturan WhatsApp Agent</h3>
</div>
<div class="card-body">

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="settings-grid">
            <!-- Admin Numbers -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-shield"></i> Nomor Admin</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>Nomor WhatsApp Admin</label>
                    <textarea name="admin_numbers" class="form-control" rows="5" placeholder="628123456789,628987654321"><?= htmlspecialchars($currentSettings['admin_whatsapp_numbers']); ?></textarea>
                    <div class="help-text">
                        • Pisahkan dengan koma (,) untuk multiple nomor<br>
                        • Format: 628xxx (tanpa + atau spasi)<br>
                        • Admin dapat generate voucher tanpa pemotongan saldo
                    </div>
                </div>

                <?php if (!empty($currentSettings['admin_whatsapp_numbers'])): ?>
                <div class="admin-numbers-list">
                    <strong style="color: #333 !important;">Nomor Terdaftar:</strong>
                    <ul>
                        <?php foreach (explode(',', $currentSettings['admin_whatsapp_numbers']) as $number): ?>
                        <li>
                            <i class="fa fa-check-circle"></i>
                            <span style="color: #333 !important;"><?= htmlspecialchars(trim($number)); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Business Info -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-building"></i> Informasi Bisnis</h3>
                </div>
                <div class="card-body">
                <div class="form-group">
                    <label>Nama Bisnis</label>
                    <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_name']); ?>" placeholder="WiFi Hotspot">
                </div>

                <div class="form-group">
                    <label>Nomor WhatsApp Bisnis</label>
                    <input type="text" name="business_phone" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_phone']); ?>" placeholder="08123456789">
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <input type="text" name="business_address" class="form-control" value="<?= htmlspecialchars($currentSettings['wa_business_address']); ?>" placeholder="Jl. Contoh No. 123">
                </div>
                </div>
            </div>
        </div>

        <!-- Message Templates -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-comment"></i> Template Pesan</h3>
            </div>
            <div class="card-body">
            <div class="settings-grid">
                <div class="form-group">
                    <label>Header Pesan</label>
                    <textarea name="message_header" class="form-control" rows="6"><?= htmlspecialchars($currentSettings['wa_message_header']); ?></textarea>
                    <div class="help-text">Header yang ditampilkan di awal setiap pesan</div>
                </div>

                <div class="form-group">
                    <label>Footer Pesan</label>
                    <textarea name="message_footer" class="form-control" rows="6"><?= htmlspecialchars($currentSettings['wa_message_footer']); ?></textarea>
                    <div class="help-text">Footer yang ditampilkan di akhir setiap pesan</div>
                    
                    <div class="variable-tags">
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_name}')" title="Klik untuk insert">{business_name}</span>
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_phone}')" title="Klik untuk insert">{business_phone}</span>
                        <span class="variable-tag" onclick="insertVariable('message_footer', '{business_address}')" title="Klik untuk insert">{business_address}</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Opsi Pesan</label>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_emoji" id="enable_emoji" value="1" <?= $currentSettings['wa_enable_emoji'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_emoji">Gunakan Emoji</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_formatting" id="enable_formatting" value="1" <?= $currentSettings['wa_enable_formatting'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_formatting">Gunakan Format Bold/Italic</label>
                </div>
            </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="card">
            <div class="card-body">
        <div class="preview-box">
            <h4><i class="fa fa-eye"></i> Preview Pesan</h4>
            <div class="preview-content" id="messagePreview">
                <!-- Preview will be generated here -->
            </div>
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
function insertVariable(fieldName, variable) {
    const field = document.querySelector(`textarea[name="${fieldName}"]`);
    const cursorPos = field.selectionStart;
    const textBefore = field.value.substring(0, cursorPos);
    const textAfter = field.value.substring(cursorPos);
    
    field.value = textBefore + variable + textAfter;
    field.focus();
    field.selectionStart = field.selectionEnd = cursorPos + variable.length;
    
    updatePreview();
}

function updatePreview() {
    const header = document.querySelector('textarea[name="message_header"]').value;
    const footer = document.querySelector('textarea[name="message_footer"]').value;
    const businessName = document.querySelector('input[name="business_name"]').value;
    const businessPhone = document.querySelector('input[name="business_phone"]').value;
    const businessAddress = document.querySelector('input[name="business_address"]').value;
    
    // Replace variables
    let footerProcessed = footer
        .replace(/{business_name}/g, businessName)
        .replace(/{business_phone}/g, businessPhone)
        .replace(/{business_address}/g, businessAddress);
    
    // Sample voucher message
    const sampleMessage = `${header}

✅ VOUCHER BERHASIL DI-GENERATE

Profile: *3JAM*
Jumlah: 5 voucher
Total: Rp 15,000

━━━━━━━━━━━━━━━━━━━━

*Voucher #1*
Username: \`AG12AB34CD\`
Password: \`XY56ZW\`

*Voucher #2*
Username: \`AG98EF76GH\`
Password: \`MN12OP\`

...

${footerProcessed}`;
    
    document.getElementById('messagePreview').textContent = sampleMessage;
}

// Update preview on input
document.querySelectorAll('textarea, input[type="text"]').forEach(element => {
    element.addEventListener('input', updatePreview);
});

// Initial preview
document.addEventListener('DOMContentLoaded', updatePreview);
</script>
