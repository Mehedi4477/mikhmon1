<?php
/*
 * Payment Methods Management
 * Admin panel for managing payment methods and fees
 */

session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin.php');
    exit;
}

include_once('../include/db_config.php');

// Get theme
$theme = 'default';
$themecolor = '#3a4149';
if (file_exists('../include/theme.php')) {
    include_once('../include/theme.php');
}

$conn = getDBConnection();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_method':
                $id = $_POST['id'];
                $admin_fee_value = $_POST['admin_fee_value'];
                $admin_fee_type = $_POST['admin_fee_type'];
                $min_amount = $_POST['min_amount'];
                $max_amount = $_POST['max_amount'];
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $stmt = $conn->prepare("UPDATE payment_methods SET 
                        admin_fee_value = ?, admin_fee_type = ?, min_amount = ?, max_amount = ?, is_active = ?
                        WHERE id = ?");
                    $stmt->execute([$admin_fee_value, $admin_fee_type, $min_amount, $max_amount, $is_active, $id]);
                    
                    $message = 'Payment method updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating payment method: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_status':
                $id = $_POST['id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status ? 0 : 1;
                
                try {
                    $stmt = $conn->prepare("UPDATE payment_methods SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $id]);
                    
                    $message = 'Payment method status updated!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating status: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all payment methods
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY gateway_name, sort_order, id");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by gateway
$grouped_methods = [];
foreach ($payment_methods as $method) {
    $gateway = $method['gateway_name'];
    if (!isset($grouped_methods[$gateway])) {
        $grouped_methods[$gateway] = [];
    }
    $grouped_methods[$gateway][] = $method;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Methods Management</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <style>
        body {
            background: var(--bg-color, #f4f4f4);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .card {
            background: var(--card-bg, white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color, #e0e0e0);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: var(--primary-color, #3c8dbc);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .method-card {
            border: 1px solid var(--border-color, #ddd);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .method-header {
            background: var(--light-bg, #f8f9fa);
            padding: 15px;
            border-bottom: 1px solid var(--border-color, #ddd);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .method-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .method-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color, #3c8dbc);
            color: white;
            border-radius: 8px;
        }
        
        .method-details h5 {
            margin: 0;
            color: var(--text-color, #333);
            font-size: 1.1rem;
        }
        
        .method-details small {
            color: var(--text-muted, #666);
        }
        
        .method-body {
            padding: 20px;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--text-color, #333);
        }
        
        .form-control {
            border-radius: 6px;
            border: 1px solid var(--border-color, #ddd);
        }
        
        .btn-update {
            background: var(--success-color, #28a745);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .btn-toggle {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 0.85rem;
        }
        
        .btn-toggle.active {
            background: var(--success-color, #28a745);
            color: white;
        }
        
        .btn-toggle.inactive {
            background: var(--danger-color, #dc3545);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .gateway-section {
            margin-bottom: 30px;
        }
        
        .gateway-title {
            background: var(--primary-color, #3c8dbc);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .fee-display {
            font-weight: 600;
            color: var(--primary-color, #3c8dbc);
        }
        
        @media (max-width: 768px) {
            .method-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-row .col-md-3,
            .form-row .col-md-4,
            .form-row .col-md-6 {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="card">
            <div class="card-header">
                <i class="fa fa-credit-card"></i> Payment Methods Management
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0">Manage payment methods, fees, and settings for your payment gateway.</p>
                    <a href="../admin.php" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Admin
                    </a>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?>">
                    <i class="fa fa-<?= $messageType === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                    <?= htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php foreach ($grouped_methods as $gateway => $methods): ?>
                <div class="gateway-section">
                    <div class="gateway-title">
                        <i class="fa fa-credit-card"></i> <?= strtoupper($gateway); ?> Payment Methods
                    </div>
                    
                    <?php foreach ($methods as $method): ?>
                    <div class="method-card">
                        <div class="method-header">
                            <div class="method-info">
                                <div class="method-icon">
                                    <i class="fa <?= htmlspecialchars($method['icon'] ?? 'fa-credit-card'); ?>"></i>
                                </div>
                                <div class="method-details">
                                    <h5><?= htmlspecialchars($method['method_name']); ?></h5>
                                    <small><?= htmlspecialchars($method['method_code']); ?> • <?= ucfirst($method['method_type']); ?></small>
                                </div>
                            </div>
                            <div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $method['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?= $method['is_active']; ?>">
                                    <button type="submit" class="btn-toggle <?= $method['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?= $method['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="method-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_method">
                                <input type="hidden" name="id" value="<?= $method['id']; ?>">
                                
                                <div class="row form-row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Admin Fee Type</label>
                                            <select name="admin_fee_type" class="form-control">
                                                <option value="fixed" <?= $method['admin_fee_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                                <option value="percentage" <?= $method['admin_fee_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Admin Fee Value</label>
                                            <input type="number" name="admin_fee_value" class="form-control" 
                                                   value="<?= $method['admin_fee_value']; ?>" step="0.01" min="0">
                                            <small class="text-muted">
                                                Current: <span class="fee-display">
                                                    <?= $method['admin_fee_type'] === 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value']); ?>
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Min Amount</label>
                                            <input type="number" name="min_amount" class="form-control" 
                                                   value="<?= $method['min_amount']; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Max Amount</label>
                                            <input type="number" name="max_amount" class="form-control" 
                                                   value="<?= $method['max_amount']; ?>" min="0">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input" 
                                                   <?= $method['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button type="submit" class="btn-update">
                                            <i class="fa fa-save"></i> Update Method
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Tips:</h5>
                    <ul class="mb-0">
                        <li><strong>Fixed Amount:</strong> Fee tetap dalam Rupiah (contoh: 1500 = Rp 1,500)</li>
                        <li><strong>Percentage:</strong> Fee persentase dari total (contoh: 2.5 = 2.5%)</li>
                        <li><strong>Min/Max Amount:</strong> Batas minimum dan maksimum transaksi</li>
                        <li><strong>Active/Inactive:</strong> Kontrol metode mana yang ditampilkan ke customer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
