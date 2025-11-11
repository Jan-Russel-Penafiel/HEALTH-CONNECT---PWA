<?php
/**
 * SMS functionality using IPROG SMS API
 * This file provides functions to send SMS notifications
 */

/**
 * Send SMS using IPROG SMS API
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message content
 * @param string $api_key IPROG SMS API token
 * @return array Response with status and message
 */
function sendSMSUsingIPROG($phone_number, $message, $api_key) {
    // Prepare the phone number (remove any spaces and ensure 63 format for IPROG)
    $phone_number = str_replace([' ', '-'], '', $phone_number);
    if (substr($phone_number, 0, 1) === '0') {
        $phone_number = '63' . substr($phone_number, 1);
    } elseif (substr($phone_number, 0, 1) === '+') {
        $phone_number = substr($phone_number, 1);
    }

    // Validate phone number format
    if (!preg_match('/^63[0-9]{10}$/', $phone_number)) {
        return array(
            'success' => false,
            'message' => 'Invalid phone number format. Must be a valid Philippine mobile number.'
        );
    }

    // Prepare the request data for IPROG SMS API
    $data = array(
        'api_token' => $api_key,
        'message' => $message,
        'phone_number' => $phone_number
    );

    // Initialize cURL session
    $ch = curl_init("https://sms.iprogtech.com/api/v1/sms_messages");

    // Set cURL options for IPROG SMS
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ));

    // Execute cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);

    // Close cURL session
    curl_close($ch);

    // Log the API request for debugging
    error_log(sprintf(
        "IPROG SMS API Request - Number: %s, Status: %d, Response: %s, Error: %s",
        $phone_number,
        $http_code,
        $response,
        $curl_error
    ));

    // Handle cURL errors
    if ($curl_errno) {
        return array(
            'success' => false,
            'message' => 'Connection error: ' . $curl_error,
            'error_code' => $curl_errno
        );
    }

    // Parse response
    $result = json_decode($response, true);

    // Handle API response for IPROG SMS
    if ($http_code === 200 || $http_code === 201) {
        // Check if the response indicates success
        // IPROG returns status 200 in the response body for success, not 500
        if ((isset($result['status']) && $result['status'] === 200) ||
            (isset($result['status']) && $result['status'] === 'success') ||
            (isset($result['success']) && $result['success'] === true) ||
            (isset($result['message']) && is_string($result['message']) && stripos($result['message'], 'sent') !== false)) {
            return array(
                'success' => true,
                'message' => 'SMS sent successfully',
                'reference_id' => $result['message_id'] ?? $result['id'] ?? $result['reference'] ?? null,
                'delivery_status' => $result['status'] ?? 'Sent',
                'timestamp' => $result['timestamp'] ?? date('Y-m-d g:i A'),
                'data' => $result
            );
        }
    }

    // Handle error responses
    $error_message = '';
    if (isset($result['message'])) {
        $error_message = is_array($result['message']) ? implode(', ', $result['message']) : $result['message'];
    } elseif (isset($result['error'])) {
        $error_message = is_array($result['error']) ? implode(', ', $result['error']) : $result['error'];
    } elseif (isset($result['errors'])) {
        $error_message = is_array($result['errors']) ? implode(', ', $result['errors']) : $result['errors'];
    } else {
        $error_message = 'Unknown error occurred';
    }
    
    return array(
        'success' => false,
        'message' => 'API Error: ' . $error_message,
        'error_code' => $http_code,
        'error_details' => $result
    );
}

// Function to send SMS using IPROG SMS API
function sendSMS($recipient_number, $message, $appointment_id = null) {
    try {
        // Initialize database connection
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // If appointment_id is provided, check if SMS was already sent
        if ($appointment_id) {
            $check_query = "SELECT sms_notification_sent FROM appointments WHERE appointment_id = ?";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([$appointment_id]);
            $already_sent = $check_stmt->fetchColumn();
            
            if ($already_sent) {
                return [
                    'success' => false,
                    'message' => 'SMS notification already sent for this appointment'
                ];
            }
        }
        
        $query = "SELECT value FROM settings WHERE name = ?";
        $stmt = $pdo->prepare($query);
        
        // Get API key (now IPROG SMS API key)
        $stmt->execute(['sms_api_key']);
        $api_key = $stmt->fetchColumn();
        
        // Check if SMS notifications are enabled
        $stmt->execute(['enable_sms_notifications']);
        $sms_enabled = $stmt->fetchColumn();
        
        if ($sms_enabled != '1') {
            return [
                'success' => false,
                'message' => 'SMS notifications are disabled in settings'
            ];
        }

        // Use default IPROG API key if not configured in database
        if (empty($api_key)) {
            $api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004';
        }

        // Send SMS using IPROG SMS
        $sms_result = sendSMSUsingIPROG($recipient_number, $message, $api_key);

        // Log SMS in database
        if ($appointment_id) {
            $log_query = "INSERT INTO sms_logs (appointment_id, recipient_number, message, status, sent_at) 
                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $status = $sms_result['success'] ? 'sent' : 'failed';
            $log_stmt->execute([$appointment_id, $recipient_number, $message, $status]);
        }
        
        // Return response in the expected format
        return $sms_result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
} 