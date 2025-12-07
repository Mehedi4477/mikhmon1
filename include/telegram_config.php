<?php
/*
 * Telegram Bot Integration for MikhMon
 * Telegram Bot API Configuration
 * 
 * DYNAMIC CONFIGURATION - Values loaded from database
 * No need to edit this file manually!
 */

// Load database connection
require_once(__DIR__ . '/db_config.php');

// Load Telegram settings from database
$telegram_config = [];
try {
    $db = getDBConnection();
    if ($db) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM telegram_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $telegram_config[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    error_log("Error loading Telegram config: " . $e->getMessage());
}

// Define constants from database values (with defaults)
define('TELEGRAM_BOT_TOKEN', $telegram_config['telegram_bot_token'] ?? '');
define('TELEGRAM_ENABLED', ($telegram_config['telegram_enabled'] ?? '0') == '1');
define('TELEGRAM_WEBHOOK_MODE', ($telegram_config['telegram_webhook_mode'] ?? '1') == '1');

// Telegram API URLs
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN);

// Webhook URL - sesuaikan dengan domain Anda
define('TELEGRAM_WEBHOOK_URL', 'https://yourdomain.com/api/telegram_webhook.php');

/**
 * Send message to Telegram
 * @param string|int $chatId Telegram chat ID
 * @param string $message Message text
 * @param string $parseMode Parse mode: 'Markdown', 'MarkdownV2', 'HTML', or null
 * @return array Response with success status
 */
if (!function_exists('sendTelegramMessage')) {
function sendTelegramMessage($chatId, $message, $parseMode = 'Markdown') {
    // Debug log
    $debugLog = __DIR__ . '/../logs/telegram_send_debug.log';
    $logDir = dirname($debugLog);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " | Attempting to send to $chatId: " . substr($message, 0, 50) . "...\n", FILE_APPEND);
    
    if (!TELEGRAM_ENABLED) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " | ERROR: Telegram disabled\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Telegram disabled'];
    }
    
    if (empty(TELEGRAM_BOT_TOKEN)) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " | ERROR: Bot token empty\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Telegram bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];
    
    // Add parse mode if specified
    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }
    
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " | Sending to URL: $url\n", FILE_APPEND);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " | HTTP Code: $httpCode | Response: $response\n", FILE_APPEND);
    
    if ($error) {
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " | cURL Error: $error\n", FILE_APPEND);
        return [
            'success' => false,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    $success = ($httpCode == 200 && isset($result['ok']) && $result['ok']);
    file_put_contents($debugLog, date('Y-m-d H:i:s') . " | Success: " . ($success ? 'YES' : 'NO') . "\n", FILE_APPEND);
    
    return [
        'success' => $success,
        'response' => $result,
        'message' => isset($result['description']) ? $result['description'] : 'Unknown error'
    ];
}
}

/**
 * Set Telegram webhook
 * @return array Response from Telegram API
 */
function setTelegramWebhook() {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        return ['ok' => false, 'description' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/setWebhook';
    $data = ['url' => TELEGRAM_WEBHOOK_URL];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Delete Telegram webhook
 * @return array Response from Telegram API
 */
function deleteTelegramWebhook() {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        return ['ok' => false, 'description' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/deleteWebhook';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get webhook info
 * @return array Webhook information
 */
function getTelegramWebhookInfo() {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        return ['ok' => false, 'description' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/getWebhookInfo';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get bot info
 * @return array Bot information
 */
function getTelegramBotInfo() {
    if (empty(TELEGRAM_BOT_TOKEN)) {
        return ['ok' => false, 'description' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/getMe';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Send message with inline keyboard
 * @param string|int $chatId Telegram chat ID
 * @param string $message Message text
 * @param array $keyboard Inline keyboard markup
 * @param string $parseMode Parse mode
 * @return array Response
 */
function sendTelegramMessageWithKeyboard($chatId, $message, $keyboard, $parseMode = 'Markdown') {
    if (!TELEGRAM_ENABLED) {
        return ['success' => false, 'message' => 'Telegram disabled'];
    }
    
    if (empty(TELEGRAM_BOT_TOKEN)) {
        return ['success' => false, 'message' => 'Telegram bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ];
    
    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return [
        'success' => ($httpCode == 200 && isset($result['ok']) && $result['ok']),
        'response' => $result,
        'message' => isset($result['description']) ? $result['description'] : 'Unknown error'
    ];
}

/**
 * Log Telegram transaction
 * @param string|int $chatId Chat ID
 * @param string $username Telegram username
 * @param string $command Command
 * @param string $status Status
 * @param string $response Response
 */
function logTelegramTransaction($chatId, $username, $command, $status, $response = '') {
    $logFile = __DIR__ . '/../logs/telegram_log.txt';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | Chat ID: $chatId | User: $username | Command: $command | Status: $status | Response: $response\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
