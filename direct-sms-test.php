<?php
/**
 * Direct SMS Test - Sends a real SMS to verify the API works
 * Usage: php direct-sms-test.php <phone_number>
 */

require_once 'includes/config/database.php';
require_once 'includes/sms.php';

// Get phone number from command line or use default
$phone = isset($argv[1]) ? $argv[1] : (isset($_GET['phone']) ? $_GET['phone'] : '09123456789');
$message = isset($argv[2]) ? $argv[2] : (isset($_GET['msg']) ? $_GET['msg'] : 'Test SMS from Health Connect. This is a verification message.');

echo "============================================\n";
echo "HEALTH CONNECT - DIRECT SMS TEST\n";
echo "============================================\n\n";

// Initialize database
$database = new Database();
$pdo = $database->getConnection();

// Check settings
echo "1. Checking SMS Settings...\n";
$stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'enable_sms_notifications'");
$stmt->execute();
$enabled = $stmt->fetchColumn();
echo "   SMS Notifications: " . ($enabled == '1' ? "ENABLED ✓" : "DISABLED ✗") . "\n";

$stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'sms_api_key'");
$stmt->execute();
$api_key = $stmt->fetchColumn();
echo "   API Key: " . (strlen($api_key) > 8 ? substr($api_key, 0, 8) . "..." : "NOT SET ✗") . "\n\n";

if ($enabled != '1') {
    echo "ERROR: SMS notifications are disabled. Enable them in admin settings.\n";
    exit(1);
}

if (empty($api_key)) {
    echo "ERROR: No API key configured.\n";
    exit(1);
}

// Test phone number formatting
echo "2. Phone Number Details...\n";
echo "   Original: $phone\n";

// Format phone number
$formatted_phone = str_replace([' ', '-', '(', ')', '.'], '', $phone);
if (substr($formatted_phone, 0, 2) === '09') {
    $formatted_phone = '63' . substr($formatted_phone, 1);
} elseif (substr($formatted_phone, 0, 1) === '0') {
    $formatted_phone = '63' . substr($formatted_phone, 1);
} elseif (substr($formatted_phone, 0, 3) === '+63') {
    $formatted_phone = substr($formatted_phone, 1);
}
echo "   Formatted: $formatted_phone\n\n";

// Test message formatting
echo "3. Message Details...\n";
echo "   Original: $message\n";
$formatted_message = formatSMSMessage($message);
echo "   Formatted: $formatted_message\n";
echo "   Length: " . strlen($formatted_message) . " characters\n\n";

// Only send if --send flag is provided
if (isset($argv[3]) && $argv[3] === '--send' || isset($_GET['send'])) {
    echo "4. Sending SMS...\n";
    $result = sendSMS($phone, $message);
    
    echo "\n   RESULT:\n";
    print_r($result);
    
    if ($result['success']) {
        echo "\n✓ SMS SENT SUCCESSFULLY!\n";
        
        // Check sms_logs table
        echo "\n5. Checking SMS Log...\n";
        $stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY sms_id DESC LIMIT 1");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($log) {
            echo "   Last SMS Log:\n";
            print_r($log);
        }
    } else {
        echo "\n✗ SMS FAILED: " . $result['message'] . "\n";
    }
} else {
    echo "4. Ready to Send...\n";
    echo "   To actually send the SMS, add --send flag:\n";
    echo "   php direct-sms-test.php $phone \"$message\" --send\n";
    echo "\n   OR via browser:\n";
    echo "   http://localhost/connect/direct-sms-test.php?phone=$phone&send=1\n";
}

echo "\n============================================\n";
echo "TEST COMPLETE\n";
echo "============================================\n";
?>
