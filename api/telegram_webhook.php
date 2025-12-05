<?php
/*
 * Telegram Webhook Handler for MikhMon
 * Handle incoming Telegram messages for voucher purchase and management
 */

session_start();
error_reporting(0);

// Load required files
include('../include/config.php');
include('../include/telegram_config.php');

// Check if Telegram is enabled
if (!TELEGRAM_ENABLED) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'description' => 'Telegram bot is disabled']);
    exit;
}

// Get webhook data
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log incoming webhook
logTelegramWebhook($input);

// Process update
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $username = isset($message['from']['username']) ? $message['from']['username'] : '';
    $firstName = isset($message['from']['first_name']) ? $message['from']['first_name'] : '';
    $lastName = isset($message['from']['last_name']) ? $message['from']['last_name'] : '';
    $text = isset($message['text']) ? trim($message['text']) : '';
    
    if (!empty($text)) {
        // Process command
        processTelegramCommand($chatId, $text, $username, $firstName, $lastName);
    }
}

// Process callback queries (for inline keyboards)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    
    // Handle callback data
    handleCallbackQuery($chatId, $data, $callbackQuery['id']);
}

/**
 * Process Telegram command
 * Reuses WhatsApp command logic with Telegram-specific adaptations
 */
function processTelegramCommand($chatId, $message, $username = '', $firstName = '', $lastName = '') {
    // Log to database
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->prepare("INSERT INTO telegram_webhook_log (chat_id, username, first_name, last_name, message, command, status) VALUES (?, ?, ?, ?, ?, ?, 'success')");
            $command = explode(' ', $message)[0];
            $stmt->execute([$chatId, $username, $firstName, $lastName, $message, $command]);
        }
    } catch (Exception $e) {
        error_log("Error logging Telegram webhook: " . $e->getMessage());
    }
    
    // Handle Telegram-specific commands
    if (strpos($message, '/start') === 0) {
        sendTelegramWelcome($chatId, $firstName);
        return;
    }
    
    if (strpos($message, '/help') === 0) {
        sendTelegramHelp($chatId);
        return;
    }
    
    // Load WhatsApp webhook processor for command handling
    // This allows us to reuse all the existing command logic
    require_once('whatsapp_webhook_with_balance.php');
    
    // Convert Telegram message to WhatsApp format and process
    // Use chat_id as "phone" identifier
    $messageLower = strtolower($message);
    
    // Remove leading slash from Telegram commands
    if (strpos($messageLower, '/') === 0) {
        $messageLower = substr($messageLower, 1);
    }
    
    // Process command using WhatsApp processor
    // We'll create a wrapper to send responses via Telegram instead of WhatsApp
    processTelegramCommandWithWhatsAppLogic($chatId, $messageLower);
}

/**
 * Process command using WhatsApp logic but send via Telegram
 */
function processTelegramCommandWithWhatsAppLogic($chatId, $message) {
    // Temporarily override sendWhatsAppMessage function to send via Telegram
    global $telegram_chat_id_override;
    $telegram_chat_id_override = $chatId;
    
    // Process command (this will call sendWhatsAppMessage internally)
    processCommand($chatId, $message);
    
    // Reset override
    $telegram_chat_id_override = null;
}

/**
 * Send welcome message
 */
function sendTelegramWelcome($chatId, $firstName = '') {
    $name = !empty($firstName) ? $firstName : 'User';
    
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->query("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_welcome_message'");
            $result = $stmt->fetch();
            if ($result && !empty($result['setting_value'])) {
                $message = str_replace('{name}', $name, $result['setting_value']);
                sendTelegramMessage($chatId, $message);
                return;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting welcome message: " . $e->getMessage());
    }
    
    // Default welcome message
    $message = "🤖 *Selamat datang di Bot MikhMon, $name!*\n\n";
    $message .= "Saya adalah bot untuk pembelian voucher WiFi dan layanan digital.\n\n";
    $message .= "Ketik /help untuk melihat perintah yang tersedia.";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Send help message
 */
function sendTelegramHelp($chatId) {
    // Check if admin
    $isAdmin = isTelegramAdmin($chatId);
    
    if ($isAdmin) {
        $message = "👑 *BANTUAN ADMIN BOT*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*Perintah Admin:*\n\n";
        $message .= "🎫 *VOUCHER <PROFILE>*\n";
        $message .= "Generate voucher (tanpa potong saldo)\n";
        $message .= "Contoh: VOUCHER 3K\n\n";
        $message .= "💰 *PULSA <SKU> <NOMER>*\n";
        $message .= "Beli produk Digiflazz\n";
        $message .= "Contoh: PULSA as10 081234567890\n\n";
        $message .= "📋 *HARGA*\n";
        $message .= "Lihat daftar harga\n\n";
        $message .= "🔧 *STATUS*\n";
        $message .= "Cek status MikroTik\n\n";
        $message .= "📊 *PPPOE*\n";
        $message .= "Cek PPPoE aktif\n\n";
        $message .= "❓ */help*\n";
        $message .= "Tampilkan bantuan ini\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🔑 *ADMIN ACCESS ACTIVE*";
    } else {
        $message = "🤖 *BANTUAN BOT VOUCHER*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*Perintah yang tersedia:*\n\n";
        $message .= "📋 *HARGA* atau *PAKET*\n";
        $message .= "Melihat daftar paket dan harga\n\n";
        $message .= "🛒 *BELI <NAMA_PAKET>*\n";
        $message .= "Membeli voucher\n";
        $message .= "Contoh: BELI 1JAM\n\n";
        $message .= "💳 *PULSA <SKU> <NOMER>*\n";
        $message .= "Beli pulsa/data/e-money\n";
        $message .= "Contoh: PULSA as10 081234567890\n\n";
        $message .= "🔐 *GANTI WIFI <SSID>*\n";
        $message .= "Ganti nama WiFi\n\n";
        $message .= "🔑 *GANTI SANDI <PASSWORD>*\n";
        $message .= "Ganti password WiFi\n\n";
        $message .= "❓ */help*\n";
        $message .= "Menampilkan bantuan ini\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "_Hubungi admin jika ada kendala_";
    }
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Check if Telegram chat ID is admin
 */
function isTelegramAdmin($chatId) {
    try {
        $db = getDBConnection();
        if (!$db) {
            return false;
        }
        
        $stmt = $db->query("SELECT setting_value FROM telegram_settings WHERE setting_key = 'telegram_admin_chat_ids'");
        $result = $stmt->fetch();
        
        if ($result) {
            $adminChatIds = explode(',', $result['setting_value']);
            $adminChatIds = array_map('trim', $adminChatIds);
            return in_array($chatId, $adminChatIds);
        }
    } catch (Exception $e) {
        error_log("Error checking Telegram admin: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Handle callback query from inline keyboards
 */
function handleCallbackQuery($chatId, $data, $callbackQueryId) {
    // Answer callback query to remove loading state
    $url = TELEGRAM_API_URL . '/answerCallbackQuery';
    $postData = ['callback_query_id' => $callbackQueryId];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
    
    // Process callback data
    // Format: action:param1:param2
    $parts = explode(':', $data);
    $action = $parts[0];
    
    switch ($action) {
        case 'buy':
            if (isset($parts[1])) {
                $profile = $parts[1];
                processTelegramCommandWithWhatsAppLogic($chatId, "beli $profile");
            }
            break;
        case 'price':
            processTelegramCommandWithWhatsAppLogic($chatId, "harga");
            break;
        case 'help':
            sendTelegramHelp($chatId);
            break;
    }
}

/**
 * Log Telegram webhook
 */
function logTelegramWebhook($data) {
    $logFile = __DIR__ . '/../logs/telegram_webhook.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | " . $data . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Override sendWhatsAppMessage to send via Telegram when processing Telegram commands
if (!function_exists('sendWhatsAppMessage_original')) {
    function sendWhatsAppMessage_original($phone, $message) {
        return sendWhatsAppMessage($phone, $message);
    }
}

// Check if we're processing a Telegram command
global $telegram_chat_id_override;
if (isset($telegram_chat_id_override) && !empty($telegram_chat_id_override)) {
    // Override sendWhatsAppMessage function
    function sendWhatsAppMessage($phone, $message) {
        global $telegram_chat_id_override;
        if ($telegram_chat_id_override) {
            return sendTelegramMessage($telegram_chat_id_override, $message);
        }
        return sendWhatsAppMessage_original($phone, $message);
    }
}
