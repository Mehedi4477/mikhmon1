<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if logged in
if (!isset($_SESSION['agent_id'])) {
    echo "Not logged in. Please login first.";
    exit();
}

include_once('../include/db_config.php');

try {
    $conn = getDBConnection();
    
    // Test query to check if database is accessible
    $stmt = $conn->query("SHOW TABLES LIKE 'agents'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Database connection successful!\n";
        
        // Test query to check if agent data exists
        $agentId = $_SESSION['agent_id'];
        $stmt = $conn->prepare("SELECT * FROM agents WHERE id = :id");
        $stmt->execute([':id' => $agentId]);
        $agent = $stmt->fetch();
        
        if ($agent) {
            echo "✅ Agent data found: " . $agent['agent_name'] . " (" . $agent['agent_code'] . ")\n";
            echo "✅ Current balance: Rp " . number_format($agent['balance'], 0, ',', '.') . "\n";
        } else {
            echo "❌ Agent data not found\n";
        }
    } else {
        echo "❌ Agent tables not found in database\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
?>