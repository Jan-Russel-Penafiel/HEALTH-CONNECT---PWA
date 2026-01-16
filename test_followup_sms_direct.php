<?php
/**
 * Direct test of follow-up SMS functionality
 * Bypasses API endpoint to test SMS sending directly
 */

set_time_limit(60);
ini_set('max_execution_time', 60);

echo "<h2>Direct Follow-up SMS Test</h2>\n";
echo "<p>Testing SMS sending directly without API endpoint...</p>\n";
echo "<hr>\n";

try {
    require_once __DIR__ . '/includes/config/database.php';
    require_once __DIR__ . '/includes/sms.php';

    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();

    // Test record ID
    $test_record_id = 10;
    
    echo "<h3>Step 1: Fetching Record Data</h3>\n";
    echo "<pre>";
    
    // Get medical record with patient details
    $query = "SELECT mr.*, u.first_name, u.last_name, u.mobile_number, p.patient_id
              FROM medical_records mr
              JOIN patients p ON mr.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              WHERE mr.record_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$test_record_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo "ERROR: Medical record not found!\n";
        echo "</pre>";
        exit;
    }
    
    echo "Record found:\n";
    echo "  Patient: {$record['first_name']} {$record['last_name']}\n";
    echo "  Phone: {$record['mobile_number']}\n";
    echo "  Follow-up Date: {$record['follow_up_date']}\n";
    
    // Parse JSON from notes field to get follow_up_message
    $follow_up_message = '';
    if (!empty($record['notes'])) {
        $notes_data = json_decode($record['notes'], true);
        if (is_array($notes_data) && isset($notes_data['follow_up_message'])) {
            $follow_up_message = $notes_data['follow_up_message'];
            echo "  Doctor's Message: {$follow_up_message}\n";
        }
    }
    echo "</pre>\n";

    if (empty($record['follow_up_date'])) {
        echo "<p style='color:red;'>ERROR: This record does not have a follow-up date set.</p>\n";
        exit;
    }

    if (empty($record['mobile_number'])) {
        echo "<p style='color:red;'>ERROR: Patient does not have a registered mobile number.</p>\n";
        exit;
    }

    echo "<hr>\n";
    echo "<h3>Step 2: Preparing SMS Message</h3>\n";
    echo "<pre>";
    
    // Format the message (keep short for IPROG SMS - sendSMS will add the prefix)
    $formatted_date = date('M j, Y', strtotime($record['follow_up_date']));
    $patient_name = $record['first_name'];
    
    // Build message based on whether there's a doctor's note
    // Add unique timestamp to bypass deduplication for testing
    $test_suffix = " [Test: " . date('H:i:s') . "]";
    
    if (!empty($follow_up_message)) {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Doctor's note: {$follow_up_message}. Thank you. - Respective Personnel" . $test_suffix;
    } else {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Please visit the Health Center. Thank you. - Respective Personnel" . $test_suffix;
    }
    
    echo "Message to send:\n";
    echo wordwrap($message, 70) . "\n";
    echo "\nMessage length: " . strlen($message) . " characters\n";
    echo "</pre>\n";

    echo "<hr>\n";
    echo "<h3>Step 3: Checking SMS Settings</h3>\n";
    echo "<pre>";
    
    $query = "SELECT name, value FROM settings WHERE name IN ('enable_sms_notifications', 'sms_api_key')";
    $stmt = $pdo->query($query);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "SMS Enabled: " . ($settings['enable_sms_notifications'] ?? 'NOT SET') . "\n";
    echo "API Key: " . (isset($settings['sms_api_key']) && !empty($settings['sms_api_key']) ? 'SET (' . strlen($settings['sms_api_key']) . ' chars)' : 'NOT SET') . "\n";
    echo "</pre>\n";
    
    if (!isset($settings['enable_sms_notifications']) || $settings['enable_sms_notifications'] != '1') {
        echo "<p style='color:orange;'>WARNING: SMS notifications are disabled in settings!</p>\n";
    }

    echo "<hr>\n";
    echo "<h3>Step 4: Sending SMS</h3>\n";
    echo "<p>Calling sendSMS function...</p>\n";
    echo "<pre>";
    
    $start_time = microtime(true);
    
    // Send SMS using sendSMS function which handles formatting and API call
    $sms_result = sendSMS($record['mobile_number'], $message);
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    echo "\nExecution time: {$execution_time} seconds\n";
    echo "\nSMS Result:\n";
    echo json_encode($sms_result, JSON_PRETTY_PRINT);
    echo "</pre>\n";

    if ($sms_result['success']) {
        echo "<p style='color:green; font-weight:bold;'>✅ SUCCESS: SMS sent successfully!</p>\n";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ FAILED: " . htmlspecialchars($sms_result['message'] ?? 'Unknown error') . "</p>\n";
    }

    echo "<hr>\n";
    echo "<h3>Step 5: Recent SMS Logs</h3>\n";
    
    $stmt = $pdo->query("SELECT sms_id, recipient_number, LEFT(message, 100) as message_preview, status, sent_at 
                         FROM sms_logs 
                         ORDER BY sent_at DESC 
                         LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>Phone</th><th>Message Preview</th><th>Status</th><th>Sent At</th></tr>\n";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['sms_id']}</td>";
        echo "<td>{$log['recipient_number']}</td>";
        echo "<td>" . htmlspecialchars($log['message_preview']) . "...</td>";
        echo "<td>{$log['status']}</td>";
        echo "<td>{$log['sent_at']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<hr>\n";
echo "<p><a href='test_followup_sms_direct.php'>Run Test Again</a></p>\n";
?>
