<?php
/*
 * Admin Panel - Topup Saldo Agent
 */

// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agentId = $_GET['agent_id'] ?? 0;
$agentData = $agentId ? $agent->getAgentById($agentId) : null;

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');

$error = '';
$success = '';

if (isset($_POST['topup'])) {
    $agentId = $_POST['agent_id'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    
    if ($amount <= 0) {
        $error = 'Jumlah topup harus lebih dari 0!';
    } else {
        $result = $agent->topupBalance($agentId, $amount, $description, $_SESSION['mikhmon']);
        
        if ($result['success']) {
            $success = 'Topup berhasil! Saldo sebelum: Rp ' . number_format($result['balance_before'], 0, ',', '.') . 
                      ', Saldo sekarang: Rp ' . number_format($result['balance_after'], 0, ',', '.');
            $agentData = $agent->getAgentById($agentId);
        } else {
            $error = $result['message'];
        }
    }
}

$agents = $agent->getAllAgents('active');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    text-align: center;
    border-radius: 15px;
    margin-bottom: 20px;
}

.balance-card h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    opacity: 0.9;
}

.balance-card .amount {
    font-size: 36px;
    font-weight: bold;
    margin: 10px 0;
}

.quick-amounts {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.quick-amount {
    padding: 10px;
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    font-weight: 600;
}

.quick-amount:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-money"></i> Topup Saldo Agent</h3>
</div>
<div class="card-body">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success; ?></div>
    <?php endif; ?>

    <?php if ($agentData): ?>
    <div class="balance-card">
        <h3><?= htmlspecialchars($agentData['agent_name']); ?> (<?= htmlspecialchars($agentData['agent_code']); ?>)</h3>
        <div class="amount">Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></div>
        <p style="opacity: 0.9;">Saldo Saat Ini</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa fa-plus-circle"></i> Form Topup</h3>
        </div>
        <div class="card-body">
        <?php if ($agentData): ?>
        <form method="POST" id="topupForm">
            <input type="hidden" name="agent_id" value="<?= $agentId; ?>">
            
            <div class="form-group">
                <label>Agent</label>
                <select class="form-control" onchange="window.location.href='./?hotspot=agent-topup&agent_id=' + this.value + '&session=<?= $session; ?>'">
                    <option value="">-- Pilih Agent --</option>
                    <?php foreach ($agents as $agt): ?>
                    <option value="<?= $agt['id']; ?>" <?= $agentId == $agt['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>) - Saldo: Rp <?= number_format($agt['balance'], 0, ',', '.'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Jumlah Topup</label>
                <input type="number" name="amount" id="amount" class="form-control" placeholder="50000" min="1000" step="1000" required>
                <div class="quick-amounts">
                    <div class="quick-amount" onclick="setAmount(50000)">50K</div>
                    <div class="quick-amount" onclick="setAmount(100000)">100K</div>
                    <div class="quick-amount" onclick="setAmount(500000)">500K</div>
                    <div class="quick-amount" onclick="setAmount(1000000)">1JT</div>
                </div>
            </div>

            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Topup saldo via transfer BCA"></textarea>
            </div>

            <button type="submit" name="topup" class="btn btn-primary btn-block">
                <i class="fa fa-check"></i> Proses Topup
            </button>
            <br>
            <a href="./?hotspot=agent-list&session=<?= $session; ?>" class="btn btn-block">
                <i class="fa fa-arrow-left"></i> Kembali
            </a>
        </form>
        <?php else: ?>
        <div class="form-group">
            <label>Pilih Agent</label>
            <select class="form-control" onchange="window.location.href='./?hotspot=agent-topup&agent_id=' + this.value + '&session=<?= $session; ?>'">
                <option value="">-- Pilih Agent --</option>
                <?php foreach ($agents as $agt): ?>
                <option value="<?= $agt['id']; ?>">
                    <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>) - Saldo: Rp <?= number_format($agt['balance'], 0, ',', '.'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>
</div>

<script>
function setAmount(amount) {
    document.getElementById('amount').value = amount;
}
</script>
