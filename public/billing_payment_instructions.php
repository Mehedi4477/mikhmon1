<?php
/*
 * Billing Payment Instructions - Payment instructions page for customer portal
 */

session_start();

date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

if (!isset($_SESSION['billing_portal_customer_id']) || (int)$_SESSION['billing_portal_customer_id'] <= 0) {
    header('Location: billing_login.php');
    exit;
}

// Check if we have payment info
if (!isset($_SESSION['billing_payment_info'])) {
    header('Location: billing_portal.php');
    exit;
}

$payment_info = $_SESSION['billing_payment_info'];
$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);
    
    if (!$customer) {
        unset($_SESSION['billing_payment_info']);
        $_SESSION['billing_portal_flash_error'] = 'Data pelanggan tidak ditemukan.';
        header('Location: billing_portal.php');
        exit;
    }
    
    $invoice = $billingService->getInvoiceById((int)$payment_info['invoice_id']);
    if (!$invoice || (int)$invoice['customer_id'] !== $activeCustomerId) {
        unset($_SESSION['billing_payment_info']);
        $_SESSION['billing_portal_flash_error'] = 'Data invoice tidak valid.';
        header('Location: billing_portal.php');
        exit;
    }
    
    $profile = $billingService->getProfileById((int)$customer['profile_id']);
    
} catch (Throwable $e) {
    $_SESSION['billing_portal_flash_error'] = 'Terjadi kesalahan: ' . htmlspecialchars($e->getMessage());
    header('Location: billing_portal.php');
    exit;
}

$theme = 'default';
$themecolor = '#3a4149';
if (file_exists(__DIR__ . '/../include/theme.php')) {
    include(__DIR__ . '/../include/theme.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Pembayaran - <?= htmlspecialchars($customer['name']); ?></title>
    <meta name="theme-color" content="<?= $themecolor; ?>" />
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="../img/favicon.png" />
    <style>
        body { 
            background-color: #ecf0f5; 
            font-family: 'Source Sans Pro', Arial, sans-serif; 
        }
        .wrapper { 
            max-width: 940px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        .card { 
            background: #fff; 
            border-radius: 4px; 
            box-shadow: 0 1px 1px rgba(0,0,0,.1); 
            margin-bottom: 20px; 
        }
        .card-header { 
            padding: 16px 20px; 
            border-bottom: 1px solid #e5e7eb; 
            display:flex; 
            flex-wrap:wrap; 
            align-items:center; 
            justify-content:space-between; 
            gap:10px; 
        }
        .card-header .header-info { 
            flex:1 1 auto; 
        }
        .card-body { 
            padding: 20px; 
        }
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .box { 
            border-radius: 4px; 
            padding: 18px; 
            color: #fff; 
            min-height: 110px; 
        }
        .box h2 { 
            margin: 0 0 6px; 
            font-size: 26px; 
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: #ffffff;
            line-height: 1.55;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            max-width: min(100%, 560px);
            box-sizing: border-box;
            flex-wrap: wrap;
        }
        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 10px 0 0 10px;
        }
        .alert-info {
            color: #075985;
        }
        .alert-info::before {
            background: #0ea5e9;
        }
        .alert-warning {
            color: #92400e;
        }
        .alert-warning::before {
            background: #d97706;
        }
        .alert-success {
            color: #166534;
        }
        .alert-success::before {
            background: #22c55e;
        }
        .payment-instructions {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .payment-detail:last-child {
            border-bottom: none;
        }
        
        .payment-detail .label {
            font-weight: 600;
            color: #333;
        }
        
        .payment-detail .value {
            text-align: right;
            color: #666;
        }
        
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        
        .qr-code img {
            max-width: 200px;
            height: auto;
        }
        
        .virtual-account {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #3c8dbc;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3c8dbc;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .btn-primary {
            background: #3c8dbc;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        
        .btn-primary:hover {
            background: #2c6aa0;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <a href="billing_portal.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Kembali ke Portal
    </a>
    
    <div class="card">
        <div class="card-header">
            <div class="header-info">
                <h2><i class="fa fa-info-circle"></i> Instruksi Pembayaran</h2>
                <p style="margin:0; color:#6b7280;">Pelanggan: <?= htmlspecialchars($customer['name']); ?></p>
                <p style="margin:0; color:#9ca3af; font-size:13px;">Nomor layanan: <?= htmlspecialchars($customer['service_number']); ?></p>
            </div>
        </div>
        <div class="card-body">
            <div class="summary-grid">
                <div class="box" style="background:#3c8dbc;">
                    <h2><?= htmlspecialchars($profile['profile_name'] ?? 'Tidak diketahui'); ?></h2>
                    <p style="margin:0;">Harga: Rp <?= number_format($profile['price_monthly'] ?? 0, 0, ',', '.'); ?>/bulan</p>
                </div>
                <div class="box" style="background:#00a65a;">
                    <h2>Rp <?= number_format($payment_info['amount'], 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Jumlah Tagihan</p>
                </div>
                <div class="box" style="background:#f39c12;">
                    <h2>Rp <?= number_format($payment_info['admin_fee'], 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Biaya Admin</p>
                </div>
                <div class="box" style="background:#dd4b39;">
                    <h2>Rp <?= number_format($payment_info['total_amount'], 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Total Bayar</p>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>Informasi:</strong> Silakan ikuti instruksi di bawah ini untuk menyelesaikan pembayaran tagihan Anda.
            </div>
        </div>
    </div>
    
    <div class="payment-instructions">
        <h3><i class="fa fa-credit-card"></i> Detail Pembayaran</h3>
        
        <div class="payment-detail">
            <span class="label">Metode Pembayaran:</span>
            <span class="value"><?= htmlspecialchars($payment_info['method_name']); ?></span>
        </div>
        
        <div class="payment-detail">
            <span class="label">Nomor Referensi:</span>
            <span class="value"><?= htmlspecialchars($payment_info['payment_reference']); ?></span>
        </div>
        
        <div class="payment-detail">
            <span class="label">Tanggal Kadaluarsa:</span>
            <span class="value"><?= htmlspecialchars(date('d M Y H:i', strtotime($payment_info['expired_at']))); ?></span>
        </div>
        
        <?php if (!empty($payment_info['qr_url'])): ?>
        <div class="qr-code">
            <p><strong>Scan QR Code di bawah ini:</strong></p>
            <img src="<?= htmlspecialchars($payment_info['qr_url']); ?>" alt="QR Code Pembayaran">
        </div>
        <?php endif; ?>
        
        <?php if (!empty($payment_info['virtual_account'])): ?>
        <div class="virtual-account">
            <?= htmlspecialchars($payment_info['virtual_account']); ?>
        </div>
        <p style="text-align: center; color: #666;">
            Gunakan nomor virtual account di atas untuk melakukan pembayaran melalui ATM, Mobile Banking, atau Internet Banking.
        </p>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="billing_portal.php" class="btn-primary">
                <i class="fa fa-check"></i> Saya Sudah Bayar
            </a>
        </div>
    </div>
</div>

</body>
</html>