<?php
/**
 * Test script for follow-up SMS reminder
 * This simulates a health worker sending a follow-up reminder
 */

// Disable output buffering for immediate display
while (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);

// Start session to simulate logged-in health worker
session_start();

// Simulate logged-in health worker (adjust these values as needed)
$_SESSION['user_id'] = 1; // Change to valid health worker user_id
$_SESSION['role'] = 'health_worker';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Health Worker';

echo "<h2>Testing Follow-up SMS Reminder</h2>\n";
echo "<p>Session set as health worker (user_id: {$_SESSION['user_id']})</p>\n";

// Record ID to test (from database query results)
$test_record_id = 10; // Change this to a valid record_id from your database

echo "<p>Testing with record_id: {$test_record_id}</p>\n";
echo "<hr>\n";

// Prepare the API request
$url = 'http://localhost/connect/api/medical_records/send_follow_up_reminder.php';
$data = json_encode(['record_id' => $test_record_id]);

// Get session ID to pass with the request
$session_id = session_id();
$session_name = session_name();

// Initialize cURL with timeouts
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data),
    'Cookie: ' . $session_name . '=' . $session_id
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connect timeout
curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

echo "<h3>Request Details:</h3>\n";
echo "<pre>\n";
echo "URL: {$url}\n";
echo "Method: POST\n";
echo "Headers:\n";
echo "  Content-Type: application/json\n";
echo "  Cookie: {$session_name}={$session_id}\n";
echo "Body: {$data}\n";
echo "</pre>\n";
echo "<hr>\n";

// Flush output so user sees progress
echo "<p><strong>Sending request...</strong></p>\n";
flush();
ob_flush();

// Execute request
$start_time = microtime(true);
$response = curl_exec($ch);
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);

curl_close($ch);

// Display results
echo "<h3>Response:</h3>\n";
echo "<pre>\n";
echo "Execution Time: {$execution_time} seconds\n";
echo "HTTP Code: {$http_code}\n";

if ($curl_error) {
    echo "cURL Error ({$curl_errno}): {$curl_error}\n";
}

echo "Response Body:\n";
if ($response) {
    $response_data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode($response_data, JSON_PRETTY_PRINT);
    } else {
        echo $response;
    }
} else {
    echo "No response received\n";
}
echo "</pre>\n";

// Check SMS logs
echo "<hr>\n";
echo "<h3>Recent SMS Logs (Last 5):</h3>\n";
require_once __DIR__ . '/includes/config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->query("SELECT sms_id, recipient_number, message, status, sent_at 
                     FROM sms_logs 
                     ORDER BY sent_at DESC 
                     LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>ID</th><th>Phone</th><th>Message</th><th>Status</th><th>Sent At</th></tr>\n";
foreach ($logs as $log) {
    echo "<tr>";
    echo "<td>{$log['sms_id']}</td>";
    echo "<td>{$log['recipient_number']}</td>";
    echo "<td>" . htmlspecialchars(substr($log['message'], 0, 100)) . "...</td>";
    echo "<td>{$log['status']}</td>";
    echo "<td>{$log['sent_at']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<hr>\n";
echo "<p><a href='test_followup_sms.php'>Run Test Again</a></p>\n";
?>
