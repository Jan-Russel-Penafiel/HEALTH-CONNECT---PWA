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

// Test sendSMS function
echo "<h3>Testing sendSMS() function:</h3>";
$result = sendSMS($test_phone, $test_message);

echo "<pre>";
print_r($result);
echo "</pre>";

// Test sendSMSUsingIPROG function directly
echo "<h3>Testing sendSMSUsingIPROG() function directly:</h3>";
$api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004'; // Your IPROG API key
$direct_result = sendSMSUsingIPROG($test_phone, $test_message, $api_key);

echo "<pre>";
print_r($direct_result);
echo "</pre>";

// Check SMS settings in database
echo "<h3>Current SMS Settings in Database:</h3>";
try {
    $query = "SELECT name, value FROM settings WHERE name LIKE '%sms%'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Setting Name</th><th>Value</th></tr>";
    foreach ($settings as $setting) {
        echo "<tr><td>{$setting['name']}</td><td>{$setting['value']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error fetching settings: " . $e->getMessage();
}

echo "<p><strong>Note:</strong> Replace the test phone number with a real number to test actual SMS sending.</p>";
?>