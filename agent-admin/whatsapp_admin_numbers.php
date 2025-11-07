<?php
/*
 * Admin Panel - WhatsApp Admin Numbers Management
 * Integrated with MikhMon Sidebar
 */

include_once('./include/db_config.php');

$error = '';
$success = '';

// Handle add number
if (isset($_POST['add_number'])) {
    $newNumber = trim($_POST['new_number']);
    
    // Validate format
    if (preg_match('/^62\d{9,13}$/', $newNumber)) {
        try {
            $db = getDBConnection();
            
            // Get current numbers
            $stmt = $db->query("SELECT setting_value FROM agent_settings WHERE setting_key = 'admin_whatsapp_numbers'");
            $result = $stmt->fetch();
            $currentNumbers = $result ? $result['setting_value'] : '';
            
            // Check if already exists
            $numbers = !empty($currentNumbers) ? explode(',', $currentNumbers) : [];
            $numbers = array_map('trim', $numbers);
            
            if (in_array($newNumber, $numbers)) {
                $error = 'Nomor sudah terdaftar!';
            } else {
                // Add new number
                $numbers[] = $newNumber;
                $numbersString = implode(',', $numbers);
                
                // Update database
                $stmt = $db->prepare("
                    INSERT INTO agent_settings (setting_key, setting_value, setting_type, description, updated_by) 
                    VALUES ('admin_whatsapp_numbers', ?, 'string', 'Admin Wha