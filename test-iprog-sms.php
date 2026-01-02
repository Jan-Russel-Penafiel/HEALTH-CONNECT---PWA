<?php
/**
 * Test IPROG SMS Integration for Connect Project
 */

require_once 'includes/config/database.php';
require_once 'includes/sms.php';

// Test phone number - replace with a real number for testing
$test_phone = '09123456789';
$test_message = 'Test SMS from Connect using IPROG SMS API';

echo "<h2>Testing IPROG SMS Integration - Connect Project</h2>";

// Initialize database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage();
    exit;
}

// Test convertToPlainMessage function
echo "<h3>Testing convertToPlainMessage() function:</h3>";
$test_technical = "Your appointment_id 123 with health_worker_id 5 on appointment_date is confirmed.";
$plain = convertToPlainMessage($test_technical);
echo "<p>Original: <code>" . htmlspecialchars($test_technical) . "</code></p>";
echo "<p>Converted: <code>" . htmlspecialchars($plain) . "</code></p>";

// Test formatSMSMessage function
echo "<h3>Testing formatSMSMessage() function:</h3>";
$formatted = formatSMSMessage($test_message);
echo "<p>Original: <code>" . htmlspecialchars($test_message) . "</code></p>";
echo "<p>Formatted: <code>" . htmlspecialchars($formatted) . "</code></p>";
echo "<p>Length: " . strlen($formatted) . " characters (160 = 1 SMS credit)</p>";

// Only send actual SMS if explicitly requested
if (isset($_GET['send']) && $_GET['send'] === 'true') {
    // Test sendSMS function
    echo "<h3>Testing sendSMS() function:</h3>";
    $result = sendSMS($test_phone, $test_message);
    
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if (isset($result['duplicate']) && $result['duplicate']) {
        echo "<p style='color: green;'><strong>✓ Duplicate prevention is working!</strong> No SMS credit was used.</p>";
    }
    
    // Test sendSMSUsingIPROG function directly
    echo "<h3>Testing sendSMSUsingIPROG() function directly:</h3>";
    $api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004'; // Your IPROG API key
    $direct_result = sendSMSUsingIPROG($test_phone, $test_message, $api_key);
    
    echo "<pre>";
    print_r($direct_result);
    echo "</pre>";
} else {
    echo "<p><strong>Note:</strong> SMS sending is disabled by default. Add <code>?send=true</code> to the URL to actually send SMS.</p>";
}

// Check SMS settings in database
echo "<h3>Current SMS Settings in Database:</h3>";
try {
    $query = "SELECT name, value FROM settings WHERE name LIKE '%sms%'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Setting Name</th><th>Value</th></tr>";
    foreach ($settings as $setting) {
        echo "<tr><td>{$setting['name']}</td><td>{$setting['value']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error fetching settings: " . $e->getMessage();
}

// Show available SMS functions
echo "<h3>Available SMS Functions:</h3>";
echo "<ul>";
echo "<li><code>sendSMS(\$phone, \$message, \$appointment_id = null)</code> - Send general SMS</li>";
echo "<li><code>sendAppointmentConfirmationSMS(\$appointment_id)</code> - Send appointment confirmation</li>";
echo "<li><code>sendAppointmentReminderSMS(\$appointment_id)</code> - Send appointment reminder</li>";
echo "<li><code>sendAppointmentCancellationSMS(\$appointment_id, \$reason = null)</code> - Send cancellation notice</li>";
echo "<li><code>sendImmunizationReminderSMS(\$patient_id, \$vaccine_name, \$due_date = null)</code> - Send immunization reminder</li>";
echo "<li><code>sendPatientNotificationSMS(\$patient_id, \$message)</code> - Send general patient notification</li>";
echo "</ul>";

echo "<h3>Features:</h3>";
echo "<ul>";
echo "<li>✓ Automatic IPROG template prefix</li>";
echo "<li>✓ Technical term conversion (snake_case to Plain Text)</li>";
echo "<li>✓ Duplicate prevention (5 minute window)</li>";
echo "<li>✓ Improved API response detection</li>";
echo "<li>✓ SMS logging to database</li>";
echo "</ul>";

echo "<p><a href='?send=true&phone={$test_phone}'>Click here to send a test SMS</a> (will use 1 SMS credit)</p>";
?>