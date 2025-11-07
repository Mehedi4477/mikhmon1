<?php
/*
 * Manual Voucher Generation
 * For failed auto-generation
 */

include_once('../include/db_config.php');
include_once('../lib/VoucherGenerator.class.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$sale_id = $_POST['sale_id'] ?? 0;

if (!$sale_id) {
    echo json_encode(['success' => false, 'message' => 'Sale ID required']);
    exit;
}

try {
    $generator = new VoucherGenerator();
    $result = $generator->generateAndSend($sale_id);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
