<?php
/*
 * Test Telegram Configuration
 * Check if telegram_config.php loads correctly
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Telegram Config</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #0088cc; }
        .config-item { background: #f5f5f5; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h2>⚙️ Test Telegram Configuration</h2>
";

try {
    require_once('../include/telegram_config.php');
    
    echo "<h3>✅ telegram_config.php loaded successfully</h3>";
    
    echo "<div class='config-item'>";
    echo "<strong>TELEGRAM_BOT_TOKEN:</strong> ";
    if (defined('TELEGRAM_BOT_TOKEN')) {
        $token = TELEGRAM_BOT_TOKEN;
        if (!empty($token)) {
            echo "<span class='success'>✅ Configured</span><br>";
            echo "Value: " . substr($token, 0, 10) . "..." . substr($token, -10);
        } else {
            echo "<span class='error'>❌ Empty</span>";
        }
    } else {
        echo "<span class='error'>❌ Not defined</span>";
    }
    echo "</div>";
    
    echo "<div class='config-item'>";
    echo "<strong>TELEGRAM_ENABLED:</strong> ";
    if (defined('TELEGRAM_ENABLED')) {
        echo TELEGRAM_ENABLED ? "<span class='success'>✅ Enabled</span>" : "<span class='error'>❌ Disabled</span>";
    } else {
        echo "<span class='error'>❌ Not defined</span>";
    }
    echo "</div>";
    
    echo "<div class='config-item'>";
    echo "<strong>TELEGRAM_WEBHOOK_MODE:</strong> ";
    if (defined('TELEGRAM_WEBHOOK_MODE')) {
        echo TELEGRAM_WEBHOOK_MODE ? "<span class='success'>✅ Webhook</span>" : "Long Polling";
    } else {
        echo "<span class='error'>❌ Not defined</span>";
    }
    echo "</div>";
    
    echo "<div class='config-item'>";
    echo "<strong>TELEGRAM_API_URL:</strong> ";
    if (defined('TELEGRAM_API_URL')) {
        echo "<span class='success'>✅ " . htmlspecialchars(TELEGRAM_API_URL) . "</span>";
    } else {
        echo "<span class='error'>❌ Not defined</span>";
    }
    echo "</div>";
    
    // Test API connection
    if (defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN)) {
        echo "<h3>🔌 Test API Connection</h3>";
        
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getMe";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            if (isset($result['ok']) && $result['ok']) {
                $bot = $result['result'];
                echo "<div class='config-item'>";
                echo "<span class='success'>✅ API Connection Successful!</span><br>";
                echo "<strong>Bot Username:</strong> @" . $bot['username'] . "<br>";
                echo "<strong>Bot Name:</strong> " . $bot['first_name'] . "<br>";
                echo "<strong>Bot ID:</strong> " . $bot['id'];
                echo "</div>";
            } else {
                echo "<div class='config-item'>";
                echo "<span class='error'>❌ API Error: " . ($result['description'] ?? 'Unknown') . "</span>";
                echo "</div>";
            }
        } else {
            echo "<div class='config-item'>";
            echo "<span class='error'>❌ HTTP Error: $httpCode</span>";
            echo "</div>";
        }
    }
    
    // Check database settings
    echo "<h3>💾 Database Settings</h3>";
    require_once('../include/db_config.php');
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM telegram_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "<p class='error'>❌ No settings in database!</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Setting Key</th><th>Setting Value</th></tr>";
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            if ($setting['setting_key'] == 'telegram_bot_token' && !empty($value)) {
                $value = substr($value, 0, 10) . "..." . substr($value, -10);
            }
            echo "<tr>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td>" . htmlspecialchars($value) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='config-item'>";
    echo "<span class='error'>❌ Error loading config: " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='check_webhook_log.php'>📊 View Webhook Logs</a></p>";
echo "<p><a href='test_webhook_manual.php'>🧪 Test Webhook Manually</a></p>";

echo "</body></html>";
?>
