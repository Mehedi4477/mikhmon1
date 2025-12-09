<?php
/*
 * Test Telegram Webhook Manually
 * Simulate Telegram sending a message to webhook
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Telegram Webhook</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #0088cc; }
        .result { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
    </style>
</head>
<body>
    <h2>🧪 Test Telegram Webhook</h2>
";

// Test data - simulate Telegram message
$testData = [
    'update_id' => 123456789,
    'message' => [
        'message_id' => 1,
        'from' => [
            'id' => 567858628,
            'is_bot' => false,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser'
        ],
        'chat' => [
            'id' => 567858628,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'type' => 'private'
        ],
        'date' => time(),
        'text' => '/start'
    ]
];

echo "<h3>📤 Sending Test Request</h3>";
echo "<p>Simulating Telegram sending: <strong>/start</strong></p>";
echo "<p>To webhook: <code>https://" . $_SERVER['HTTP_HOST'] . "/api/telegram_webhook.php</code></p>";

// Send POST request to webhook
$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/telegram_webhook.php';

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h3>📥 Response from Webhook</h3>";

if ($curlError) {
    echo "<div class='result error'>";
    echo "<strong>❌ cURL Error:</strong><br>";
    echo htmlspecialchars($curlError);
    echo "</div>";
} else {
    $resultClass = ($httpCode == 200) ? 'success' : 'error';
    echo "<div class='result $resultClass'>";
    echo "<strong>HTTP Status Code:</strong> $httpCode<br>";
    
    if ($httpCode == 200) {
        echo "✅ Webhook responded successfully!";
    } else {
        echo "❌ Webhook returned error status";
    }
    echo "</div>";
    
    echo "<h4>Response Body:</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode JSON
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo "<h4>Decoded JSON:</h4>";
        echo "<pre>" . print_r($jsonResponse, true) . "</pre>";
    }
}

// Check if message was logged
echo "<h3>📊 Check Database Log</h3>";

try {
    require_once('../include/db_config.php');
    $db = getDBConnection();
    
    $stmt = $db->query("SELECT * FROM telegram_webhook_log WHERE chat_id = '567858628' ORDER BY created_at DESC LIMIT 1");
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($log) {
        echo "<div class='result success'>";
        echo "✅ Message was logged to database!<br>";
        echo "<strong>Time:</strong> {$log['created_at']}<br>";
        echo "<strong>Message:</strong> " . htmlspecialchars($log['message']) . "<br>";
        echo "<strong>Status:</strong> {$log['status']}";
        echo "</div>";
    } else {
        echo "<div class='result error'>";
        echo "❌ Message was NOT logged to database<br>";
        echo "Webhook mungkin error sebelum logging";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='result error'>";
    echo "❌ Database Error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='check_webhook_log.php'>📊 View All Webhook Logs</a></p>";
echo "<p><a href='test_telegram_config.php'>⚙️ Test Telegram Config</a></p>";

echo "</body></html>";
?>
