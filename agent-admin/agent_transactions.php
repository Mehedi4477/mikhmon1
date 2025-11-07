<?php
// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agentId = $_GET['agent_id'] ?? 0;
$agentData = $agentId ? $agent->getAgentById($agentId) : null;
$transactions = $agentId ? $agent->getTransactions($agentId, 100) : [];
$agents = $agent->getAllAgents();

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
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
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-history"></i> Transaksi Agent</h3>
</div>
<div class="card-body">
    <div class="card">
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="hotspot" value="agent-transactions">
                <input type="hidden" name="session" value="<?= $session; ?>">
                <div class="form-group">
                    <label>Pilih Agent</label>
                    <select name="agent_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Agent --</option>
                        <?php foreach ($agents as $agt): ?>
                        <option value="<?= $agt['id']; ?>" <?= $agentId == $agt['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($agentData && !empty($transactions)): ?>
    <div class="card">
        <div class="card-header">
            <h3><?= htmlspecialchars($agentData['agent_name']); ?> - Saldo: Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></h3>
        </div>
        <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Saldo Sebelum</th>
                    <th>Saldo Sesudah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trx): ?>
                <tr>
                    <td><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                    <td><span class="badge badge-<?= $trx['transaction_type']; ?>"><?= ucfirst($trx['transaction_type']); ?></span></td>
                    <td style="font-weight: bold; color: <?= $trx['transaction_type'] == 'topup' ? '#10b981' : '#ef4444'; ?>">
                        <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </td>
                    <td>Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></td>
                    <td>Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></td>
                    <td><?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>
