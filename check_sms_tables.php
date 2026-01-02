<?php
require_once 'includes/config/database.php';
$db = new Database();
$pdo = $db->getConnection();

// Check if sms_logs table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'sms_logs'");
$tables = $stmt->fetchAll();
echo "sms_logs table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";

// If table doesn't exist, create it
if (count($tables) === 0) {
    echo "Creating sms_logs table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS sms_logs (
        sms_id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NULL,
        recipient_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient_number),
        INDEX idx_status (status),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "sms_logs table created successfully!\n";
}

// Check if immunization_reminders table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'immunization_reminders'");
$tables = $stmt->fetchAll();
echo "immunization_reminders table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";

if (count($tables) === 0) {
    echo "Creating immunization_reminders table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS immunization_reminders (
        reminder_id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        message TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_by INT NULL,
        INDEX idx_patient (patient_id),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "immunization_reminders table created successfully!\n";
}

echo "\nDatabase tables verified!\n";
?>
