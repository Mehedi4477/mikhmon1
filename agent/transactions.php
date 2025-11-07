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

// Get all transactions
$transactions = $agent->getTransactions($agentId, 100);

include_once('include_head.php');
include_once('include_nav.php');
?>

<style>
.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-topup {
    background: #d1fae5;
    color: #065f46;
}

.badge-generate {
    background: #fee2e2;
    color: #991b1b;
}

/* Mobile responsive table */
@media (max-width: 768px) {
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
        min-width: 600px !important;
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
    
    /* Make table more compact on mobile */
    .table-bordered {
        border-collapse: collapse !important;
    }
    
    .badge {
        padding: 3px 8px !important;
        font-size: 10px !important;
    }
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
    <div class="card-header">
        <h3><i class="fa fa-history"></i> Transaction History</h3>
        <div style="font-size: 14px;">Current Balance: <strong>Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></strong></div>
    </div>
    <div class="card-body" style="padding: 15px;">
        <?php if (!empty($transactions)): ?>
        <div class="table-responsive" style="overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; display: block !important; -ms-overflow-style: -ms-autohiding-scrollbar !important;">
        <table class="table table-bordered table-hover" style="width: 100% !important; min-width: 600px !important; margin-bottom: 0 !important; display: table !important;">
            <thead>
                <tr>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Date & Time</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Type</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Amount</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Balance Before</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Balance After</th>
                    <th style="padding: 8px 6px; font-size: 12px; white-space: nowrap;">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trx): ?>
                <tr>
                    <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;"><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                    <td style="padding: 8px 6px; font-size: 11px;">
                        <span class="badge badge-<?= $trx['transaction_type']; ?>">
                            <?= ucfirst($trx['transaction_type']); ?>
                        </span>
                    </td>
                    <td style="padding: 8px 6px; font-size: 11px; font-weight: bold; color: <?= $trx['transaction_type'] == 'topup' ? '#10b981' : '#ef4444'; ?>; white-space: nowrap;">
                        <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </td>
                    <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></td>
                    <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;">Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></td>
                    <td style="padding: 8px 6px; font-size: 11px; white-space: nowrap;"><?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No transactions found.
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<?php include_once('include_foot.php'); ?>
