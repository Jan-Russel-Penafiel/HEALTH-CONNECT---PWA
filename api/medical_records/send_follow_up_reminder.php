<?php
/**
 * Send Follow-up Reminder SMS API
 * This endpoint sends SMS reminders to patients with upcoming follow-up checkups
 */

// Ensure no output before headers
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type
header('Content-Type: application/json');

// Custom error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error in send_follow_up_reminder.php: [$errno] $errstr in $errfile on line $errline");
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'A system error occurred. Please try again later.'
    ]);
    exit;
}
set_error_handler('handleError');

try {
    // Check if session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please refresh the page and login again.'
        ]);
        exit;
    }

    require_once __DIR__ . '/../../includes/config/database.php';
    require_once __DIR__ . '/../../includes/sms.php';

    // Check if user is health worker or admin
    if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access.'
        ]);
        exit;
    }

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method.'
        ]);
        exit;
    }

    // Get and validate JSON data
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data.'
        ]);
        exit;
    }

    if (!isset($data['record_id'])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Missing record_id parameter.'
        ]);
        exit;
    }

    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();

    // Get medical record with patient details
    $query = "SELECT mr.*, u.first_name, u.last_name, u.mobile_number, p.patient_id
              FROM medical_records mr
              JOIN patients p ON mr.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              WHERE mr.record_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$data['record_id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Medical record not found.'
        ]);
        exit;
    }
    
    // Parse JSON from notes field to get follow_up_message
    $follow_up_message = '';
    if (!empty($record['notes'])) {
        $notes_data = json_decode($record['notes'], true);
        if (is_array($notes_data) && isset($notes_data['follow_up_message'])) {
            $follow_up_message = $notes_data['follow_up_message'];
        }
    }

    if (empty($record['follow_up_date'])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'This record does not have a follow-up date set.'
        ]);
        exit;
    }

    if (empty($record['mobile_number'])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Patient does not have a registered mobile number.'
        ]);
        exit;
    }

    // Format the message (keep short for IPROG SMS - sendSMS will add the prefix)
    $formatted_date = date('M j, Y', strtotime($record['follow_up_date']));
    $patient_name = $record['first_name'];
    
    // Build message based on whether there's a doctor's note
    if (!empty($follow_up_message)) {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Doctor's note: {$follow_up_message}. Thank you. - Respective Personnel";
    } else {
        $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Please visit the Health Center. Thank you. - Respective Personnel";
    }

    // Send SMS using sendSMS function which handles formatting and API call
    $sms_result = sendSMS($record['mobile_number'], $message);

    if ($sms_result['success']) {
        // Wait briefly to respect API rate limits (20 seconds if multiple requests expected)
        // This ensures smooth operation when sending multiple reminders
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Follow-up reminder SMS sent successfully to ' . $record['first_name'] . ' ' . $record['last_name'] . '.'
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send SMS: ' . ($sms_result['message'] ?? 'Unknown error')
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in send_follow_up_reminder.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log("General error in send_follow_up_reminder.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

ob_end_flush();
?>
