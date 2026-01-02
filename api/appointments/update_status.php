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
    error_log("PHP Error in update_status.php: [$errno] $errstr in $errfile on line $errline");
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
    $log = date('Y-m-d H:i:s') . " [update_status.php] $message";
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
    // Include SMS functionality
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

    if (!isset($data['appointment_id']) || !isset($data['status'])) {
        logDebug("Missing required parameters", $data);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: appointment_id and status are required.'
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

    // Verify appointment belongs to this health worker
    $query = "SELECT status_id FROM appointments WHERE appointment_id = ? AND health_worker_id = ?";
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

    // Map status names to IDs (case-insensitive)
    $status_map = [
        'scheduled' => 1,
        'confirmed' => 2,
        'completed' => 3,
        'done' => 3,
        'cancelled' => 4,
        'no show' => 5,
        'no-show' => 5
    ];

    $status_id = $status_map[strtolower($data['status'])] ?? null;
    
    if ($status_id === null) {
        logDebug("Invalid status provided", ['status' => $data['status'], 'valid_statuses' => array_keys($status_map)]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status. Allowed values: Scheduled, Confirmed, Done, Cancelled, No Show'
        ]);
        exit;
    }

    // Update appointment status
    $query = "UPDATE appointments SET status_id = ?, updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ? AND health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$status_id, $data['appointment_id'], $health_worker['health_worker_id']]);

    if ($result && $stmt->rowCount() > 0) {
        logDebug("Status updated successfully", [
            'appointment_id' => $data['appointment_id'],
            'new_status' => $data['status'],
            'status_id' => $status_id
        ]);
        
        // Check if status is now "Confirmed" (status_id = 2)
        if ($status_id == 2) {
            // Get appointment details and patient information
            $query = "SELECT a.*, 
                             u.first_name, u.last_name, u.mobile_number,
                             hw_user.first_name as hw_first_name, hw_user.last_name as hw_last_name,
                             a.sms_notification_sent
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN users u ON p.user_id = u.user_id
                      JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
                      JOIN users hw_user ON hw.user_id = hw_user.user_id
                      WHERE a.appointment_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data['appointment_id']]);
            $appointment_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if patient has a mobile number and SMS hasn't been sent yet
            if (!empty($appointment_details['mobile_number']) && !$appointment_details['sms_notification_sent']) {
                // Check if SMS notifications are enabled
                $query = "SELECT value FROM settings WHERE name = 'enable_sms_notifications'";
                $stmt = $pdo->query($query);
                $sms_enabled = $stmt->fetchColumn();
                
                if ($sms_enabled == '1') {
                    // Format date and time for SMS
                    $appointment_date = date('M j, Y', strtotime($appointment_details['appointment_date']));
                    $appointment_time = date('g:i A', strtotime($appointment_details['appointment_time']));
                    
                    // Prepare SMS message (keep short for IPROG template)
                    $message = "Hello {$appointment_details['first_name']}, your appointment is CONFIRMED for {$appointment_date} at {$appointment_time}. Please arrive early. Thank you. - Respective Personnel";
                    
                    // Send SMS
                    $sms_result = sendSMS($appointment_details['mobile_number'], $message, $data['appointment_id']);
                    
                    // Update appointment notification status if SMS was sent successfully
                    if ($sms_result['success']) {
                        $update_query = "UPDATE appointments SET sms_notification_sent = 1 WHERE appointment_id = ?";
                        $update_stmt = $pdo->prepare($update_query);
                        $update_stmt->execute([$data['appointment_id']]);
                        
                        logDebug("SMS notification sent successfully", [
                            'appointment_id' => $data['appointment_id'],
                            'recipient' => $appointment_details['mobile_number']
                        ]);
                    } else {
                        logDebug("Failed to send SMS notification", [
                            'appointment_id' => $data['appointment_id'],
                            'error' => $sms_result['message']
                        ]);
                    }
                } else {
                    $sms_result = [
                        'success' => false,
                        'message' => 'SMS notifications are currently disabled in system settings'
                    ];
                }
            } else if ($appointment_details['sms_notification_sent']) {
                $sms_result = [
                    'success' => false,
                    'message' => 'SMS notification already sent for this appointment'
                ];
            } else {
                $sms_result = [
                    'success' => false,
                    'message' => 'Patient does not have a registered mobile number'
                ];
            }
        }
        
        // Check if status is now "Cancelled" (status_id = 4) - Send cancellation SMS
        if ($status_id == 4) {
            // Get appointment details and patient information
            $query = "SELECT a.*, 
                             u.first_name, u.last_name, u.mobile_number,
                             hw_user.first_name as hw_first_name, hw_user.last_name as hw_last_name
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN users u ON p.user_id = u.user_id
                      JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
                      JOIN users hw_user ON hw.user_id = hw_user.user_id
                      WHERE a.appointment_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data['appointment_id']]);
            $appointment_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if patient has a mobile number
            if (!empty($appointment_details['mobile_number'])) {
                // Check if SMS notifications are enabled
                $query = "SELECT value FROM settings WHERE name = 'enable_sms_notifications'";
                $stmt = $pdo->query($query);
                $sms_enabled = $stmt->fetchColumn();
                
                if ($sms_enabled == '1') {
                    // Format date and time for SMS
                    $appointment_date = date('F j, Y', strtotime($appointment_details['appointment_date']));
                    $appointment_time = date('g:i A', strtotime($appointment_details['appointment_time']));
                    
                    // Prepare cancellation SMS message
                    $message = "Hello {$appointment_details['first_name']}, your appointment at Brgy. Poblacion Health Center scheduled for {$appointment_date} at {$appointment_time} has been CANCELLED. Please contact us to reschedule. Thank you!";
                    
                    // Send SMS
                    $sms_result = sendSMS($appointment_details['mobile_number'], $message, $data['appointment_id']);
                    
                    if ($sms_result['success']) {
                        logDebug("Cancellation SMS notification sent successfully", [
                            'appointment_id' => $data['appointment_id'],
                            'recipient' => $appointment_details['mobile_number']
                        ]);
                    } else {
                        logDebug("Failed to send cancellation SMS notification", [
                            'appointment_id' => $data['appointment_id'],
                            'error' => $sms_result['message']
                        ]);
                    }
                } else {
                    $sms_result = [
                        'success' => false,
                        'message' => 'SMS notifications are currently disabled in system settings'
                    ];
                }
            } else {
                $sms_result = [
                    'success' => false,
                    'message' => 'Patient does not have a registered mobile number'
                ];
            }
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'sms_result' => $sms_result ?? null
        ]);
    } else {
        logDebug("No changes made to appointment", [
            'appointment_id' => $data['appointment_id'],
            'status' => $data['status'],
            'rowCount' => $stmt->rowCount()
        ]);
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No changes were made. The appointment might have been updated already.'
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