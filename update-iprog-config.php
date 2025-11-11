<?php
/**
 * Update SMS Configuration for Connect Project
 * This script updates the database settings to use IPROG SMS API
 */

require_once 'includes/config/database.php';

echo "<h2>Updating Connect Project SMS Configuration to IPROG</h2>";

try {
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // IPROG SMS Configuration
    $iprog_api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004';
    $iprog_settings = [
        'sms_api_key' => $iprog_api_key,
        'sms_provider' => 'IPROG SMS',
        'sms_api_url' => 'https://sms.iprogtech.com/api/v1/sms_messages',
        'enable_sms_notifications' => '1'
    ];
    
    echo "<h3>Updating SMS settings...</h3>";
    
    foreach ($iprog_settings as $setting_name => $setting_value) {
        // Check if setting exists
        $check_query = "SELECT COUNT(*) FROM settings WHERE name = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$setting_name]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists) {
            // Update existing setting
            $update_query = "UPDATE settings SET value = ? WHERE name = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$setting_value, $setting_name]);
            echo "✓ Updated {$setting_name}<br>";
        } else {
            // Insert new setting
            $insert_query = "INSERT INTO settings (name, value) VALUES (?, ?)";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->execute([$setting_name, $setting_value]);
            echo "✓ Added {$setting_name}<br>";
        }
    }
    
    echo "<h3>SMS Configuration Update Complete!</h3>";
    echo "<p>The following settings have been configured:</p>";
    echo "<ul>";
    echo "<li><strong>API Key:</strong> " . substr($iprog_api_key, 0, 8) . "... (IPROG SMS)</li>";
    echo "<li><strong>Provider:</strong> IPROG SMS</li>";
    echo "<li><strong>API URL:</strong> https://sms.iprogtech.com/api/v1/sms_messages</li>";
    echo "<li><strong>SMS Notifications:</strong> Enabled</li>";
    echo "</ul>";
    
    echo "<p><a href='test-iprog-sms.php'>Click here to test SMS functionality</a></p>";
    
} catch (Exception $e) {
    echo "Error updating SMS configuration: " . $e->getMessage();
    echo "<br><pre>";
    print_r($e->getTraceAsString());
    echo "</pre>";
}
?>