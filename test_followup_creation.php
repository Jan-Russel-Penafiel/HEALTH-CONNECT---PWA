<?php
/**
 * Test Follow-up Creation and SMS Sending
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simulate health worker session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'health_worker';
$_SESSION['health_worker_id'] = 1;

require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/sms.php';

echo "<h2>Follow-up Creation Test</h2>";
echo "<hr>";

$database = new Database();
$pdo = $database->getConnection();

// Test patient
$test_patient_id = 1; // Change to valid patient ID

// Get patient info
$query = "SELECT p.*, u.first_name, u.last_name, u.mobile_number
          FROM patients p
          JOIN users u ON p.user_id = u.user_id
          WHERE p.patient_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$test_patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "<p style='color:red;'>Patient not found!</p>";
    exit;
}

echo "<h3>Patient Information:</h3>";
echo "<ul>";
echo "<li>Name: {$patient['first_name']} {$patient['last_name']}</li>";
echo "<li>Mobile: {$patient['mobile_number']}</li>";
echo "</ul>";
echo "<hr>";

// Simulate form data
$has_follow_up = true;
$follow_up_date = date('Y-m-d', strtotime('+7 days'));
$follow_up_message = "Please bring your laboratory results.";
$notes = "Patient doing well, continuing treatment.";

echo "<h3>Follow-up Details:</h3>";
echo "<ul>";
echo "<li>Has Follow-up: " . ($has_follow_up ? 'YES' : 'NO') . "</li>";
echo "<li>Follow-up Date: {$follow_up_date}</li>";
echo "<li>Doctor's Message: {$follow_up_message}</li>";
echo "</ul>";
echo "<hr>";

// Create JSON notes
$notes_data = [
    'notes' => $notes,
    'follow_up_message' => $follow_up_message
];
$combined_notes = json_encode($notes_data);

echo "<h3>JSON Notes:</h3>";
echo "<pre>" . htmlspecialchars(json_encode($notes_data, JSON_PRETTY_PRINT)) . "</pre>";
echo "<hr>";

// Insert test medical record
try {
    $query = "INSERT INTO medical_records (patient_id, health_worker_id, visit_date, chief_complaint, diagnosis, treatment, prescription, notes, follow_up_date, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $test_patient_id,
        1, // health_worker_id
        date('Y-m-d'),
        'Test complaint',
        'Test diagnosis',
        'Test treatment',
        'Test prescription',
        $combined_notes,
        $follow_up_date
    ]);
    
    $record_id = $pdo->lastInsertId();
    echo "<p style='color:green;'>✅ Medical record created successfully (ID: {$record_id})</p>";
    echo "<hr>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Error creating medical record: " . $e->getMessage() . "</p>";
    exit;
}

// Now test SMS sending
echo "<h3>Testing SMS Sending:</h3>";

if ($has_follow_up && !empty($follow_up_date) && !empty($patient['mobile_number'])) {
    echo "<p>✓ Conditions met for SMS sending</p>";
    
    $formatted_date = date('M j, Y', strtotime($follow_up_date));
    $patient_name = $patient['first_name'];
    
    if (!empty($follow_up_message)) {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Doctor's note: {$follow_up_message}. Thank you. - Respective Personnel";
    } else {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Please visit the Health Center. Thank you. - Respective Personnel";
    }
    
    echo "<h4>Message to send:</h4>";
    echo "<pre>" . htmlspecialchars($message) . "</pre>";
    echo "<p>Length: " . strlen($message) . " characters</p>";
    echo "<hr>";
    
    echo "<h4>Sending SMS...</h4>";
    $sms_result = sendSMS($patient['mobile_number'], $message);
    
    echo "<h4>SMS Result:</h4>";
    echo "<pre>" . htmlspecialchars(json_encode($sms_result, JSON_PRETTY_PRINT)) . "</pre>";
    
    if ($sms_result && isset($sms_result['success'])) {
        if ($sms_result['success']) {
            echo "<p style='color:green; font-weight:bold;'>✅ SMS SENT SUCCESSFULLY!</p>";
        } else {
            echo "<p style='color:red; font-weight:bold;'>❌ SMS FAILED: " . htmlspecialchars($sms_result['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color:orange; font-weight:bold;'>⚠ Unexpected SMS result format</p>";
    }
} else {
    echo "<p style='color:red;'>✗ SMS conditions NOT met:</p>";
    echo "<ul>";
    echo "<li>Has follow-up: " . ($has_follow_up ? 'YES' : 'NO') . "</li>";
    echo "<li>Follow-up date: " . ($follow_up_date ?: 'EMPTY') . "</li>";
    echo "<li>Mobile number: " . ($patient['mobile_number'] ?: 'EMPTY') . "</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Check SMS Logs:</h3>";
$stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($logs) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Recipient</th><th>Message</th><th>Status</th><th>Sent At</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['sms_id']}</td>";
        echo "<td>{$log['recipient_number']}</td>";
        echo "<td>" . htmlspecialchars(substr($log['message'], 0, 100)) . "...</td>";
        echo "<td>{$log['status']}</td>";
        echo "<td>{$log['sent_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No SMS logs found</p>";
}
?>
