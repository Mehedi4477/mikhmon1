<?php
/*
 * Telegram Webhook Handler for MikhMon
 * Handle incoming Telegram messages for voucher purchase and management
 */

// Disable error display (log only)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/telegram_error.log');

// Load required files
require_once(__DIR__ . '/../include/db_config.php');
require_once(__DIR__ . '/../include/telegram_config.php');

// Load MikroTik helper functions
require_once(__DIR__ . '/telegram_mikrotik_helpers.php');
// Load Digiflazz helper functions
require_once(__DIR__ . '/telegram_digiflazz_helpers.php');
// Load WiFi helper functions
require_once(__DIR__ . '/telegram_wifi_helpers.php');

// Check if Telegram is enabled
if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) {
    http_response_code(200); // Return 200 to prevent Telegram retries
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
    
    // Handle other commands
    $messageLower = strtolower($message);
    
    // Remove leading slash from Telegram commands
    if (strpos($messageLower, '/') === 0) {
        $messageLower = substr($messageLower, 1);
    }
    
    // Check if admin
    $isAdmin = isTelegramAdmin($chatId);
    
    // Handle price list
    if (in_array($messageLower, ['harga', 'paket', 'list', 'price'])) {
        sendTelegramPriceList($chatId);
        return;
    }
    
    // Handle voucher command (admin only for now)
    if (strpos($messageLower, 'voucher ') === 0 || strpos($messageLower, 'vcr ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *VOUCHER <NAMA_PAKET>*\nContoh: *VOUCHER 3K*");
            return;
        }
        
        // Generate voucher
        purchaseTelegramVoucher($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Handle buy command
    if (strpos($messageLower, 'beli ') === 0 || strpos($messageLower, 'buy ') === 0) {
        // Extract profile name
        $parts = explode(' ', $messageLower, 2);
        $profileName = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (empty($profileName)) {
            sendTelegramMessage($chatId, "❌ Format salah!\n\nGunakan: *BELI <NAMA_PAKET>*\nContoh: *BELI 3K*");
            return;
        }
        
        // Generate voucher (same as VOUCHER command)
        purchaseTelegramVoucher($chatId, $profileName, $isAdmin);
        return;
    }
    
    // Admin-only commands
    if ($isAdmin) {
        // PING - Test MikroTik connection
        if (in_array($messageLower, ['ping', 'cek ping'])) {
            checkTelegramMikroTikPing($chatId);
            return;
        }
        
        // STATUS - Check MikroTik status
        if (in_array($messageLower, ['status', 'cek', 'cek status'])) {
            checkTelegramMikroTikStatus($chatId);
            return;
        }
        
        // RESOURCE - Check MikroTik resources
        if (in_array($messageLower, ['resource', 'res', 'resource mikrotik'])) {
            checkTelegramMikroTikResource($chatId);
            return;
        }
        
        // PPPOE - Check active PPPoE
        if (in_array($messageLower, ['pppoe', 'ppp', 'pppoe aktif', 'ppp aktif'])) {
            checkTelegramPPPoEActive($chatId);
            return;
        }
        
        // PPPOE OFFLINE - Check offline PPPoE
        if (in_array($messageLower, ['pppoe offline', 'ppp offline', 'pppoe mati', 'ppp mati'])) {
            checkTelegramPPPoEOffline($chatId);
            return;
        }
        
        // TAMBAH - Add PPPoE secret
        if (strpos($messageLower, 'tambah ') === 0) {
            $rest = trim(substr($message, 7)); // Remove "TAMBAH "
            $parts = preg_split('/\s+/', $rest, 3);
            
            if (count($parts) >= 3) {
                $username = $parts[0];
                $password = $parts[1];
                $profile = $parts[2];
                addTelegramPPPoESecret($chatId, $username, $password, $profile);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *TAMBAH <username> <password> <profile>*\nContoh: *TAMBAH user123 pass123 profile1*");
            }
            return;
        }
        
        // EDIT - Edit PPPoE profile
        if (strpos($messageLower, 'edit ') === 0) {
            $rest = trim(substr($message, 5)); // Remove "EDIT "
            $parts = preg_split('/\s+/', $rest, 2);
            
            if (count($parts) >= 2) {
                $username = $parts[0];
                $newProfile = $parts[1];
                editTelegramPPPoESecret($chatId, $username, $newProfile);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *EDIT <username> <profile_baru>*\nContoh: *EDIT user123 profile2*");
            }
            return;
        }
        
        // HAPUS - Delete PPPoE secret
        if (strpos($messageLower, 'hapus ') === 0) {
            $username = trim(substr($message, 6)); // Remove "HAPUS "
            
            if (!empty($username)) {
                deleteTelegramPPPoESecret($chatId, $username);
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat: *HAPUS <username>*\nContoh: *HAPUS user123*");
            }
            return;
        }
        
        // ENABLE/DISABLE commands
        if (strpos($messageLower, 'enable ') === 0 || strpos($messageLower, 'disable ') === 0) {
            $isEnable = strpos($messageLower, 'enable ') === 0;
            $rest = trim(substr($message, $isEnable ? 7 : 8)); // Remove "ENABLE " or "DISABLE "
            $parts = preg_split('/\s+/', $rest, 2);
            
            if (count($parts) >= 2) {
                $type = strtolower($parts[0]); // pppoe or hotspot
                $username = $parts[1];
                
                if ($type == 'pppoe' || $type == 'ppp') {
                    if ($isEnable) {
                        enableTelegramPPPoESecret($chatId, $username);
                    } else {
                        disableTelegramPPPoESecret($chatId, $username);
                    }
                } elseif ($type == 'hotspot' || $type == 'hs') {
                    if ($isEnable) {
                        enableTelegramHotspotUser($chatId, $username);
                    } else {
                        disableTelegramHotspotUser($chatId, $username);
                    }
                } else {
                    sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat:\n*ENABLE PPPOE <username>*\n*DISABLE PPPOE <username>*\n*ENABLE HOTSPOT <username>*\n*DISABLE HOTSPOT <username>*");
                }
            } else {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nFormat:\n*ENABLE PPPOE <username>*\n*DISABLE PPPOE <username>*\n*ENABLE HOTSPOT <username>*\n*DISABLE HOTSPOT <username>*");
            }
            return;
        }
        
        // SALDO DIGIFLAZZ
        if (in_array($messageLower, ['saldo digiflazz', 'cek saldo digiflazz', 'balance digiflazz'])) {
            checkTelegramDigiflazzBalance($chatId);
            return;
        }
        
        // PENCARIAN DEVICE
        if (strpos($messageLower, 'cariperangkat ') === 0 || strpos($messageLower, 'cari perangkat ') === 0) {
            $query = trim(str_replace(['cariperangkat', 'cari perangkat'], '', $messageLower));
            findTelegramDevice($chatId, $query);
            return;
        }

        // REGISTER AGENT/CUSTOMER
        if (strpos($messageLower, 'reg ') === 0 || strpos($messageLower, 'register ') === 0) {
            $phone = trim(str_replace(['reg ', 'register '], '', $messageLower));
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            if (empty($phone)) {
                sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nGunakan: `REG <NOMOR_HP>`\nContoh: `REG 08123456789`");
                return;
            }
            
            // Basic link logic (for prototype/ MVP parity)
            // Ideally we'd send OTP, but for now we'll match by phone directly if it exists in DB.
            $db = getDBConnection();
            if ($db) {
                // Check Agent
                $stmt = $db->prepare("SELECT id FROM agents WHERE phone LIKE ? OR phone LIKE ? LIMIT 1");
                $stmt->execute(['%'.$phone, '%'.$phone]); // Simple loose match
                $agent = $stmt->fetch();
                
                if ($agent) {
                    $upd = $db->prepare("UPDATE agents SET telegram_chat_id = ? WHERE id = ?");
                    $upd->execute([$chatId, $agent['id']]);
                    sendTelegramMessage($chatId, "✅ *REGISTRASI BERHASIL*\n\nAkun Telegram terhubung sebagai AGEN.");
                    return;
                }
                
                // Check Customer
                $stmt = $db->prepare("SELECT id FROM billing_customers WHERE phone LIKE ? OR phone LIKE ? LIMIT 1");
                $stmt->execute(['%'.$phone, '%'.$phone]);
                $cust = $stmt->fetch();
                
                if ($cust) {
                    $upd = $db->prepare("UPDATE billing_customers SET telegram_chat_id = ? WHERE id = ?");
                    $upd->execute([$chatId, $cust['id']]);
                    sendTelegramMessage($chatId, "✅ *REGISTRASI BERHASIL*\n\nAkun Telegram terhubung sebagai PELANGGAN.");
                    return;
                }
                
                sendTelegramMessage($chatId, "❌ *NOMOR TIDAK DITEMUKAN*\n\nNomor HP tidak terdaftar di sistem.");
            }
            return;
        }
    }
    
    // User commands - PULSA
    if (strpos($messageLower, 'pulsa ') === 0) {
        $parts = explode(' ', $message);
        $sku = $parts[1] ?? '';
        $number = $parts[2] ?? '';
        
        if (empty($sku) || empty($number)) {
             sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nGunakan: `PULSA <SKU> <NOMOR>`");
             return;
        }
        
        purchaseTelegramDigiflazz($chatId, $sku, $number);
        return;
    }
    
    // User commands - GANTI WIFI/SANDI
    if (strpos($messageLower, 'gantiwifi ') === 0) {
        $ssid = trim(substr($message, 10)); // Remove "GANTIWIFI "
        changeTelegramWiFiSSID($chatId, $ssid);
        return;
    }
    
    if (strpos($messageLower, 'gantisandi ') === 0) {
        $pass = trim(substr($message, 11)); // Remove "GANTISANDI "
        changeTelegramWiFiPassword($chatId, $pass);
        return;
    }
    
    // Default response for unknown commands
    sendTelegramMessage($chatId, "❓ Perintah tidak dikenali.\n\nKetik /help untuk melihat daftar perintah yang tersedia.");
}

/**
 * Process command using WhatsApp logic but send via Telegram
 * DEPRECATED - Not used anymore, kept for future integration
 */
function processTelegramCommandWithWhatsAppLogic($chatId, $message) {
    // This function is deprecated
    // Will be implemented later when full integration is needed
    sendTelegramMessage($chatId, "⚠️ Fitur ini sedang dalam pengembangan.");
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
        $message .= "*🎫 VOUCHER & PAKET*\n\n";
        $message .= "📋 *HARGA* - Lihat daftar paket\n";
        $message .= "🎫 *VOUCHER <PAKET>* - Generate voucher\n";
        $message .= "🛒 *BELI <PAKET>* - Generate voucher\n";
        $message .= "Contoh: `VOUCHER 3K`, `BELI 1JAM`\n\n";
        
        $message .= "*� MONITORING*\n\n";
        $message .= "🔌 *PING* - Test koneksi MikroTik\n";
        $message .= "📊 *STATUS* - Cek status MikroTik\n";
        $message .= "� *RESOURCE* - Cek resource server\n";
        $message .= "🌐 *PPPOE* - Cek PPPoE aktif\n";
        $message .= "📴 *PPPOE OFFLINE* - Cek PPPoE offline\n\n";
        
        $message .= "*⚙️ MANAGEMENT*\n\n";
        $message .= "➕ *TAMBAH <user> <pass> <profile>*\n";
        $message .= "✏️ *EDIT <user> <profile>*\n";
        $message .= "🗑️ *HAPUS <user>*\n";
        $message .= "✅ *ENABLE PPPOE <user>*\n";
        $message .= "❌ *DISABLE PPPOE <user>*\n";
        $message .= "✅ *ENABLE HOTSPOT <user>*\n";
        $message .= "❌ *DISABLE HOTSPOT <user>*\n\n";
        
        $message .= "*💰 DIGIFLAZZ*\n\n";
        $message .= "📱 *PULSA <SKU> <NOMER>*\n";
        $message .= "💵 *SALDO DIGIFLAZZ* - Cek saldo\n";
        $message .= "Contoh: `PULSA as10 081234567890`\n\n";
        
        $message .= "*🔐 WIFI*\n\n";
        $message .= "📡 *GANTIWIFI <SSID>*\n";
        $message .= "🔑 *GANTISANDI <PASSWORD>*\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🔑 *ADMIN ACCESS ACTIVE*\n";
        $message .= "❓ */help* - Tampilkan bantuan ini";
    } else {
        $message = "🤖 *BANTUAN BOT VOUCHER*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "*Perintah yang tersedia:*\n\n";
        $message .= "📋 *HARGA* atau *PAKET*\n";
        $message .= "Melihat daftar paket dan harga\n\n";
        $message .= "🛒 *BELI <NAMA_PAKET>*\n";
        $message .= "Membeli voucher\n";
        $message .= "Contoh: `BELI 1JAM`, `BELI 3K`\n\n";
        $message .= "� *PULSA <SKU> <NOMER>*\n";
        $message .= "Beli pulsa/data/e-money\n";
        $message .= "Contoh: `PULSA as10 081234567890`\n\n";
        $message .= "🔐 *GANTIWIFI <SSID>*\n";
        $message .= "Ganti nama WiFi\n";
        $message .= "Contoh: `GANTIWIFI MyWiFi`\n\n";
        $message .= "🔑 *GANTISANDI <PASSWORD>*\n";
        $message .= "Ganti password WiFi\n";
        $message .= "Contoh: `GANTISANDI password123`\n\n";
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

/**
 * Send price list from MikroTik
 */
function sendTelegramPriceList($chatId) {
    // Load session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ Sistem sedang maintenance.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session || !isset($sessionConfig[$session])) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak ditemukan.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    $hotspotname = explode('%', $data[4])[1] ?? $session;
    $currency = explode('&', $data[6])[1] ?? 'Rp';
    
    if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak lengkap.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ Gagal terhubung ke server.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get all profiles
    $profiles = $API->comm("/ip/hotspot/user/profile/print");
    $API->disconnect();
    
    $message = "*📋 DAFTAR PAKET WIFI*\n";
    $message .= "*$hotspotname*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $hasPackages = false;
    foreach ($profiles as $profile) {
        $name = $profile['name'];
        if ($name == 'default' || $name == 'default-encryption') continue;
        
        $ponlogin = $profile['on-login'] ?? '';
        if (empty($ponlogin)) continue;
        
        $parts = explode(",", $ponlogin);
        $validity = $parts[3] ?? '';
        $price = $parts[2] ?? '';
        $sprice = $parts[4] ?? '0';
        
        if (empty($sprice) || $sprice == '0') continue;
        
        if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
        } else {
            $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
        }
        
        $message .= "*$name*\n";
        $message .= "Validity: $validity\n";
        $message .= "Harga: $priceFormatted\n\n";
        $hasPackages = true;
    }
    
    if (!$hasPackages) {
        $message .= "Belum ada paket tersedia.\n\n";
    }
    
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Cara order:\n";
    $message .= "Ketik: *BELI <NAMA_PAKET>*\n";
    $message .= "Contoh: *BELI 1JAM*";
    
    sendTelegramMessage($chatId, $message);
}

/**
 * Purchase/Generate voucher for Telegram
 */
function purchaseTelegramVoucher($chatId, $profileName, $isAdmin) {
    // Load session config
    global $data;
    if (!isset($data) || empty($data)) {
        require_once(__DIR__ . '/../include/config.php');
    }
    $sessionConfig = isset($data) ? $data : array();
    
    if (empty($sessionConfig)) {
        sendTelegramMessage($chatId, "❌ Sistem sedang maintenance.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get first session
    $sessions = array_keys($sessionConfig);
    $session = null;
    foreach ($sessions as $s) {
        if ($s != 'mikhmon') {
            $session = $s;
            break;
        }
    }
    
    if (!$session || !isset($sessionConfig[$session])) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak ditemukan.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Load session config
    $data = $sessionConfig[$session];
    $iphost = explode('!', $data[1])[1] ?? '';
    $userhost = explode('@|@', $data[2])[1] ?? '';
    $passwdhost = explode('#|#', $data[3])[1] ?? '';
    $hotspotname = explode('%', $data[4])[1] ?? $session;
    $dnsname = explode('^', $data[5])[1] ?? $iphost;
    $currency = explode('&', $data[6])[1] ?? 'Rp';
    
    if (empty($iphost) || empty($userhost) || empty($passwdhost)) {
        sendTelegramMessage($chatId, "❌ Konfigurasi server tidak lengkap.\n\nSilakan hubungi admin.");
        return;
    }
    
    // Connect to MikroTik
    require_once(__DIR__ . '/../lib/routeros_api.class.php');
    
    $API = new RouterosAPI();
    $API->debug = false;
    
    if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
        sendTelegramMessage($chatId, "❌ Gagal terhubung ke server.\n\nSilakan coba lagi nanti.");
        return;
    }
    
    // Get profile
    $profiles = $API->comm("/ip/hotspot/user/profile/print", array(
        "?name" => $profileName
    ));
    
    if (empty($profiles)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ Profile *$profileName* tidak ditemukan.\n\nKetik *HARGA* untuk melihat daftar paket.");
        return;
    }
    
    $profile = $profiles[0];
    $ponlogin = $profile['on-login'] ?? '';
    
    if (empty($ponlogin)) {
        $API->disconnect();
        sendTelegramMessage($chatId, "❌ Profile *$profileName* tidak memiliki konfigurasi harga.");
        return;
    }
    
    $parts = explode(",", $ponlogin);
    $validity = $parts[3] ?? '';
    $price = $parts[2] ?? '0';
    $sprice = $parts[4] ?? '0';
    
    // Generate username and password based on settings
    $credentials = generateTelegramVoucherCredentials();
    $username = $credentials['username'];
    $password = $credentials['password'];
    
    // Add user to MikroTik
    $addResult = $API->comm("/ip/hotspot/user/add", array(
        "name" => $username,
        "password" => $password,
        "profile" => $profileName,
        "comment" => "vc-Telegram-" . date('Y-m-d H:i:s')
    ));
    
    $API->disconnect();
    
    if (empty($addResult) || isset($addResult['!trap'])) {
        $error = isset($addResult['!trap'][0]['message']) ? $addResult['!trap'][0]['message'] : 'Unknown error';
        sendTelegramMessage($chatId, "❌ Gagal generate voucher.\n\nError: $error");
        return;
    }
    
    // Format price
    if (strpos($currency, 'Rp') !== false || strpos($currency, 'IDR') !== false) {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 0, ",", ".");
    } else {
        $priceFormatted = $currency . " " . number_format((float)$sprice, 2);
    }
    
    // Send success message
    $message = "✅ *VOUCHER BERHASIL DI-GENERATE*\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "*Profile:* $profileName\n";
    $message .= "*Validity:* $validity\n";
    $message .= "*Harga:* $priceFormatted\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "*Username:* `$username`\n";
    $message .= "*Password:* `$password`\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "Login: http://$dnsname/login\n";
    $message .= "Hotspot: *$hotspotname*";
    
    sendTelegramMessage($chatId, $message);
    
    // Log to database if needed
    try {
        $db = getDBConnection();
        if ($db) {
            $stmt = $db->prepare("INSERT INTO telegram_webhook_log (chat_id, username, message, command, status, response) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$chatId, '', "VOUCHER $profileName", 'voucher', 'success', "Generated: $username"]);
        }
    } catch (Exception $e) {
        // Ignore logging errors
    }
}

// Return 200 OK to Telegram
http_response_code(200);
echo json_encode(['ok' => true]);
exit;
