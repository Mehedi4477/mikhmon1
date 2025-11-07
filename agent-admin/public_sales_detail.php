<?php
/*
 * Public Sales Transaction Detail
 * AJAX endpoint for modal
 */

include_once('../include/db_config.php');

$id = $_GET['id'] ?? 0;

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT ps.*, a.agent_name, a.agent_code, a.phone as agent_phone
                           FROM public_sales ps
                           JOIN agents a ON ps.agent_id = a.id
                           WHERE ps.id = :id");
    $stmt->execute([':id' => $id]);
    $trx = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trx) {
        throw new Exception('Transaction not found');
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<table class="table table-bordered">
    <tr>
        <th width="30%">Transaction ID</th>
        <td><?= htmlspecialchars($trx['transaction_id']); ?></td>
    </tr>
    <tr>
        <th>Payment Reference</th>
        <td><?= htmlspecialchars($trx['payment_reference'] ?? '-'); ?></td>
    </tr>
    <tr>
        <th>Date</th>
        <td><?= date('d M Y H:i:s', strtotime($trx['created_at'])); ?></td>
    </tr>
    <tr>
        <th>Status</th>
        <td>
            <?php
            $badge_colors = [
                'pending' => 'warning',
                'paid' => 'success',
                'expired' => 'secondary',
                'failed' => 'danger'
            ];
            $badge_color = $badge_colors[$trx['status']] ?? 'secondary';
            ?>
            <span class="badge badge-<?= $badge_color; ?>"><?= ucfirst($trx['status']); ?></span>
        </td>
    </tr>
</table>

<h5>Customer Information</h5>
<table class="table table-bordered">
    <tr>
        <th width="30%">Name</th>
        <td><?= htmlspecialchars($trx['customer_name']); ?></td>
    </tr>
    <tr>
        <th>Phone</th>
        <td>
            <?= htmlspecialchars($trx['customer_phone']); ?>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $trx['customer_phone']); ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="fa fa-whatsapp"></i> WhatsApp
            </a>
        </td>
    </tr>
    <?php if ($trx['customer_email']): ?>
    <tr>
        <th>Email</th>
        <td><?= htmlspecialchars($trx['customer_email']); ?></td>
    </tr>
    <?php endif; ?>
</table>

<h5>Package & Payment</h5>
<table class="table table-bordered">
    <tr>
        <th width="30%">Agent</th>
        <td><?= htmlspecialchars($trx['agent_name']); ?> (<?= htmlspecialchars($trx['agent_code']); ?>)</td>
    </tr>
    <tr>
        <th>Package</th>
        <td><?= htmlspecialchars($trx['profile_name']); ?></td>
    </tr>
    <tr>
        <th>Price</th>
        <td>Rp <?= number_format($trx['price'], 0, ',', '.'); ?></td>
    </tr>
    <?php if ($trx['admin_fee'] > 0): ?>
    <tr>
        <th>Admin Fee</th>
        <td>Rp <?= number_format($trx['admin_fee'], 0, ',', '.'); ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th>Total Amount</th>
        <td><strong>Rp <?= number_format($trx['total_amount'], 0, ',', '.'); ?></strong></td>
    </tr>
    <tr>
        <th>Payment Gateway</th>
        <td><?= htmlspecialchars(strtoupper($trx['gateway_name'])); ?></td>
    </tr>
    <tr>
        <th>Payment Method</th>
        <td><?= htmlspecialchars($trx['payment_method'] ?? '-'); ?></td>
    </tr>
    <?php if ($trx['payment_channel']): ?>
    <tr>
        <th>Payment Channel</th>
        <td><?= htmlspecialchars($trx['payment_channel']); ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if ($trx['status'] == 'paid'): ?>
<h5>Payment Info</h5>
<table class="table table-bordered">
    <tr>
        <th width="30%">Paid At</th>
        <td><?= $trx['paid_at'] ? date('d M Y H:i:s', strtotime($trx['paid_at'])) : '-'; ?></td>
    </tr>
</table>
<?php endif; ?>

<?php if (!empty($trx['voucher_code'])): ?>
<h5>Voucher</h5>
<table class="table table-bordered">
    <tr>
        <th width="30%">Username</th>
        <td><strong><?= htmlspecialchars($trx['voucher_code']); ?></strong></td>
    </tr>
    <tr>
        <th>Password</th>
        <td><strong><?= htmlspecialchars($trx['voucher_password']); ?></strong></td>
    </tr>
    <tr>
        <th>Generated At</th>
        <td><?= $trx['voucher_generated_at'] ? date('d M Y H:i:s', strtotime($trx['voucher_generated_at'])) : '-'; ?></td>
    </tr>
    <tr>
        <th>Sent At</th>
        <td><?= $trx['voucher_sent_at'] ? date('d M Y H:i:s', strtotime($trx['voucher_sent_at'])) : 'Not sent'; ?></td>
    </tr>
</table>
<?php endif; ?>

<?php if ($trx['payment_url']): ?>
<div class="mt-3">
    <a href="<?= htmlspecialchars($trx['payment_url']); ?>" target="_blank" class="btn btn-primary">
        <i class="fa fa-external-link"></i> Open Payment Page
    </a>
</div>
<?php endif; ?>
