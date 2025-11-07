<?php
/*
 * Agent Setup Page - Database Configuration & System Setup
 * URL: /?hotspot=agent-setup&session=SESSION_NAME
 */

session_start();

// Include required files
include_once('../include/db_config.php');

// Get session from URL
$session = $_GET['session'] ?? '';
if (empty($session)) {
    die('Session required');
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_database') {
        // Test database connection
        try {
            $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Test query
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $message = "✅ Database connection successful! Found " . count($tables) . " tables.";
        } catch (PDOException $e) {
            $error = "❌ Database connection failed: " . $e->getMessage();
        }
    }
    
    if ($action === 'create_tables') {
        // Create missing tables
        try {
            $conn = getDBConnection();
            
            // Read and execute safe_fix_tables.sql
            $sql_file = '../database/safe_fix_tables.sql';
            if (file_exists($sql_file)) {
                $sql_content = file_get_contents($sql_file);
                
                // Split by semicolon and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !str_starts_with($statement, '--')) {
                        $conn->exec($statement);
                    }
                }
                
                $message = "✅ Database tables created successfully!";
            } else {
                $error = "❌ SQL file not found: $sql_file";
            }
        } catch (Exception $e) {
            $error = "❌ Error creating tables: " . $e->getMessage();
        }
    }
    
    if ($action === 'check_agents') {
        // Check and create default agents
        try {
            $conn = getDBConnection();
            
            // Check existing agents
            $stmt = $conn->query("SELECT agent_code, agent_name, status FROM agents ORDER BY id");
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($agents)) {
                // Create default agents
                $default_agents = [
                    ['AG001', 'Agent Demo', 'active'],
                    ['AG5136', 'Tester Agent', 'active'],
                    ['PUBLIC', 'Public Catalog', 'active']
                ];
                
                $stmt = $conn->prepare("INSERT INTO agents (agent_code, agent_name, status) VALUES (?, ?, ?)");
                foreach ($default_agents as $agent) {
                    $stmt->execute($agent);
                }
                
                $message = "✅ Default agents created successfully!";
            } else {
                $message = "✅ Found " . count($agents) . " agents in database.";
            }
        } catch (Exception $e) {
            $error = "❌ Error checking agents: " . $e->getMessage();
        }
    }
    
    if ($action === 'setup_payment') {
        // Setup default payment methods
        try {
            $conn = getDBConnection();
            
            // Check existing payment methods
            $stmt = $conn->query("SELECT COUNT(*) FROM payment_methods WHERE is_active = 1");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                // Insert default payment methods
                $payment_methods = [
                    ['Tripay', 'tripay', 'Tripay Payment Gateway', 'fa-credit-card', 'percentage', 2.50, 1],
                    ['Bank Transfer', 'manual', 'Transfer Bank Manual', 'fa-university', 'fixed', 0.00, 1],
                    ['QRIS', 'qris', 'QRIS Payment', 'fa-qrcode', 'percentage', 0.70, 1]
                ];
                
                $stmt = $conn->prepare("INSERT INTO payment_methods (name, type, display_name, icon, admin_fee_type, admin_fee_value, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($payment_methods as $method) {
                    $stmt->execute($method);
                }
                
                $message = "✅ Default payment methods created successfully!";
            } else {
                $message = "✅ Found $count active payment methods.";
            }
        } catch (Exception $e) {
            $error = "❌ Error setting up payment methods: " . $e->getMessage();
        }
    }
}

// Get current database status
$db_status = [];
try {
    $conn = getDBConnection();
    
    // Check tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $db_status['tables'] = $tables;
    
    // Check agents
    if (in_array('agents', $tables)) {
        $stmt = $conn->query("SELECT COUNT(*) FROM agents");
        $db_status['agents_count'] = $stmt->fetchColumn();
    }
    
    // Check pricing
    if (in_array('agent_profile_pricing', $tables)) {
        $stmt = $conn->query("SELECT COUNT(*) FROM agent_profile_pricing");
        $db_status['pricing_count'] = $stmt->fetchColumn();
    }
    
    // Check payment methods
    if (in_array('payment_methods', $tables)) {
        $stmt = $conn->query("SELECT COUNT(*) FROM payment_methods WHERE is_active = 1");
        $db_status['payment_count'] = $stmt->fetchColumn();
    }
    
    // Check settings
    if (in_array('agent_settings', $tables)) {
        $stmt = $conn->query("SELECT COUNT(*) FROM agent_settings");
        $db_status['settings_count'] = $stmt->fetchColumn();
    }
    
} catch (Exception $e) {
    $db_status['error'] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Setup - Database Configuration</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .setup-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .setup-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .setup-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .status-success { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }
        .setup-section { padding: 20px; border-bottom: 1px solid #eee; }
        .setup-section:last-child { border-bottom: none; }
        .btn-setup { margin: 5px; }
        .info-box { background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        .table-status { font-size: 14px; }
        .config-display { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>

<div class="setup-container">
    <!-- Header -->
    <div class="setup-card">
        <div class="setup-header">
            <h1><i class="fa fa-cogs"></i> Agent Setup - Database Configuration</h1>
            <p class="mb-0">Complete database setup and system configuration for MikhMon Agent System</p>
            <small>Session: <strong><?= htmlspecialchars($session) ?></strong></small>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="success-box">
        <i class="fa fa-check-circle"></i> <?= $message ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="error-box">
        <i class="fa fa-exclamation-triangle"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- Database Status -->
    <div class="setup-card">
        <div class="setup-section">
            <h3><i class="fa fa-database"></i> Database Status</h3>
            
            <?php if (isset($db_status['error'])): ?>
            <div class="error-box">
                <strong>Database Connection Error:</strong><br>
                <?= htmlspecialchars($db_status['error']) ?>
            </div>
            <?php else: ?>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Current Configuration:</h5>
                    <div class="config-display">
                        <strong>Host:</strong> <?= DB_HOST ?><br>
                        <strong>Database:</strong> <?= DB_NAME ?><br>
                        <strong>User:</strong> <?= DB_USER ?><br>
                        <strong>Charset:</strong> <?= DB_CHARSET ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5>Tables Status:</h5>
                    <div class="table-status">
                        <?php
                        $required_tables = ['agents', 'agent_profile_pricing', 'agent_settings', 'public_sales', 'payment_methods'];
                        foreach ($required_tables as $table):
                            $exists = in_array($table, $db_status['tables'] ?? []);
                            $badge_class = $exists ? 'status-success' : 'status-danger';
                            $icon = $exists ? 'fa-check' : 'fa-times';
                        ?>
                        <div style="margin: 5px 0;">
                            <span class="status-badge <?= $badge_class ?>">
                                <i class="fa <?= $icon ?>"></i> <?= $table ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="info-box">
                        <strong>Agents:</strong><br>
                        <?= $db_status['agents_count'] ?? 0 ?> records
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <strong>Pricing:</strong><br>
                        <?= $db_status['pricing_count'] ?? 0 ?> profiles
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <strong>Payments:</strong><br>
                        <?= $db_status['payment_count'] ?? 0 ?> methods
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <strong>Settings:</strong><br>
                        <?= $db_status['settings_count'] ?? 0 ?> configs
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Setup Actions -->
    <div class="setup-card">
        <div class="setup-section">
            <h3><i class="fa fa-wrench"></i> Setup Actions</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Database Operations:</h5>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="test_database">
                        <button type="submit" class="btn btn-info btn-setup">
                            <i class="fa fa-plug"></i> Test Database Connection
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="create_tables">
                        <button type="submit" class="btn btn-primary btn-setup" 
                                onclick="return confirm('This will create missing database tables. Continue?')">
                            <i class="fa fa-table"></i> Create Missing Tables
                        </button>
                    </form>
                </div>
                
                <div class="col-md-6">
                    <h5>Data Setup:</h5>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="check_agents">
                        <button type="submit" class="btn btn-success btn-setup">
                            <i class="fa fa-users"></i> Setup Default Agents
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="setup_payment">
                        <button type="submit" class="btn btn-warning btn-setup">
                            <i class="fa fa-credit-card"></i> Setup Payment Methods
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="setup-card">
        <div class="setup-section">
            <h3><i class="fa fa-link"></i> Quick Access</h3>
            
            <div class="row">
                <div class="col-md-4">
                    <h5>Admin Panel:</h5>
                    <a href="../?session=<?= urlencode($session) ?>" class="btn btn-outline-primary btn-block">
                        <i class="fa fa-dashboard"></i> MikhMon Dashboard
                    </a>
                    <a href="../agent/pricing.php?session=<?= urlencode($session) ?>" class="btn btn-outline-info btn-block">
                        <i class="fa fa-money"></i> Pricing Management
                    </a>
                </div>
                
                <div class="col-md-4">
                    <h5>Public Pages:</h5>
                    <a href="../public/?agent=AG001" class="btn btn-outline-success btn-block" target="_blank">
                        <i class="fa fa-shopping-cart"></i> Public Sales Page
                    </a>
                    <a href="../public/profile_links.php?session=<?= urlencode($session) ?>" class="btn btn-outline-warning btn-block" target="_blank">
                        <i class="fa fa-link"></i> Profile Links Manager
                    </a>
                </div>
                
                <div class="col-md-4">
                    <h5>Documentation:</h5>
                    <a href="../INSTALLATION_GUIDE.md" class="btn btn-outline-secondary btn-block" target="_blank">
                        <i class="fa fa-book"></i> Installation Guide
                    </a>
                    <a href="../PROFILE_LINKS_INTEGRATION.md" class="btn btn-outline-secondary btn-block" target="_blank">
                        <i class="fa fa-file-text"></i> Integration Guide
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="setup-card">
        <div class="setup-section">
            <h3><i class="fa fa-info-circle"></i> System Information</h3>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Server Environment:</h5>
                    <div class="config-display">
                        <strong>PHP Version:</strong> <?= PHP_VERSION ?><br>
                        <strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?><br>
                        <strong>Document Root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?><br>
                        <strong>Current Time:</strong> <?= date('Y-m-d H:i:s') ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5>File Permissions:</h5>
                    <div class="config-display">
                        <?php
                        $files_to_check = [
                            '../include/db_config.php',
                            '../database/',
                            '../public/',
                            '../agent/'
                        ];
                        
                        foreach ($files_to_check as $file):
                            $exists = file_exists($file);
                            $readable = $exists ? is_readable($file) : false;
                            $writable = $exists ? is_writable($file) : false;
                            
                            echo "<strong>" . basename($file) . ":</strong> ";
                            if ($exists) {
                                echo "✅ Exists";
                                if ($readable) echo " | ✅ Readable";
                                if ($writable) echo " | ✅ Writable";
                            } else {
                                echo "❌ Not Found";
                            }
                            echo "<br>";
                        endforeach;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>

</body>
</html>
