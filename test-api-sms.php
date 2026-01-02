<?php
/**
 * API SMS Test Script
 * Tests the API endpoints directly
 */

require_once 'includes/config/database.php';
require_once 'includes/sms.php';

echo "===========================================\n";
echo "API SMS TEST\n";
echo "===========================================\n\n";

$db = new Database();
$pdo = $db->getConnection();

// Check SMS enabled setting
$stmt = $pdo->query("SELECT value FROM settings WHERE name = 'enable_sms_notifications'");
$enabled = $stmt->fetchColumn();
echo "1. SMS Notifications Enabled: " . ($enabled == '1' ? "YES ✓" : "NO ✗") . "\n";

// Check API key
$stmt = $pdo->query("SELECT value FROM settings WHERE name = 'sms_api_key'");
$api_key = $stmt->fetchColumn();
echo "2. API Key Set: " . (!empty($api_key) ? "YES ✓ (" . substr($api_key, 0, 8) . "...)" : "NO ✗") . "\n";

// Check if appointments exist
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
$apt_count = $stmt->fetchColumn();
echo "3. Total Appointments: $apt_count\n";

// Check appointments with patients that have phone numbers
$stmt = $pdo->query("SELECT a.appointment_id, u.first_name, u.last_name, u.mobile_number, s.status_name 
                     FROM appointments a 
                     JOIN patients p ON a.patient_id = p.patient_id 
                     JOIN users u ON p.user_id = u.user_id 
                     JOIN appointment_status s ON a.status_id = s.status_id
                     WHERE u.mobile_number IS NOT NULL AND u.mobile_number != ''
                     LIMIT 5");
$apts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "4. Appointments with patient phone numbers:\n";
if (count($apts) > 0) {
    foreach ($apts as $apt) {
        echo "   - ID: {$apt['appointment_id']}, Patient: {$apt['first_name']} {$apt['last_name']}, Phone: {$apt['mobile_number']}, Status: {$apt['status_name']}\n";
    }
} else {
    echo "   ✗ No appointments found with patients that have phone numbers!\n";
}

// Check immunization records
$stmt = $pdo->query("SELECT ir.immunization_record_id, ir.patient_id, u.first_name, u.last_name, u.mobile_number, it.name
                     FROM immunization_records ir
                     JOIN patients p ON ir.patient_id = p.patient_id
                     JOIN users u ON p.user_id = u.user_id
                     JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id
                     WHERE u.mobile_number IS NOT NULL AND u.mobile_number != ''
                     LIMIT 5");
$imms = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n5. Immunization records with patient phone numbers:\n";
if (count($imms) > 0) {
    foreach ($imms as $imm) {
        echo "   - ID: {$imm['immunization_record_id']}, Patient: {$imm['first_name']} {$imm['last_name']}, Phone: {$imm['mobile_number']}, Type: {$imm['name']}\n";
    }
} else {
    echo "   ✗ No immunization records found with patients that have phone numbers!\n";
}

// Test direct SMS send
echo "\n6. Direct SMS Function Test:\n";
$test_phone = '09123456789';
$test_msg = 'API Test Message from Health Connect';

echo "   Testing sendSMS('$test_phone', '$test_msg')...\n";

// Only send if --send flag is provided
if (isset($argv[1]) && $argv[1] === '--send') {
    $result = sendSMS($test_phone, $test_msg);
    echo "   Result:\n";
    print_r($result);
} else {
    echo "   (Skipped - add --send flag to actually send)\n";
}

echo "\n===========================================\n";
echo "TEST COMPLETE\n";
echo "===========================================\n";
?>
