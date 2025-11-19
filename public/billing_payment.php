<?php
/*
 * Billing Payment - Online payment page for customer portal
 */

session_start();

date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../lib/BillingService.class.php');
require_once(__DIR__ . '/../lib/PublicPayment.class.php');

if (!isset($_SESSION['billing_portal_customer_id']) || (int)$_SESSION['billing_portal_customer_id'] <= 0) {
    header('Location: billing_login.php');
    exit;
}

// Check if we have an invoice to pay
if (!isset($_SESSION['billing_payment_invoice'])) {
    header('Location: billing_portal.php');
    exit;
}

$invoice = $_SESSION['billing_payment_invoice'];
$activeCustomerId = (int)$_SESSION['billing_portal_customer_id'];

try {
    $billingService = new BillingService();
    $customer = $billingService->getCustomerById($activeCustomerId);
    
    if (!$customer || (int)$customer['id'] !== (int)$invoice['customer_id']) {
        unset($_SESSION['billing_payment_invoice']);
        $_SESSION['billing_portal_flash_error'] = 'Data pelanggan tidak valid.';
        header('Location: billing_portal.php');
        exit;
    }
    
    $profile = $billingService->getProfileById((int)$customer['profile_id']);
    
    // Get active payment gateway
    $payment = new PublicPayment();
    $gateway = $payment->getActiveGateway();
    
    if (!$gateway) {
        throw new Exception('Payment gateway tidak aktif. Silakan hubungi admin.');
    }
    
    // Get available payment methods
    $methods = $payment->getPaymentMethods((float)$invoice['amount']);
    
    if (empty($methods)) {
        throw new Exception('Tidak ada metode pembayaran yang tersedia untuk jumlah tagihan ini.');
    }
    
    // Group methods by type
    $grouped_methods = [];
    foreach ($methods as $method) {
        $type = $method['method_type'];
        if (!isset($grouped_methods[$type])) {
            $grouped_methods[$type] = [];
        }
        $grouped_methods[$type][] = $method;
    }
    
    $type_labels = [
        'qris' => 'QRIS',
        'va' => 'Virtual Account',
        'ewallet' => 'E-Wallet',
        'retail' => 'Retail Store'
    ];
    
    $type_icons = [
        'qris' => 'fa-qrcode',
        'va' => 'fa-bank',
        'ewallet' => 'fa-mobile',
        'retail' => 'fa-shopping-cart'
    ];
    
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

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    
    try {
        // Validate payment method
        $selected_method = null;
        foreach ($methods as $method) {
            if ($method['method_code'] === $payment_method) {
                $selected_method = $method;
                break;
            }
        }
        
        if (!$selected_method) {
            throw new Exception('Metode pembayaran tidak valid.');
        }
        
        // Calculate admin fee
        $admin_fee = 0;
        if ($selected_method['admin_fee_type'] == 'percentage' || $selected_method['admin_fee_type'] == 'percent') {
            $admin_fee = ceil(((float)$invoice['amount'] * (float)$selected_method['admin_fee_value']) / 100);
        } else {
            $admin_fee = (float)$selected_method['admin_fee_value'];
        }
        
        $total_amount = (float)$invoice['amount'] + $admin_fee;
        
        // Create payment transaction
        $payment_data = [
            'payment_method' => $payment_method,
            'amount' => $total_amount,
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'] ?? '',
            'customer_email' => $customer['email'] ?? '',
            'product_name' => 'Pembayaran Tagihan ' . ($profile['profile_name'] ?? 'Internet'),
            'callback_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/billing_payment_callback.php',
            'return_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/public/billing_payment_status.php'
        ];
        
        $result = $payment->createPayment($payment_data);
        
        if ($result['success']) {
            // Save payment info to session
            $_SESSION['billing_payment_info'] = [
                'invoice_id' => $invoice['id'],
                'payment_reference' => $result['payment_reference'],
                'payment_url' => $result['payment_url'],
                'qr_url' => $result['qr_url'],
                'virtual_account' => $result['virtual_account'],
                'amount' => $invoice['amount'],
                'admin_fee' => $admin_fee,
                'total_amount' => $total_amount,
                'method_name' => $selected_method['method_name'],
                'expired_at' => $result['expired_at']
            ];
            
            // Redirect to payment page
            if (!empty($result['payment_url'])) {
                header('Location: ' . $result['payment_url']);
                exit;
            } else {
                // Redirect to payment instructions page
                header('Location: billing_payment_instructions.php');
                exit;
            }
        } else {
            throw new Exception($result['message'] ?? 'Gagal membuat pembayaran.');
        }
        
    } catch (Exception $e) {
        $_SESSION['billing_portal_flash_error'] = 'Pembayaran gagal: ' . htmlspecialchars($e->getMessage());
        header('Location: billing_payment.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Tagihan - <?= htmlspecialchars($customer['name']); ?></title>
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
        .logout-link { 
            background:#f3f4f6; 
            border-radius:6px; 
            padding:8px 14px; 
            font-size:13px; 
            color:#1f2937; 
            text-decoration:none; 
            border:1px solid #cbd5f5; 
            transition: all 0.2s; 
            font-weight:600; 
            display:inline-flex; 
            align-items:center; 
            gap:6px; 
        }
        .logout-link:hover { 
            background:#e5e7eb; 
            color:#0f172a; 
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
        .badge-status { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 999px; 
            font-size: 12px; 
        }
        .badge-status.paid { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .badge-status.unpaid { 
            background: #fee2e2; 
            color: #991b1b; 
        }
        .badge-status.overdue { 
            background: #fde68a; 
            color: #92400e; 
        }
        .invoice-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .invoice-table th, 
        .invoice-table td { 
            border: 1px solid #e5e7eb; 
            padding: 10px; 
            font-size: 13px; 
        }
        .invoice-table th { 
            background: #f3f4f6; 
            font-weight: 600; 
        }
        .form-control[disabled] { 
            background: #f3f4f6; 
        }
        .btn { 
            cursor: pointer; 
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
        .alert-error {
            color: #991b1b;
        }
        .alert-error::before {
            background: #dc2626;
        }
        .payment-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .payment-section h4 {
            color: #3c8dbc;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .payment-section h4 i {
            margin-right: 8px;
            width: 20px;
        }
        
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .payment-methods-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .wrapper {
                padding: 0 10px;
            }
            
            .payment-section {
                padding: 15px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .payment-methods-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .payment-method {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            color: #333;
            min-height: 60px;
        }
        
        .payment-method:hover {
            border-color: #3c8dbc;
            background: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .payment-method.selected {
            border-color: #3c8dbc;
            background: #e3f2fd;
            box-shadow: 0 4px 12px rgba(60, 141, 188, 0.2);
        }
        
        .payment-method input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .payment-method .method-info {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 12px;
        }
        
        .payment-method .method-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .payment-method .method-details {
            flex: 1;
        }
        
        .payment-method input[type="radio"] {
            margin: 0;
            transform: scale(1.3);
            accent-color: #3c8dbc;
        }
        
        .btn-pay {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            background: #3c8dbc;
            color: white;
            border: none;
            border-radius: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-pay:hover:not(:disabled) {
            background: #2c6aa0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 141, 188, 0.3);
        }
        
        .btn-pay:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #ccc;
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
                <h2><i class="fa fa-credit-card"></i> Pembayaran Tagihan</h2>
                <p style="margin:0; color:#6b7280;">Pelanggan: <?= htmlspecialchars($customer['name']); ?></p>
                <p style="margin:0; color:#9ca3af; font-size:13px;">Nomor layanan: <?= htmlspecialchars($customer['service_number']); ?></p>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['billing_portal_flash_error'])): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($_SESSION['billing_portal_flash_error']); ?>
                </div>
                <?php unset($_SESSION['billing_portal_flash_error']); ?>
            <?php endif; ?>
            
            <div class="summary-grid">
                <div class="box" style="background:#3c8dbc;">
                    <h2><?= htmlspecialchars($profile['profile_name'] ?? 'Tidak diketahui'); ?></h2>
                    <p style="margin:0;">Harga: Rp <?= number_format($profile['price_monthly'] ?? 0, 0, ',', '.'); ?>/bulan</p>
                </div>
                <div class="box" style="background:#00a65a;">
                    <h2>Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></h2>
                    <p style="margin:0;">Jumlah Tagihan</p>
                </div>
                <div class="box" style="background:#f39c12;">
                    <h2><?= htmlspecialchars(date('d M Y', strtotime($invoice['due_date']))); ?></h2>
                    <p style="margin:0;">Jatuh Tempo</p>
                </div>
                <div class="box" style="background:#dd4b39;">
                    <h2><?= htmlspecialchars($invoice['period']); ?></h2>
                    <p style="margin:0;">Periode Tagihan</p>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>Informasi:</strong> Pilih metode pembayaran yang tersedia di bawah ini untuk menyelesaikan pembayaran tagihan Anda.
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <form method="POST" action="" id="paymentForm">
        <?php foreach ($grouped_methods as $type => $type_methods): ?>
        <div class="payment-section">
            <h4>
                <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>"></i>
                <?= $type_labels[$type] ?? ucfirst($type); ?>
            </h4>
            
            <div class="payment-methods-grid">
                <?php foreach ($type_methods as $method): ?>
                <label class="payment-method">
                    <div class="method-info">
                        <input type="radio" name="payment_method" value="<?= htmlspecialchars($method['method_code']); ?>" required>
                        <div class="method-icon">
                            <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>" style="font-size: 24px; color: #3c8dbc;"></i>
                        </div>
                        <div class="method-details">
                            <div class="method-name" style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                <?= htmlspecialchars($method['method_name']); ?>
                            </div>
                            <div class="method-fee" style="font-size: 12px; color: #666;">
                                <?php if ($method['admin_fee_value'] > 0): ?>
                                    <?php if ($method['admin_fee_type'] == 'percentage'): ?>
                                        +<?= $method['admin_fee_value']; ?>%
                                    <?php else: ?>
                                        +Rp <?= number_format($method['admin_fee_value'], 0, ',', '.'); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #28a745;">Gratis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <button type="submit" class="btn-pay" id="btnPay" disabled>
            <i class="fa fa-lock"></i> Lanjutkan Pembayaran
        </button>
    </form>
</div>

<script>
// Handle payment method selection
document.querySelectorAll('.payment-method').forEach(method => {
    method.addEventListener('click', function() {
        // Remove selected class from all methods
        document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
        
        // Add selected class to clicked method
        this.classList.add('selected');
        
        // Check the radio button
        this.querySelector('input[type="radio"]').checked = true;
        
        // Enable pay button
        document.getElementById('btnPay').disabled = false;
    });
});

// Form submission
document.getElementById('paymentForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnPay');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
});
</script>

</body>
</html>