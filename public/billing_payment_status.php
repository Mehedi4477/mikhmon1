<?php
/*
 * Billing Payment Status - Payment status page for customer portal
 */

session_start();

date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');

if (!isset($_SESSION['billing_portal_customer_id']) || (int)$_SESSION['billing_portal_customer_id'] <= 0) {
    header('Location: billing_login.php');
    exit;
}

$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);
    
    if (!$customer) {
        $_SESSION['billing_portal_flash_error'] = 'Data pelanggan tidak ditemukan.';
        header('Location: billing_portal.php');
        exit;
    }
    
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
    <title>Status Pembayaran - <?= htmlspecialchars($customer['name']); ?></title>
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
            text-align: center;
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
        .payment-status {
            background: #fff;
            border-radius: 10px;
            padding: 40px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .status-success {
            color: #22c55e;
        }
        
        .status-pending {
            color: #f59e0b;
        }
        
        .status-failed {
            color: #ef4444;
        }
        
        .status-text {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .status-description {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
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
                <h2><i class="fa fa-info-circle"></i> Status Pembayaran</h2>
                <p style="margin:0; color:#6b7280;">Pelanggan: <?= htmlspecialchars($customer['name']); ?></p>
                <p style="margin:0; color:#9ca3af; font-size:13px;">Nomor layanan: <?= htmlspecialchars($customer['service_number']); ?></p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Informasi:</strong> Pembayaran Anda sedang diproses. Silakan periksa kembali dalam beberapa saat.
            </div>
        </div>
    </div>
    
    <div class="payment-status">
        <div class="status-icon status-pending">
            <i class="fa fa-clock-o"></i>
        </div>
        <div class="status-text">Pembayaran Sedang Diproses</div>
        <div class="status-description">
            Pembayaran Anda sedang diproses oleh sistem. Silakan periksa kembali status pembayaran Anda dalam beberapa saat.
        </div>
        <div style="text-align: center;">
            <a href="billing_portal.php" class="btn-primary">
                <i class="fa fa-home"></i> Kembali ke Portal
            </a>
        </div>
    </div>
</div>

</body>
</html>