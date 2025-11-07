<?php
/*
 * Payment Methods Management
 * Manage payment methods and fees for payment gateway
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');

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
                
            case 'bulk_update':
                // Quick update for common scenarios
                $updates = $_POST['updates'] ?? [];
                $success_count = 0;
                
                foreach ($updates as $id => $data) {
                    if (!empty($data['admin_fee_value'])) {
                        try {
                            $stmt = $conn->prepare("UPDATE payment_methods SET admin_fee_value = ? WHERE id = ?");
                            $stmt->execute([$data['admin_fee_value'], $id]);
                            $success_count++;
                        } catch (Exception $e) {
                            // Continue with other updates
                        }
                    }
                }
                
                $message = "Updated $success_count payment methods successfully!";
                $messageType = 'success';
                break;
        }
    }
}

// Get all payment methods
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY gateway_name, sort_order, id");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by type for easier management
$grouped_methods = [];
foreach ($payment_methods as $method) {
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
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-money"></i> Payment Methods Management</h3>
            </div>
            <div class="card-body">
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <i class="fa fa-<?= $messageType === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                    <?= htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> Information</h5>
                    <p><strong>Admin Fee</strong> adalah biaya tambahan yang dikenakan kepada customer di atas fee payment gateway.</p>
                    <ul class="mb-0">
                        <li><strong>Fixed:</strong> Fee tetap dalam Rupiah (contoh: 1500 = Rp 1,500)</li>
                        <li><strong>Percentage:</strong> Fee persentase dari total transaksi (contoh: 2.5 = 2.5%)</li>
                        <li><strong>Fee Payment Gateway</strong> sudah diatur di dashboard payment gateway (Tripay/Xendit/Midtrans)</li>
                    </ul>
                </div>
                
                <!-- Quick Actions -->
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <p>Update fee untuk metode pembayaran yang umum digunakan:</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="bulk_update">
                            <div class="row">
                                <?php foreach ($payment_methods as $method): ?>
                                    <?php if ($method['method_code'] === 'QRIS'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">QRIS Admin Fee (Rp):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="1500" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: Rp <?= number_format($method['admin_fee_value']); ?></small>
                                    </div>
                                    <?php elseif ($method['method_code'] === 'BRIVA'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">BRI VA Admin Fee (Rp):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="2500" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: Rp <?= number_format($method['admin_fee_value']); ?></small>
                                    </div>
                                    <?php elseif ($method['method_code'] === 'OVO'): ?>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">OVO Admin Fee (%):</label>
                                        <input type="number" name="updates[<?= $method['id']; ?>][admin_fee_value]" 
                                               class="form-control" placeholder="2.0" step="0.1" value="<?= $method['admin_fee_value']; ?>">
                                        <small class="text-muted">Current: <?= $method['admin_fee_value']; ?>%</small>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa fa-flash"></i> Quick Update
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Payment Methods by Category -->
                <?php foreach ($grouped_methods as $type => $methods): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa <?= $type_icons[$type] ?? 'fa-credit-card'; ?>"></i>
                            <?= $type_labels[$type] ?? ucfirst($type); ?> Methods
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Current Fee</th>
                                        <th>Fee Type</th>
                                        <th>Fee Value</th>
                                        <th>Min Amount</th>
                                        <th>Max Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($methods as $method): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="method-icon mr-2" style="width: 30px; height: 30px; background: #3c8dbc; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa <?= $method['icon'] ?? 'fa-credit-card'; ?>" style="font-size: 14px;"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($method['method_name']); ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($method['method_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= $method['admin_fee_type'] === 'percentage' ? $method['admin_fee_value'] . '%' : 'Rp ' . number_format($method['admin_fee_value']); ?>
                                            </span>
                                        </td>
                                        <td colspan="6">
                                            <form method="POST" class="form-inline">
                                                <input type="hidden" name="action" value="update_method">
                                                <input type="hidden" name="id" value="<?= $method['id']; ?>">
                                                
                                                <select name="admin_fee_type" class="form-control form-control-sm mr-2 mb-2">
                                                    <option value="fixed" <?= $method['admin_fee_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed (Rp)</option>
                                                    <option value="percentage" <?= $method['admin_fee_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                                </select>
                                                
                                                <input type="number" name="admin_fee_value" class="form-control form-control-sm mr-2 mb-2" 
                                                       value="<?= $method['admin_fee_value']; ?>" step="0.01" min="0" placeholder="Fee Value" style="width: 100px;">
                                                
                                                <input type="number" name="min_amount" class="form-control form-control-sm mr-2 mb-2" 
                                                       value="<?= $method['min_amount']; ?>" placeholder="Min" style="width: 80px;">
                                                
                                                <input type="number" name="max_amount" class="form-control form-control-sm mr-2 mb-2" 
                                                       value="<?= $method['max_amount']; ?>" placeholder="Max" style="width: 100px;">
                                                
                                                <div class="form-check form-check-inline mr-2 mb-2">
                                                    <input type="checkbox" name="is_active" class="form-check-input" 
                                                           <?= $method['is_active'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-success btn-sm mb-2">
                                                    <i class="fa fa-save"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Summary -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-info-circle"></i> Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fa fa-credit-card"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Methods</span>
                                        <span class="info-box-number"><?= count($payment_methods); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fa fa-check"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Active Methods</span>
                                        <span class="info-box-number"><?= count(array_filter($payment_methods, function($m) { return $m['is_active']; })); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-warning"><i class="fa fa-qrcode"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">QRIS Methods</span>
                                        <span class="info-box-number"><?= count($grouped_methods['qris'] ?? []); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-primary"><i class="fa fa-bank"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">VA Methods</span>
                                        <span class="info-box-number"><?= count($grouped_methods['va'] ?? []); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h5><i class="fa fa-exclamation-triangle"></i> Important Notes:</h5>
                            <ul class="mb-0">
                                <li><strong>Admin Fee</strong> adalah keuntungan untuk admin/agen, bukan fee payment gateway</li>
                                <li><strong>Fee Payment Gateway</strong> (seperti fee Tripay) sudah diatur di dashboard payment gateway</li>
                                <li><strong>Total yang dibayar customer</strong> = Harga Voucher + Admin Fee + Fee Payment Gateway</li>
                                <li>Jika tidak ingin keuntungan tambahan, set Admin Fee = 0</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<style>
.method-icon {
    flex-shrink: 0;
}

.form-inline .form-control {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .form-inline {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-inline .form-control,
    .form-inline .form-check,
    .form-inline .btn {
        margin-right: 0 !important;
        margin-bottom: 10px;
        width: 100%;
    }
}
</style>
