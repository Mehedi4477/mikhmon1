<?php
session_start();
error_reporting(0);

// Check if logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');

$agentId = $_SESSION['agent_id'];

// Get agent data
$agent = new Agent();
$agentData = $agent->getAgentById($agentId);

// Get ISP name from MikroTik config
include_once('../include/config.php');
$sessions = array_keys($data);
$session = null;
foreach ($sessions as $s) {
    if ($s != 'mikhmon') {
        $session = $s;
        break;
    }
}

$ispName = "WiFi Hotspot"; // Default
$ispDns = "";
if ($session && isset($data[$session])) {
    $hotspotname = explode('%', $data[$session][4])[1] ?? '';
    $dnsname = explode('^', $data[$session][5])[1] ?? '';
    if (!empty($hotspotname)) {
        $ispName = $hotspotname;
    }
    if (!empty($dnsname)) {
        $ispDns = $dnsname;
    }
}

// Get agent vouchers
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM agent_vouchers WHERE agent_id = :agent_id ORDER BY created_at DESC");
    $stmt->execute([':agent_id' => $agentId]);
    $vouchers = $stmt->fetchAll();
} catch (Exception $e) {
    $vouchers = [];
}

include_once('include_head.php');
include_once('include_nav.php');
?>

<style>
.voucher-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.voucher-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    background: white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.voucher-username {
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 5px;
    color: #333;
}

.voucher-password {
    font-family: monospace;
    background: #f8f9fa;
    padding: 8px;
    border-radius: 5px;
    margin: 10px 0;
    font-size: 16px;
}

.voucher-profile {
    color: #666;
    font-size: 14px;
    margin: 5px 0;
}

.voucher-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 10px;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-used {
    background: #dbeafe;
    color: #1e40af;
}

.status-expired {
    background: #fee2e2;
    color: #991b1b;
}

.voucher-date {
    font-size: 12px;
    color: #999;
    margin-top: 10px;
}

.voucher-actions {
    margin-top: 15px;
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-print-voucher {
    padding: 8px 15px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-print-voucher:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
}

.btn-print-voucher i {
    font-size: 14px;
}

@media (max-width: 768px) {
    .voucher-grid {
        grid-template-columns: 1fr;
    }
    
    .voucher-actions {
        flex-direction: column;
    }
    
    .btn-print-voucher {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .btn-print-voucher {
        font-size: 12px;
        padding: 6px 12px;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-ticket"></i> My Vouchers</h3>
        <div>Total Vouchers: <strong><?= count($vouchers); ?></strong></div>
    </div>
    <div class="card-body">
        <?php if (!empty($vouchers)): ?>
        <div class="voucher-grid">
            <?php foreach ($vouchers as $voucher): ?>
            <div class="voucher-card">
                <div class="voucher-username"><?= htmlspecialchars($voucher['username']); ?></div>
                <div class="voucher-password"><?= htmlspecialchars($voucher['password']); ?></div>
                <div class="voucher-profile"><?= htmlspecialchars($voucher['profile_name']); ?></div>
                <div class="voucher-profile">Price: Rp <?= number_format($voucher['sell_price'], 0, ',', '.'); ?></div>
                <div class="voucher-status status-<?= $voucher['status']; ?>">
                    <?= ucfirst($voucher['status']); ?>
                </div>
                <div class="voucher-date">
                    <?= date('d M Y H:i', strtotime($voucher['created_at'])); ?>
                </div>
                <div class="voucher-actions">
                    <button class="btn-print-voucher" onclick="printSingleVoucher('<?= htmlspecialchars($voucher['username']); ?>', '<?= htmlspecialchars($voucher['password']); ?>', '<?= htmlspecialchars($voucher['profile_name']); ?>')">
                        <i class="fa fa-print"></i> Print Normal
                    </button>
                    <button class="btn-print-voucher" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);" onclick="printSingleVoucherThermal('<?= htmlspecialchars($voucher['username']); ?>', '<?= htmlspecialchars($voucher['password']); ?>', '<?= htmlspecialchars($voucher['profile_name']); ?>')">
                        <i class="fa fa-print"></i> Thermal 58mm
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No vouchers found.
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<script>
// ISP and Agent info
const ispName = "<?= addslashes($ispName); ?>";
const ispDns = "<?= addslashes($ispDns); ?>";
const agentName = "<?= addslashes($agentData['name']); ?>";
const agentCode = "<?= addslashes($agentData['agent_code']); ?>";

function printSingleVoucher(username, password, profile) {
    const printWindow = window.open('', '_blank');
    
    // Generate QR Code URL
    const loginUrl = 'http://10.5.50.1/login?username=' + username + '&password=' + password;
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(loginUrl);
    
    const printContent = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Print Voucher - ${username}</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .voucher-print { 
                border: 2px dashed #333; 
                padding: 30px; 
                width: 350px;
                text-align: center;
                background: white;
            }
            .voucher-print h2 { 
                margin: 0 0 10px 0; 
                color: #333;
                font-size: 24px;
            }
            .profile-badge { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white; 
                padding: 8px 20px; 
                border-radius: 20px;
                display: inline-block;
                margin-bottom: 20px;
                font-weight: 600;
                font-size: 14px;
            }
            .qr-container {
                margin: 20px 0;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 10px;
            }
            .qr-container img {
                width: 200px;
                height: 200px;
                border: 3px solid #667eea;
                padding: 10px;
                border-radius: 10px;
                background: white;
            }
            .qr-label {
                font-size: 12px;
                color: #666;
                margin-top: 10px;
                font-style: italic;
            }
            .field { 
                margin: 15px 0;
                text-align: left;
            }
            .label { 
                font-weight: bold; 
                font-size: 13px; 
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
                display: block;
            }
            .value { 
                font-size: 20px; 
                font-family: 'Courier New', monospace; 
                background: #f0f0f0; 
                padding: 12px 15px; 
                border-radius: 6px;
                font-weight: bold;
                color: #333;
                word-break: break-all;
            }
            .footer {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px dashed #ccc;
                font-size: 11px;
                color: #999;
            }
            @media print {
                body { 
                    padding: 0;
                    display: block;
                }
                .voucher-print { 
                    page-break-after: always;
                    border: 2px dashed #333;
                }
            }
        </style>
    </head>
    <body>
        <div class="voucher-print">
            <h2>🎫 Voucher WiFi</h2>
            <div style="text-align: center; margin-bottom: 15px;">
                <div style="font-weight: bold; font-size: 16px; color: #667eea;">${ispName}</div>
                ${ispDns ? '<div style="font-size: 12px; color: #999; margin-top: 3px;">' + ispDns + '</div>' : ''}
            </div>
            <div class="profile-badge">${profile}</div>
            
            <div class="qr-container">
                <img src="${qrUrl}" alt="QR Code">
                <div class="qr-label">Scan QR Code untuk login otomatis</div>
            </div>
            
            <div class="field">
                <div class="label">Username</div>
                <div class="value">${username}</div>
            </div>
            
            <div class="field">
                <div class="label">Password</div>
                <div class="value">${password}</div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ccc;">
                <div style="font-size: 11px; color: #999;">Agent: ${agentName} (${agentCode})</div>
            </div>
            
            <div class="footer">
                Terima kasih telah menggunakan layanan kami
            </div>
        </div>
        
        <script>
            // Auto print when page loads
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        <\/script>
    </body>
    </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}

function printSingleVoucherThermal(username, password, profile) {
    const printWindow = window.open('', '_blank');
    
    // Generate QR Code URL
    const loginUrl = 'http://10.5.50.1/login?username=' + username + '&password=' + password;
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(loginUrl);
    
    const printContent = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Print Voucher Thermal - ${username}</title>
        <style>
            @page {
                size: 58mm auto;
                margin: 0;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body { 
                font-family: 'Courier New', monospace;
                width: 58mm;
                padding: 5mm;
                margin: 0 auto;
                font-size: 10pt;
                line-height: 1.3;
            }
            
            .voucher-thermal { 
                width: 100%;
                text-align: center;
            }
            
            .header {
                text-align: center;
                margin-bottom: 3mm;
                padding-bottom: 2mm;
                border-bottom: 1px dashed #000;
            }
            
            .header h2 { 
                font-size: 12pt;
                font-weight: bold;
                margin-bottom: 1mm;
            }
            
            .profile-badge { 
                font-size: 9pt;
                font-weight: bold;
                padding: 1mm 0;
                margin: 2mm 0;
                border-top: 1px solid #000;
                border-bottom: 1px solid #000;
            }
            
            .qr-container {
                text-align: center;
                margin: 3mm 0;
            }
            
            .qr-container img {
                width: 35mm;
                height: 35mm;
                display: block;
                margin: 0 auto;
            }
            
            .qr-label {
                font-size: 7pt;
                margin-top: 1mm;
            }
            
            .credentials {
                text-align: left;
                margin: 3mm 0;
                padding: 2mm;
                background: #f0f0f0;
                border: 1px solid #000;
            }
            
            .field { 
                margin: 2mm 0;
                word-break: break-all;
            }
            
            .label { 
                font-weight: bold; 
                font-size: 8pt;
                display: block;
                margin-bottom: 1mm;
            }
            
            .value { 
                font-size: 11pt; 
                font-weight: bold;
                font-family: 'Courier New', monospace;
                display: block;
                word-wrap: break-word;
            }
            
            .separator {
                border-top: 1px dashed #000;
                margin: 3mm 0;
            }
            
            .footer {
                text-align: center;
                font-size: 7pt;
                margin-top: 3mm;
                padding-top: 2mm;
                border-top: 1px dashed #000;
            }
            
            @media print {
                body { 
                    width: 58mm;
                    padding: 2mm;
                }
            }
        </style>
    </head>
    <body>
        <div class="voucher-thermal">
            <div class="header">
                <h2>VOUCHER WiFi</h2>
            </div>
            
            <div style="text-align: center; margin: 2mm 0; font-size: 9pt; font-weight: bold;">
                ${ispName}
            </div>
            ${ispDns ? '<div style="text-align: center; font-size: 7pt; margin-bottom: 2mm;">' + ispDns + '</div>' : ''}
            
            <div class="profile-badge">${profile}</div>
            
            <div class="qr-container">
                <img src="${qrUrl}" alt="QR Code">
                <div class="qr-label">Scan QR untuk Login</div>
            </div>
            
            <div class="separator"></div>
            
            <div class="credentials">
                <div class="field">
                    <div class="label">USERNAME:</div>
                    <div class="value">${username}</div>
                </div>
                <div class="field">
                    <div class="label">PASSWORD:</div>
                    <div class="value">${password}</div>
                </div>
            </div>
            
            <div class="separator"></div>
            
            <div style="text-align: center; font-size: 7pt; margin: 2mm 0;">
                Agent: ${agentName}<br>${agentCode}
            </div>
            
            <div class="footer">
                Terima Kasih
            </div>
        </div>
        
        <script>
            // Auto print when page loads
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        <\/script>
    </body>
    </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}
</script>

<?php include_once('include_foot.php'); ?>
