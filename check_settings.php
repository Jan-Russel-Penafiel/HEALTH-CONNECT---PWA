<?php
require_once __DIR__ . '/includes/config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Required settings
    $required_settings = [
        'sms_api_key' => '',  // Add your PhilSMS API key here
        'sms_sender_id' => 'HealthConnect',  // Default sender ID
        'enable_sms_notifications' => '1'  // Enable by default
    ];

    // Check and insert settings
    foreach ($required_settings as $name => $default_value) {
        $query = "SELECT COUNT(*) FROM settings WHERE name = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$name]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $insert = "INSERT INTO settings (name, value) VALUES (?, ?)";
            $stmt = $pdo->prepare($insert);
            $stmt->execute([$name, $default_value]);
            echo "Added setting: $name\n";
        } else {
            echo "Setting exists: $name\n";
        }
    }

    echo "\nAll required settings are in place.\n";
    
    // Display current settings
    $query = "SELECT * FROM settings WHERE name IN ('sms_api_key', 'sms_sender_id', 'enable_sms_notifications')";
    $stmt = $pdo->query($query);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent settings:\n";
    foreach ($settings as $setting) {
        echo "{$setting['name']}: {$setting['value']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 