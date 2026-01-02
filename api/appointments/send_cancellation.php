<?php
/**
 * Send Cancellation SMS API
 * Sends SMS notification when an appointment is cancelled
 */

// Ensure no output before headers
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON content type immediately
header('Content-Type: application/json');

// Custom error handler
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error in send_cancellation.php: [$errno] $errstr in $errfile on line $errline");
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
    $log = date('Y-m-d H:i:s') . " [send_cancellation.php] $message";
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
        logDebug("Session validation failed", $_SESSION);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please refresh the page and login again.'
        ]);
        exit;
    }

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
    logDebug("Received raw input", ['input' => $raw_input]);

    if (empty($raw_input)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No data received. Please try again.'
        ]);
        exit;
    }

    $data = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logDebug("JSON decode error", ['error' => json_last_error_msg()]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg()
        ]);
        exit;
    }

    logDebug("Parsed JSON data", $data);

    if (!isset($data['appointment_id'])) {
        logDebug("Missing required parameters", $data);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameter: appointment_id is required.'
        ]);
        exit;
    }

    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get health worker ID
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        logDebug("Health worker not found", ['user_id' => $_SESSION['user_id']]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Health worker record not found. Please contact administrator.'
        ]);
        exit;
    }
    
    // Get appointment details with patient info
    $query = "SELECT a.*, 
                     u.first_name, u.last_name, u.mobile_number,
                     hw_user.first_name as hw_first_name, hw_user.last_name as hw_last_name,
                     s.status_name as status
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users hw_user ON hw.user_id = hw_user.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.appointment_id = ? AND a.health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$data['appointment_id'], $health_worker['health_worker_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        logDebug("Appointment not found or not assigned", [
            'appointment_id' => $data['appointment_id'],
            'health_worker_id' => $health_worker['health_worker_id']
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found or not assigned to you.'
        ]);
        exit;
    }

    // Check if patient has mobile number
    if (empty($appointment['mobile_number'])) {
        logDebug("Patient has no mobile number", [
            'appointment_id' => $data['appointment_id']
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Patient does not have a registered mobile number.'
        ]);
        exit;
    }
    
    // Check if SMS notifications are enabled
    $query = "SELECT value FROM settings WHERE name = 'enable_sms_notifications'";
    $stmt = $pdo->query($query);
    $sms_enabled = $stmt->fetchColumn();
    
    if ($sms_enabled != '1') {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'SMS notifications are currently disabled in system settings.'
        ]);
        exit;
    }
    
    // Format date and time for SMS
    $appointment_date = date('M j, Y', strtotime($appointment['appointment_date']));
    $appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
    
    // Get cancellation reason if provided
    $reason = isset($data['reason']) ? $data['reason'] : '';
    
    // Prepare cancellation SMS message (keep short for IPROG template)
    $message = "Hello {$appointment['first_name']}, your appointment on {$appointment_date} at {$appointment_time} has been CANCELLED.";
    if (!empty($reason)) {
        $message .= " Reason: {$reason}.";
    }
    $message .= " Thank you. - Respective Personnel";
    
    // Send SMS using the sendSMS function
    // NOTE: We do NOT pass appointment_id here because cancellation SMS should be allowed 
    // even after a confirmation SMS was sent. The 1-minute deduplication will prevent double-sends.
    $sms_result = sendSMS($appointment['mobile_number'], $message);
    
    if ($sms_result['success']) {
        logDebug("Cancellation SMS sent successfully", [
            'appointment_id' => $data['appointment_id'],
            'recipient' => $appointment['mobile_number'],
            'reference_id' => $sms_result['reference_id'] ?? null
        ]);
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Cancellation SMS sent successfully!',
            'reference_id' => $sms_result['reference_id'] ?? null
        ]);
    } else {
        logDebug("Failed to send cancellation SMS", [
            'appointment_id' => $data['appointment_id'],
            'error' => $sms_result['message']
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send SMS: ' . $sms_result['message']
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
