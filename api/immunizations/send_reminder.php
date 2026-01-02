<?php
// Ensure no output before headers
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type immediately
header('Content-Type: application/json');

// Custom error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error in immunization send_reminder.php: [$errno] $errstr in $errfile on line $errline");
    $error = [
        'success' => false,
        'message' => 'A system error occurred. Please try again later.',
        'debug_info' => [
            'error_type' => $errno,
            'error_message' => $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ]
    ];
    ob_clean();
    echo json_encode($error);
    exit;
}
set_error_handler('handleError');

// Function to log detailed debug information
function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " [immunization_send_reminder.php] $message";
    if ($data !== null) {
        $log .= "\nData: " . print_r($data, true);
    }
    error_log($log);
}

try {
    // Check if session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    logDebug("Session status check completed", ['session_id' => session_id(), 'user_id' => $_SESSION['user_id'] ?? 'not set']);

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        logDebug("Session validation failed", [
            'session_data' => $_SESSION,
            'session_id' => session_id(),
            'cookie_params' => session_get_cookie_params()
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please refresh the page and login again.'
        ]);
        exit;
    }

    logDebug("Session validation successful", [
        'user_id' => $_SESSION['user_id'], 
        'role' => $_SESSION['role'],
        'session_id' => session_id()
    ]);

    require_once __DIR__ . '/../../includes/config/database.php';
    require_once __DIR__ . '/../../includes/sms.php';

    // Check if user is health worker
    if ($_SESSION['role'] !== 'health_worker') {
        logDebug("Unauthorized access attempt", ['role' => $_SESSION['role']]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access. Please login as a health worker.'
        ]);
        exit;
    }

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logDebug("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method. Only POST requests are allowed.'
        ]);
        exit;
    }

    // Get and validate JSON data
    $raw_input = file_get_contents('php://input');
    logDebug("Received raw input", ['input' => $raw_input, 'length' => strlen($raw_input)]);

    if (empty($raw_input)) {
        logDebug("Empty raw input received");
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No data received. Please try again.'
        ]);
        exit;
    }

    $data = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logDebug("JSON decode error", ['error' => json_last_error_msg(), 'raw_input' => $raw_input]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg()
        ]);
        exit;
    }

    logDebug("Parsed JSON data", $data);

    // Validate required parameters
    if (!isset($data['patient_id']) || !isset($data['message'])) {
        logDebug("Missing required parameters", $data);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: patient_id and message are required.'
        ]);
        exit;
    }

    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get patient's phone number
    $query = "SELECT u.mobile_number, u.first_name, u.last_name
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.patient_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$data['patient_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        logDebug("Patient not found", [
            'patient_id' => $data['patient_id']
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Patient not found.'
        ]);
        exit;
    }

    if (empty($patient['mobile_number'])) {
        logDebug("Patient has no mobile number", [
            'patient_id' => $data['patient_id'],
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name']
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Patient has no mobile number on file.'
        ]);
        exit;
    }

    // Check if SMS notifications are enabled
    $query = "SELECT value FROM settings WHERE name = 'enable_sms_notifications'";
    $stmt = $pdo->query($query);
    $sms_enabled = $stmt->fetchColumn();
    
    if ($sms_enabled != '1') {
        logDebug("SMS notifications disabled in settings");
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'SMS notifications are currently disabled in system settings.'
        ]);
        exit;
    }

    // Send SMS reminder
    $sms_result = sendSMS($patient['mobile_number'], $data['message']);
    
    logDebug("SMS send result", $sms_result);

    if ($sms_result['success']) {
        // Log the reminder in immunization_reminders table (if it exists)
        try {
            $log_query = "INSERT INTO immunization_reminders (patient_id, message, sent_at, sent_by) 
                          VALUES (?, ?, CURRENT_TIMESTAMP, ?)";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([$data['patient_id'], $data['message'], $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Table might not exist, just log the error but don't fail
            logDebug("Could not log reminder (table might not exist)", ['error' => $e->getMessage()]);
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Immunization reminder sent successfully to ' . $patient['first_name'] . ' ' . $patient['last_name']
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send reminder: ' . $sms_result['message']
        ]);
    }

} catch (PDOException $e) {
    logDebug("Database error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    logDebug("General error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}

// Ensure we send the output
ob_end_flush();
?>
