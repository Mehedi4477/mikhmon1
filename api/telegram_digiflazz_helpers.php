<?php
/**
 * Telegram Helper Functions for Digiflazz Integration
 */

// Include Digiflazz and Agent classes if not already included
require_once __DIR__ . '/../lib/DigiflazzClient.class.php';
require_once __DIR__ . '/../lib/Agent.class.php';

/**
 * Check Digiflazz Balance (Admin Only)
 */
function checkTelegramDigiflazzBalance($chatId) {
    if (!isTelegramAdmin($chatId)) {
        sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nPerintah ini hanya untuk admin.");
        return;
    }

    try {
        $digiflazz = new DigiflazzClient();
        
        if (!$digiflazz->isEnabled()) {
            sendTelegramMessage($chatId, "❌ *DIGIFLAZZ OFF*\n\nIntegrasi Digiflazz belum aktif.");
            return;
        }

        $balanceData = $digiflazz->checkBalance();

        if (!$balanceData['success']) {
            sendTelegramMessage($chatId, "❌ *GAGAL*\n\nGagal cek saldo Digiflazz.");
            return;
        }

        $balance = number_format($balanceData['balance'], 0, ',', '.');
        $msg = "💰 *SALDO DIGIFLAZZ*\n\n";
        $msg .= "Rp $balance\n\n";
        $msg .= "📅 " . date('d/m/Y H:i');

        sendTelegramMessage($chatId, $msg);

    } catch (Exception $e) {
        sendTelegramMessage($chatId, "❌ *ERROR*\n\n" . $e->getMessage());
    }
}

/**
 * Purchase Digiflazz Product via Telegram
 */
function purchaseTelegramDigiflazz($chatId, $sku, $customerNo) {
    global $db;
    
    // Validate SKU and Number
    if (empty($sku) || empty($customerNo)) {
        sendTelegramMessage($chatId, "❌ *FORMAT SALAH*\n\nGunakan: `PULSA <SKU> <NOMOR>`");
        return;
    }

    // Initialize Digiflazz
    $digiflazz = new DigiflazzClient();
    if (!$digiflazz->isEnabled()) {
        sendTelegramMessage($chatId, "❌ *DIGIFLAZZ OFF*\n\nLayanan sedang tidak aktif.");
        return;
    }

    // Get Product Info
    $product = getDigiflazzProductBySKU($sku);
    if (!$product) {
        sendTelegramMessage($chatId, "❌ *PRODUK TIDAK DITEMUKAN*\n\nSKU `$sku` tidak valid.");
        return;
    }

    // Check if Admin
    $isAdmin = isTelegramAdmin($chatId);
    $agent = null;
    $agentId = null;

    if (!$isAdmin) {
        // Find Agent by Telegram Chat ID
        $agent = getAgentByTelegramChatId($chatId);
        
        if (!$agent) {
             sendTelegramMessage($chatId, "❌ *AKSES DITOLAK*\n\nAkun Telegram Anda belum terhubung ke akun Agen.\nHubungi Admin.");
             return;
        }
        
        $agentId = $agent['id'];
        if ($agent['status'] !== 'active') {
             sendTelegramMessage($chatId, "❌ *AKUN DIGANTUNG*\n\nAkun Agen Anda tidak aktif.");
             return;
        }
    }

    // Calculate Price
    $digiflazzSettings = $digiflazz->getSettings();
    $defaultMarkup = isset($digiflazzSettings['default_markup_nominal']) ? (int)$digiflazzSettings['default_markup_nominal'] : 0;
    
    $basePrice = (int)$product['price'];
    if ($basePrice <= 0 && isset($product['buyer_price'])) {
        $basePrice = (int)$product['buyer_price'];
    }
    
    $sellPrice = $basePrice;
    
    // Apply markup for agent
    if (!$isAdmin) {
        if (!empty($product['seller_price']) && (int)$product['seller_price'] > 0) {
            $sellPrice = (int)$product['seller_price'];
        } elseif ($defaultMarkup > 0) {
            $sellPrice = $basePrice + $defaultMarkup;
        }
        
        // Ensure sell price is at least base price
        if ($sellPrice < $basePrice) {
            $sellPrice = $basePrice;
        }
        
        // Check Balance
        if ($agent['balance'] < $sellPrice) {
             sendTelegramMessage($chatId, "❌ *SALDO TIDAK CUKUP*\n\nButuh: Rp " . number_format($sellPrice, 0, ',', '.') . "\nSaldo: Rp " . number_format($agent['balance'], 0, ',', '.'));
             return;
        }
    }

    // Process Transaction
    $refIdPrefix = $isAdmin ? 'DFADM' : 'DFAG' . ($agent['agent_code'] ?? $agentId);
    $refId = $digiflazz->generateRefId($refIdPrefix);

    // Send Processing Message
    sendTelegramMessage($chatId, "⏳ *MEMPROSES HANTARAN*\n\nProduk: " . $product['product_name'] . "\nNomor: $customerNo", 'Markdown');

    // Call API
    $payload = [
        'buyer_sku_code' => $product['buyer_sku_code'],
        'customer_no' => $customerNo,
        'ref_id' => $refId
    ];
    
    try {
        $response = $digiflazz->createTransactionWithRetry($payload);
        
        // Handle Response
        $data = isset($response['data']) ? $response['data'] : $response;
        $status = strtolower($data['status'] ?? 'pending');
        $finalRefId = $data['ref_id'] ?? $refId;
        $sn = $data['sn'] ?? '';
        $msg = $data['message'] ?? '';
        
        // Check Failure
        $failStatuses = ['failed', 'fail', 'gagal', 'error', 'refund'];
        if (in_array($status, $failStatuses)) {
             sendTelegramMessage($chatId, "❌ *TRANSAKSI GAGAL*\n\n$msg");
             return;
        }

        // Deduct Balance (if not admin and not failed)
        $balanceAfter = 0;
        if (!$isAdmin) {
            $agentClass = new Agent();
            $deductResult = $agentClass->deductBalance(
                $agentId,
                $sellPrice,
                $product['product_name'],
                $finalRefId,
                'Telegram Digiflazz: ' . $product['product_name'],
                'digiflazz'
            );
            
            if ($deductResult['success']) {
                $balanceAfter = $deductResult['balance_after'];
            }
        }
        
        // Save Transaction to DB (Simplified for brevity, similar to WA)
        // ... (Skipping verbose DB insert here, assuming Agent class or Digiflazz helper might handle logging or we accept minimal logging for now to fit context)
        // Ideally we replicate the insert from WA webhook. Let's do a quick insert.
        
        if (function_exists('getDBConnection')) {
             $db = getDBConnection();
             $stmt = $db->prepare("INSERT INTO digiflazz_transactions (agent_id, ref_id, buyer_sku_code, customer_no, status, message, price, sell_price, serial_number, response, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
             $stmt->execute([
                 $isAdmin ? 1 : $agentId,
                 $finalRefId,
                 $product['buyer_sku_code'],
                 $customerNo,
                 $status,
                 $msg,
                 $basePrice,
                 $sellPrice,
                 $sn,
                 json_encode($response)
             ]);
        }

        // Send Success/Pending Message
        $reply = "✅ *TRANSAKSI BERHASIL*\n\n";
        if ($status == 'pending') $reply = "⏳ *TRANSAKSI DIPROSES*\n\n";
        
        $reply .= "Produk: " . $product['product_name'] . "\n";
        $reply .= "Nomor: $customerNo\n";
        $reply .= "SN: $sn\n";
        if (!$isAdmin) {
            $reply .= "Harga: Rp " . number_format($sellPrice, 0, ',', '.') . "\n";
            $reply .= "Sisa Saldo: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n";
        }
        $reply .= "\nRef: $finalRefId";
        
        sendTelegramMessage($chatId, $reply);

    } catch (Exception $e) {
        sendTelegramMessage($chatId, "❌ *ERROR SISTEM*\n\n" . $e->getMessage());
    }
}

/**
 * Get Agent by Telegram Chat ID
 */
function getAgentByTelegramChatId($chatId) {
    $db = getDBConnection();
    if (!$db) return null;
    
    $stmt = $db->prepare("SELECT * FROM agents WHERE telegram_chat_id = ? LIMIT 1");
    $stmt->execute([$chatId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Helper to get Product by SKU (copied from WA webhook logic for reuse)
 */
function getDigiflazzProductBySKU($sku) {
    $db = getDBConnection();
    if (!$db) return null;
    
    $stmt = $db->prepare("SELECT * FROM digiflazz_products WHERE LOWER(buyer_sku_code) = LOWER(?) AND status = 'active' LIMIT 1");
    $stmt->execute([$sku]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

?>
