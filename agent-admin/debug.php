<?php
/*
 * Debug Agent Admin Pages
 */

echo "<h2>Debug Agent Admin</h2>";
echo "<pre>";

echo "=== SESSION INFO ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session mikhmon: " . (isset($_SESSION['mikhmon']) ? $_SESSION['mikhmon'] : 'NOT SET') . "\n";
echo "Session variable: " . (isset($session) ? $session : 'NOT SET') . "\n";
echo "\n";

echo "=== FILE PATHS ===\n";
echo "Current dir: " . __DIR__ . "\n";
echo "db_config.php exists: " . (file_exists('./include/db_config.php') ? 'YES' : 'NO') . "\n";
echo "Agent.class.php exists: " . (file_exists('./lib/Agent.class.php') ? 'YES' : 'NO') . "\n";
echo "\n";

echo "=== DATABASE CONNECTION ===\n";
try {
    include_once('./include/db_config.php');
    echo "db_config.php included: YES\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    
    $conn = getDBConnection();
    if ($conn) {
        echo "Connection: SUCCESS\n";
        
        // Check tables
        $tables = ['agents', 'agent_prices', 'agent_transactions'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            echo "Table $table: " . ($stmt->rowCount() > 0 ? 'EXISTS' : 'NOT FOUND') . "\n";
        }
    } else {
        echo "Connection: FAILED\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== AGENT CLASS ===\n";
try {
    include_once('./lib/Agent.class.php');
    echo "Agent.class.php included: YES\n";
    
    $agent = new Agent();
    echo "Agent instantiated: YES\n";
    
    $agents = $agent->getAllAgents();
    echo "Total agents: " . count($agents) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='./?hotspot=agent-list&session=$session'>Agent List</a></li>";
echo "<li><a href='./?hotspot=agent-add&session=$session'>Add Agent</a></li>";
echo "<li><a href='./?hotspot=agent-setup&session=$session'>Agent Setup</a></li>";
echo "</ul>";
?>
