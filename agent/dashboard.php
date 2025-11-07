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

$agent = new Agent();
$agentId = $_SESSION['agent_id'];
$agentData = $agent->getAgentById($agentId);

// Get agent balance
$balance = $agentData['balance'];

// Get transaction history (last 10)
$transactions = $agent->getTransactions($agentId, 10);

// Get statistics for summary cards
try {
    $conn = getDBConnection();
    
    // Total vouchers generated
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agent_vouchers WHERE agent_id = :agent_id");
    $stmt->execute([':agent_id' => $agentId]);
    $totalVouchers = $stmt->fetch()['total'] ?? 0;
    
    // Total transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agent_transactions WHERE agent_id = :agent_id");
    $stmt->execute([':agent_id' => $agentId]);
    $totalTransactions = $stmt->fetch()['total'] ?? 0;
    
    // Vouchers today
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agent_vouchers WHERE agent_id = :agent_id AND DATE(created_at) = :today");
    $stmt->execute([':agent_id' => $agentId, ':today' => $today]);
    $vouchersToday = $stmt->fetch()['total'] ?? 0;
    
    // Total spent (from generate transactions)
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM agent_transactions WHERE agent_id = :agent_id AND transaction_type = 'generate'");
    $stmt->execute([':agent_id' => $agentId]);
    $totalSpent = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $totalVouchers = 0;
    $totalTransactions = 0;
    $vouchersToday = 0;
    $totalSpent = 0;
}

// Get user profiles from MikroTik for voucher generation
include_once('../lib/routeros_api.class.php');
include_once('../include/config.php');

// Get MikroTik session
$sessions = array_keys($data);
$session = null;
foreach ($sessions as $s) {
    if ($s != 'mikhmon') {
        $session = $s;
        break;
    }
}

$profiles = [];
if ($session) {
    try {
        $iphost = explode('!', $data[$session][1])[1];
        $userhost = explode('@|@', $data[$session][2])[1];
        $passwdhost = explode('#|#', $data[$session][3])[1];
        
        $API = new RouterosAPI();
        $API->debug = false;
        
        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $profilesData = $API->comm("/ip/hotspot/user/profile/print");
            foreach ($profilesData as $profile) {
                if (isset($profile['name'])) {
                    $profiles[] = $profile['name'];
                }
            }
            $API->disconnect();
        }
    } catch (Exception $e) {
        // Handle connection error
    }
}

// Get ISP name from MikroTik config
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

include_once('include_head.php');
include_once('include_nav.php');
?>

<style>
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    text-align: center;
    border-radius: 15px;
    margin-bottom: 20px;
}

.balance-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 10px;
}

.balance-amount {
    font-size: 48px;
    font-weight: bold;
    margin: 15px 0;
}

@media (max-width: 768px) {
    .balance-card {
        padding: 20px 15px;
        margin-bottom: 15px;
    }
    
    .balance-amount {
        font-size: 36px;
    }
    
    .balance-label {
        font-size: 12px;
    }
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.voucher-result {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    display: none;
}

.voucher-list {
    margin-top: 15px;
}

/* Summary box styling - consistent with MikhMon */
.box a {
    text-decoration: none;
    color: #f3f4f5;
}

.box a:hover {
    text-decoration: none;
    color: #fff;
}

.box h1 {
    margin: 0;
    padding: 0;
    font-size: 24px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    /* Mobile responsive table for Recent Transactions */
    .content-wrapper {
        padding-left: 10px !important;
        padding-right: 10px !important;
        overflow-x: visible !important;
    }
    
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .col-12 {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    .card {
        margin-bottom: 10px !important;
        border-radius: 4px !important;
    }
    
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: visible !important;
        -webkit-overflow-scrolling: touch !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        margin: 0 !important;
        -ms-overflow-style: -ms-autohiding-scrollbar !important;
        position: relative !important;
    }
    
    .table-responsive::-webkit-scrollbar {
        height: 8px !important;
        -webkit-appearance: none !important;
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1 !important;
        border-radius: 4px !important;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #888 !important;
        border-radius: 4px !important;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555 !important;
    }
    
    .table-responsive table {
        width: 100% !important;
        min-width: 500px !important;
        font-size: 12px !important;
        margin-bottom: 0 !important;
        display: table !important;
        table-layout: auto !important;
    }
    
    .table-responsive th,
    .table-responsive td {
        padding: 8px 6px !important;
        white-space: nowrap !important;
        font-size: 11px !important;
    }
    
    .card-body {
        padding: 10px !important;
        overflow-x: visible !important;
        overflow-y: visible !important;
        max-width: 100% !important;
    }
    
    .card {
        overflow: visible !important;
        max-width: 100% !important;
    }
    
    .card-header {
        padding: 10px !important;
        font-size: 14px !important;
    }
    
    .card-header h3 {
        font-size: 16px !important;
        margin-bottom: 5px !important;
    }
    
    .badge {
        padding: 3px 8px !important;
        font-size: 10px !important;
    }
}
</style>

<div class="row">
<div class="col-12">
    <!-- Balance Card -->
    <div class="card balance-card">
        <div class="balance-label">Your Current Balance</div>
        <div class="balance-amount">Rp <?= number_format($balance, 0, ',', '.'); ?></div>
        <div class="balance-label">Agent Code: <?= htmlspecialchars($agentData['agent_code']); ?></div>
    </div>
    
    <!-- Summary Cards -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-dashboard"></i> Summary</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-3 col-box-6">
                    <div class="box bg-blue bmh-75">
                        <a href="vouchers.php">
                            <h1><?= $totalVouchers; ?>
                                <span style="font-size: 15px;">voucher</span>
                            </h1>
                            <div>
                                <i class="fa fa-ticket"></i> Total Vouchers
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-3 col-box-6">
                    <div class="box bg-green bmh-75">
                        <a href="transactions.php">
                            <h1><?= $totalTransactions; ?>
                                <span style="font-size: 15px;">trans</span>
                            </h1>
                            <div>
                                <i class="fa fa-history"></i> Total Transactions
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-3 col-box-6">
                    <div class="box bg-yellow bmh-75">
                        <a href="vouchers.php">
                            <h1><?= $vouchersToday; ?>
                                <span style="font-size: 15px;">voucher</span>
                            </h1>
                            <div>
                                <i class="fa fa-calendar"></i> Vouchers Today
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-3 col-box-6">
                    <div class="box bg-red bmh-75">
                        <a href="transactions.php">
                            <h1>Rp <?= number_format($totalSpent, 0, ',', '.'); ?></h1>
                            <div>
                                <i class="fa fa-money"></i> Total Spent
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- Generate Voucher Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-ticket"></i> Generate Voucher</h3>
            </div>
            <div class="card-body">
                <form id="generateForm">
                    <input type="hidden" name="agent_id" value="<?= $agentId; ?>">
                    <input type="hidden" name="agent_token" value="<?= $_SESSION['agent_token']; ?>">
                    
                    <div class="form-group">
                        <label>Profile</label>
                        <select name="profile" class="form-control" required>
                            <option value="">-- Select Profile --</option>
                            <?php foreach ($profiles as $profile): ?>
                            <option value="<?= htmlspecialchars($profile); ?>"><?= htmlspecialchars($profile); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="100" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Phone (Optional)</label>
                        <input type="text" name="customer_phone" class="form-control" placeholder="Customer phone number">
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Name (Optional)</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Customer name">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="generateBtn">
                        <i class="fa fa-plus-circle"></i> Generate Voucher
                    </button>
                </form>
                
                <div id="voucherResult" class="voucher-result">
                    <h3><i class="fa fa-check-circle"></i> Generated Vouchers</h3>
                    <div id="voucherList" class="voucher-list"></div>
                    <div style="margin-top: 15px;">
                        <strong>New Balance:</strong> <span id="newBalance">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transaction History Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-history"></i> Recent Transactions</h3>
            </div>
            <div class="card-body" style="padding: 15px;">
                <?php if (!empty($transactions)): ?>
                <div class="table-responsive" style="overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; display: block !important; -ms-overflow-style: -ms-autohiding-scrollbar !important;">
                <table class="table table-bordered table-hover" style="width: 100% !important; min-width: 500px !important; margin-bottom: 0 !important; display: table !important;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Date</th>
                            <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Type</th>
                            <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Amount</th>
                            <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trx): ?>
                        <tr>
                            <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;"><?= date('d/m H:i', strtotime($trx['created_at'])); ?></td>
                            <td style="padding: 8px 6px; font-size: 11px;">
                                <span class="badge badge-<?= $trx['transaction_type']; ?>">
                                    <?= ucfirst($trx['transaction_type']); ?>
                                </span>
                            </td>
                            <td style="padding: 8px 6px; font-size: 11px; font-weight: bold; color: <?= $trx['transaction_type'] == 'topup' ? '#10b981' : '#ef4444'; ?>; white-space: nowrap;">
                                <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                            </td>
                            <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;"><?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                <p>No transactions found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modern Voucher Popup Modal -->
<div id="voucherModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa fa-check-circle"></i> Voucher Berhasil Di-generate!</h2>
            <span class="modal-close" onclick="closeVoucherModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="success-message">
                <i class="fa fa-check-circle-o"></i>
                <p>Berhasil generate <strong id="voucherCount">0</strong> voucher</p>
                <p class="balance-info">Saldo Anda: <strong id="modalBalance">Rp 0</strong></p>
            </div>
            
            <div id="voucherCards" class="voucher-cards"></div>
        </div>
        
        <div class="modal-footer">
            <button onclick="printVouchers()" class="btn btn-primary">
                <i class="fa fa-print"></i> Print Normal
            </button>
            <button onclick="printVouchersThermal()" class="btn btn-info">
                <i class="fa fa-print"></i> Print Thermal 58mm
            </button>
            <button onclick="sendViaWhatsApp()" class="btn btn-success">
                <i class="fa fa-whatsapp"></i> Kirim via WhatsApp
            </button>
            <button onclick="closeVoucherModal()" class="btn">
                <i class="fa fa-times"></i> Tutup
            </button>
        </div>
    </div>
</div>

<script>
    let generatedVouchers = [];
    
    // ISP and Agent info
    const ispName = "<?= addslashes($ispName); ?>";
    const ispDns = "<?= addslashes($ispDns); ?>";
    const agentName = "<?= addslashes($agentData['name']); ?>";
    const agentCode = "<?= addslashes($agentData['agent_code']); ?>";
    
    document.getElementById('generateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = document.getElementById('generateBtn');
        const originalBtnText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
        submitBtn.disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        
        // Send request to API
        fetch('../api/agent_generate_voucher.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store vouchers globally
                generatedVouchers = data.vouchers;
                
                // Show modal with vouchers
                showVoucherModal(data);
                
                // Reset form
                form.reset();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat generate voucher');
        })
        .finally(() => {
            // Reset button
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
    });
    
    function showVoucherModal(data) {
        const modal = document.getElementById('voucherModal');
        const voucherCards = document.getElementById('voucherCards');
        
        // Update count and balance
        document.getElementById('voucherCount').textContent = data.vouchers.length;
        document.getElementById('modalBalance').textContent = 'Rp ' + Number(data.balance).toLocaleString('id-ID');
        
        // Generate voucher cards
        let cardsHtml = '';
        data.vouchers.forEach((voucher, index) => {
            cardsHtml += `
            <div class="voucher-card-modern">
                <div class="voucher-card-header">
                    <span class="voucher-number">#${index + 1}</span>
                    <span class="voucher-profile-badge">${voucher.profile}</span>
                </div>
                <div class="voucher-card-body">
                    <div class="qr-code-container">
                        <img class="qr-code-image" 
                             alt="QR Code" 
                             data-username="${voucher.username}"
                             data-password="${voucher.password}">
                        <div class="qr-label">Scan untuk login</div>
                    </div>
                    <div class="voucher-field">
                        <label>Username</label>
                        <div class="voucher-value">
                            <span class="value-text">${voucher.username}</span>
                            <button onclick="copyToClipboard('${voucher.username}')" class="btn-copy" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="voucher-field">
                        <label>Password</label>
                        <div class="voucher-value">
                            <span class="value-text">${voucher.password}</span>
                            <button onclick="copyToClipboard('${voucher.password}')" class="btn-copy" title="Copy">
                                <i class="fa fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            `;
        });
        
        voucherCards.innerHTML = cardsHtml;
        
        // Set QR code images after DOM is ready
        setTimeout(() => {
            const qrImages = document.querySelectorAll('.qr-code-image');
            qrImages.forEach(img => {
                const username = img.getAttribute('data-username');
                const password = img.getAttribute('data-password');
                const loginUrl = 'http://10.5.50.1/login?username=' + username + '&password=' + password;
                const qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' + encodeURIComponent(loginUrl) + '&choe=UTF-8';
                img.src = qrUrl;
                
                // Fallback if Google Charts fails
                img.onerror = function() {
                    this.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(loginUrl);
                };
            });
        }, 100);
        
        modal.style.display = 'flex';
        
        // Add animation
        setTimeout(() => {
            modal.querySelector('.modal-content').style.transform = 'scale(1)';
            modal.querySelector('.modal-content').style.opacity = '1';
        }, 10);
    }
    
    function closeVoucherModal(skipReload = false) {
        const modal = document.getElementById('voucherModal');
        modal.querySelector('.modal-content').style.transform = 'scale(0.7)';
        modal.querySelector('.modal-content').style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
            
            // Reload page to update balance and transaction history
            if (!skipReload) {
                location.reload();
            }
        }, 300);
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show temporary success message
            const btn = event.target.closest('.btn-copy');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-check"></i>';
            btn.style.background = '#10b981';
            
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.style.background = '';
            }, 1000);
        }).catch(err => {
            alert('Gagal copy: ' + err);
        });
    }
    
    function printVouchers() {
        const printWindow = window.open('', '_blank');
        const vouchers = generatedVouchers;
        
        let printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Voucher</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .voucher-print { 
                    border: 2px dashed #333; 
                    padding: 20px; 
                    margin: 20px 0; 
                    page-break-inside: avoid;
                    width: 300px;
                }
                .voucher-print h3 { margin: 0 0 15px 0; text-align: center; }
                .voucher-print .field { margin: 10px 0; }
                .voucher-print .label { font-weight: bold; font-size: 12px; color: #666; }
                .voucher-print .value { 
                    font-size: 18px; 
                    font-family: monospace; 
                    background: #f0f0f0; 
                    padding: 8px; 
                    margin-top: 5px;
                    border-radius: 4px;
                }
                .voucher-print .profile { 
                    text-align: center; 
                    background: #667eea; 
                    color: white; 
                    padding: 5px; 
                    border-radius: 4px;
                    margin-bottom: 15px;
                }
                @media print {
                    .voucher-print { page-break-after: always; }
                }
            </style>
        </head>
        <body>
        `;
        
        vouchers.forEach((voucher, index) => {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent('http://10.5.50.1/login?username=' + voucher.username + '&password=' + voucher.password)}`;
            
            printContent += `
            <div class="voucher-print">
                <h3>Voucher WiFi #${index + 1}</h3>
                <div style="text-align: center; margin-bottom: 10px;">
                    <div style="font-weight: bold; font-size: 14px; color: #667eea;">${ispName}</div>
                    ${ispDns ? '<div style="font-size: 11px; color: #999;">' + ispDns + '</div>' : ''}
                </div>
                <div class="profile">${voucher.profile}</div>
                <div style="text-align: center; margin: 15px 0;">
                    <img src="${qrUrl}" width="150" height="150" style="border: 2px solid #667eea; padding: 5px; border-radius: 8px;">
                    <div style="font-size: 11px; color: #666; margin-top: 5px;">Scan untuk login otomatis</div>
                </div>
                <div class="field">
                    <div class="label">Username:</div>
                    <div class="value">${voucher.username}</div>
                </div>
                <div class="field">
                    <div class="label">Password:</div>
                    <div class="value">${voucher.password}</div>
                </div>
                <div style="text-align: center; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ccc;">
                    <div style="font-size: 10px; color: #999;">Agent: ${agentName} (${agentCode})</div>
                </div>
            </div>
            `;
        });
        
        printContent += `
        </body>
        </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }
    
    function printVouchersThermal() {
        const printWindow = window.open('', '_blank');
        const vouchers = generatedVouchers;
        
        let printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Voucher - Thermal 58mm</title>
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
                    margin-bottom: 10mm;
                    page-break-after: always;
                    text-align: center;
                }
                
                .voucher-thermal:last-child {
                    page-break-after: auto;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 3mm;
                    padding-bottom: 2mm;
                    border-bottom: 1px dashed #000;
                }
                
                .header h3 { 
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
                    .voucher-thermal { 
                        page-break-after: always;
                    }
                    .voucher-thermal:last-child {
                        page-break-after: auto;
                    }
                }
            </style>
        </head>
        <body>
        `;
        
        vouchers.forEach((voucher, index) => {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent('http://10.5.50.1/login?username=' + voucher.username + '&password=' + voucher.password)}`;
            
            printContent += `
            <div class="voucher-thermal">
                <div class="header">
                    <h3>VOUCHER WiFi</h3>
                    <div>#${index + 1}</div>
                </div>
                
                <div style="text-align: center; margin: 2mm 0; font-size: 9pt; font-weight: bold;">
                    ${ispName}
                </div>
                ${ispDns ? '<div style="text-align: center; font-size: 7pt; margin-bottom: 2mm;">' + ispDns + '</div>' : ''}
                
                <div class="profile-badge">${voucher.profile}</div>
                
                <div class="qr-container">
                    <img src="${qrUrl}" alt="QR">
                    <div class="qr-label">Scan QR untuk Login</div>
                </div>
                
                <div class="separator"></div>
                
                <div class="credentials">
                    <div class="field">
                        <div class="label">USERNAME:</div>
                        <div class="value">${voucher.username}</div>
                    </div>
                    <div class="field">
                        <div class="label">PASSWORD:</div>
                        <div class="value">${voucher.password}</div>
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
            `;
        });
        
        printContent += `
        </body>
        </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }
    
    function sendViaWhatsApp() {
        const phone = prompt('Masukkan nomor WhatsApp (contoh: 628123456789):');
        
        if (!phone) return;
        
        let message = '🎫 *Voucher WiFi*\n\n';
        
        generatedVouchers.forEach((voucher, index) => {
            message += `*Voucher #${index + 1}*\n`;
            message += `Profile: ${voucher.profile}\n`;
            message += `Username: \`${voucher.username}\`\n`;
            message += `Password: \`${voucher.password}\`\n\n`;
        });
        
        message += '✅ Terima kasih!';
        
        const whatsappUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
        window.open(whatsappUrl, '_blank');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('voucherModal');
        if (event.target == modal) {
            if (confirm('Tutup modal? Halaman akan di-refresh untuk update saldo.')) {
                closeVoucherModal();
            }
        }
    }
</script>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white !important;
    border-radius: 15px;
    max-width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.7);
    opacity: 0;
    transition: all 0.3s;
    color: #333 !important;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white !important;
}

.modal-header h2 {
    color: #333 !important;
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: #999 !important;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #333 !important;
}

.modal-body {
    padding: 20px;
    background: white !important;
    color: #333 !important;
}

.success-message {
    text-align: center;
    margin-bottom: 20px;
    color: #333 !important;
}

.success-message i {
    font-size: 48px;
    color: #28a745 !important;
    margin-bottom: 10px;
}

.success-message p {
    color: #333 !important;
    font-size: 16px;
    margin: 5px 0;
}

.balance-info {
    color: #666 !important;
    font-size: 14px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.voucher-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.voucher-card-modern {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    background: #f8f9fa;
    color: #333 !important;
}

.voucher-card-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.voucher-number {
    font-weight: bold;
    font-size: 14px;
    color: #333 !important;
}

.voucher-profile-badge {
    background: #667eea;
    color: white !important;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.qr-code-container {
    text-align: center;
    margin: 15px 0;
}

.qr-code-image {
    width: 150px;
    height: 150px;
    border: 2px solid #667eea;
    padding: 5px;
    border-radius: 8px;
    background: white;
}

.qr-label {
    margin-top: 8px;
    font-size: 12px;
    color: #666 !important;
    font-weight: 500;
}

.voucher-field {
    margin: 10px 0;
}

.voucher-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #555 !important;
    margin-bottom: 5px;
}

.voucher-value {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.value-text {
    flex: 1;
    font-family: monospace;
    font-weight: bold;
    font-size: 14px;
    color: #333 !important;
    text-align: left;
}

.btn-copy {
    padding: 5px 10px;
    border: none;
    background: #667eea;
    color: white;
    border-radius: 5px;
    cursor: pointer;
}

/* Mobile Responsive untuk Modal */
@media (max-width: 768px) {
    .modal-content {
        width: 95% !important;
        max-width: 95% !important;
        max-height: 95vh !important;
        margin: 10px !important;
    }
    
    .modal-header {
        padding: 15px !important;
    }
    
    .modal-header h2 {
        font-size: 16px !important;
    }
    
    .modal-body {
        padding: 15px !important;
        max-height: calc(95vh - 180px) !important;
    }
    
    .modal-footer {
        padding: 15px !important;
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .modal-footer .btn {
        width: 100% !important;
        margin: 0 !important;
        padding: 12px 20px !important;
        font-size: 14px !important;
    }
    
    .voucher-cards {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .voucher-card-modern {
        padding: 12px !important;
    }
    
    .qr-code-image {
        width: 120px !important;
        height: 120px !important;
    }
    
    .success-message {
        padding: 15px !important;
    }
    
    .success-message i {
        font-size: 36px !important;
    }
    
    .success-message p {
        font-size: 14px !important;
    }
}

/* Untuk layar sangat kecil */
@media (max-width: 480px) {
    .modal-content {
        width: 98% !important;
        max-height: 98vh !important;
    }
    
    .modal-header h2 {
        font-size: 14px !important;
    }
    
    .modal-footer .btn {
        padding: 10px 15px !important;
        font-size: 13px !important;
    }
    
    .qr-code-image {
        width: 100px !important;
        height: 100px !important;
    }
}
</style>

<?php include_once('include_foot.php'); ?>
