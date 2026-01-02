<?php
/**
 * SMS functionality using IPROG SMS API
 * This file provides functions to send SMS notifications
 */

/**
 * Convert technical class names and terms to plain text for SMS
 * @param string $message Original message that may contain class names
 * @return string Plain text message suitable for SMS
 */
function convertToPlainMessage($message) {
    // Remove HTML tags if any
    $message = strip_tags($message);
    
    // Replace common database attributes and technical terms with plain language
    $replacements = array(
        // User and patient related
        'patient_id' => 'Patient ID',
        'user_id' => 'User ID',
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'last_name' => 'Last Name',
        'full_name' => 'Full Name',
        'mobile_number' => 'Mobile Number',
        'contact_number' => 'Contact Number',
        'email_address' => 'Email Address',
        
        // Health worker related
        'health_worker_id' => 'Health Worker ID',
        'hw_first_name' => 'Doctor First Name',
        'hw_last_name' => 'Doctor Last Name',
        
        // Appointment related
        'appointment_id' => 'Appointment ID',
        'appointment_date' => 'Appointment Date',
        'appointment_time' => 'Appointment Time',
        'status_id' => 'Status ID',
        'sms_notification_sent' => 'SMS Sent',
        
        // Immunization related
        'immunization_id' => 'Immunization ID',
        'vaccine_name' => 'Vaccine Name',
        'dose_number' => 'Dose Number',
        'next_dose_date' => 'Next Dose Date',
        
        // Medical records related
        'record_id' => 'Record ID',
        'diagnosis' => 'Diagnosis',
        'prescription' => 'Prescription',
        'medical_history' => 'Medical History',
        
        // System related
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'deleted_at' => 'Deleted',
        'sent_at' => 'Sent'
    );
    
    // Apply replacements (case-insensitive)
    foreach ($replacements as $technical => $plain) {
        $message = str_ireplace($technical, $plain, $message);
    }
    
    // Convert remaining camelCase to plain text
    $message = preg_replace('/([a-z])([A-Z])/', '$1 $2', $message);
    
    // Convert remaining snake_case to plain text
    $message = preg_replace('/(\w+)_(\w+)/', '$1 $2', $message);
    
    // Remove excessive whitespace
    $message = preg_replace('/\s+/', ' ', $message);
    
    // Trim and clean up
    $message = trim($message);
    
    return $message;
}

/**
 * Format SMS message with required IPROG template prefix
 * Message should already include footer from the calling code
 * @param string $message Original message content
 * @return string Formatted message with IPROG prefix
 */
function formatSMSMessage($message) {
    // Convert to plain text first
    $message = convertToPlainMessage($message);
    
    // Required prefix for IPROG template compatibility
    $prefix = 'This is an important message from the Organization. ';
    
    // Don't add prefix if message already starts with it
    if (strpos($message, $prefix) === 0) {
        return $message;
    }
    
    return $prefix . $message;
}

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

    // Format message with universal prefix for IPROG template compatibility
    $formatted_message = formatSMSMessage($message);
    
    // Prepare the request data for IPROG SMS API
    $data = array(
        'api_token' => $api_key,
        'message' => $formatted_message,
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
    // IPROG returns: {"status":200,"message":"SMS successfully queued for delivery.","message_id":"xxx"}
    // On error: {"status":500,"message":["error message"]}
    if ($http_code === 200 || $http_code === 201) {
        // First check for IPROG error status in response body
        if (isset($result['status']) && ($result['status'] === 500 || $result['status'] === '500')) {
            $error_message = '';
            if (isset($result['message'])) {
                $error_message = is_array($result['message']) ? implode(', ', $result['message']) : $result['message'];
            }
            return array(
                'success' => false,
                'message' => 'IPROG API Error: ' . $error_message,
                'error_code' => $result['status'],
                'error_details' => $result
            );
        }
        
        // Check for IPROG specific success indicators
        $isStatusSuccess = isset($result['status']) && ($result['status'] === 200 || $result['status'] === 'success' || $result['status'] === 201);
        $hasMessageId = isset($result['message_id']) && !empty($result['message_id']);
        $messageContainsSuccess = isset($result['message']) && is_string($result['message']) && 
                                  (stripos($result['message'], 'queued') !== false || 
                                   stripos($result['message'], 'sent') !== false ||
                                   stripos($result['message'], 'success') !== false);
        
        if ($isStatusSuccess || $hasMessageId || $messageContainsSuccess ||
            (isset($result['success']) && $result['success'] === true)) {
            return array(
                'success' => true,
                'message' => 'SMS sent successfully',
                'reference_id' => $result['message_id'] ?? $result['id'] ?? $result['reference'] ?? null,
                'delivery_status' => 'Sent',
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
        
        // Format the message for comparison
        $formatted_message = formatSMSMessage($message);
        
        // Format phone number for comparison
        $formatted_phone = str_replace([' ', '-', '(', ')', '.'], '', $recipient_number);
        if (substr($formatted_phone, 0, 2) === '09') {
            $formatted_phone = '63' . substr($formatted_phone, 1);
        } elseif (substr($formatted_phone, 0, 1) === '0') {
            $formatted_phone = '63' . substr($formatted_phone, 1);
        } elseif (substr($formatted_phone, 0, 3) === '+63') {
            $formatted_phone = substr($formatted_phone, 1);
        }
        
        // DEDUPLICATION: Check if same SMS was sent to this number in last 1 minute
        // This prevents double-sending from accidental double-clicks or page reloads
        try {
            $check_query = "SELECT sms_id FROM sms_logs 
                            WHERE recipient_number LIKE ? 
                            AND SUBSTRING(message, 1, 100) = SUBSTRING(?, 1, 100)
                            AND status = 'sent'
                            AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                            LIMIT 1";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute(['%' . substr($formatted_phone, -10), $formatted_message]);
            $existing_sms = $check_stmt->fetch();
            
            if ($existing_sms) {
                return [
                    'success' => false,
                    'message' => 'SMS was already sent recently. Please wait 1 minute before sending again.',
                    'duplicate' => true,
                    'existing_id' => $existing_sms['sms_id']
                ];
            }
        } catch (PDOException $e) {
            // Table might not exist or have different structure, continue sending
            error_log("Deduplication check failed: " . $e->getMessage());
        }
        
        // If appointment_id is provided, check if SMS was already sent for this appointment
        if ($appointment_id) {
            $check_query = "SELECT sms_notification_sent FROM appointments WHERE appointment_id = ?";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([$appointment_id]);
            $already_sent = $check_stmt->fetchColumn();
            
            if ($already_sent) {
                return [
                    'success' => false,
                    'message' => 'SMS notification was already sent for this appointment',
                    'duplicate' => true
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
        try {
            $log_query = "INSERT INTO sms_logs (appointment_id, recipient_number, message, status, sent_at) 
                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $status = $sms_result['success'] ? 'sent' : 'failed';
            $log_stmt->execute([$appointment_id, $recipient_number, $formatted_message, $status]);
        } catch (PDOException $e) {
            // Log table might not exist, continue anyway
            error_log("SMS logging failed: " . $e->getMessage());
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

/**
 * Send appointment confirmation SMS
 * @param int $appointment_id Appointment ID
 * @return array Response with status and message
 */
function sendAppointmentConfirmationSMS($appointment_id) {
    try {
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get appointment details
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
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found'];
        }
        
        if (empty($appointment['mobile_number'])) {
            return ['success' => false, 'message' => 'Patient has no mobile number'];
        }
        
        // Format date and time
        $date = date('M j, Y', strtotime($appointment['appointment_date']));
        $time = date('g:i A', strtotime($appointment['appointment_time']));
        
        // Generate message (keep short for IPROG template)
        $message = "Hello {$appointment['first_name']}, your appointment is CONFIRMED for {$date} at {$time}. Please arrive early. Thank you. - Respective Personnel";
        
        return sendSMS($appointment['mobile_number'], $message, $appointment_id);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send appointment reminder SMS
 * @param int $appointment_id Appointment ID
 * @return array Response with status and message
 */
function sendAppointmentReminderSMS($appointment_id) {
    try {
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get appointment details
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
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found'];
        }
        
        if (empty($appointment['mobile_number'])) {
            return ['success' => false, 'message' => 'Patient has no mobile number'];
        }
        
        // Format date and time
        $date = date('M j, Y', strtotime($appointment['appointment_date']));
        $time = date('g:i A', strtotime($appointment['appointment_time']));
        
        // Generate reminder message (keep short for IPROG template)
        $message = "Hello {$appointment['first_name']}, reminder: Appointment on {$date} at {$time}. Please arrive early. Thank you. - Respective Personnel";
        
        return sendSMS($appointment['mobile_number'], $message);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send immunization reminder SMS
 * @param int $patient_id Patient ID
 * @param string $vaccine_name Vaccine name
 * @param string $due_date Due date for immunization
 * @return array Response with status and message
 */
function sendImmunizationReminderSMS($patient_id, $vaccine_name, $due_date = null) {
    try {
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get patient details
        $query = "SELECT u.first_name, u.last_name, u.mobile_number
                  FROM patients p
                  JOIN users u ON p.user_id = u.user_id
                  WHERE p.patient_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return ['success' => false, 'message' => 'Patient not found'];
        }
        
        if (empty($patient['mobile_number'])) {
            return ['success' => false, 'message' => 'Patient has no mobile number'];
        }
        
        // Generate message (keep short for IPROG template)
        if ($due_date) {
            $formatted_date = date('M j, Y', strtotime($due_date));
            $message = "Hello {$patient['first_name']}, your {$vaccine_name} is due on {$formatted_date}. Please visit the Health Center. Thank you. - Respective Personnel";
        } else {
            $message = "Hello {$patient['first_name']}, your {$vaccine_name} is now due. Please visit the Health Center. Thank you. - Respective Personnel";
        }
        
        return sendSMS($patient['mobile_number'], $message);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send general notification SMS to a patient
 * @param int $patient_id Patient ID
 * @param string $message Custom message
 * @return array Response with status and message
 */
function sendPatientNotificationSMS($patient_id, $message) {
    try {
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get patient details
        $query = "SELECT u.first_name, u.last_name, u.mobile_number
                  FROM patients p
                  JOIN users u ON p.user_id = u.user_id
                  WHERE p.patient_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return ['success' => false, 'message' => 'Patient not found'];
        }
        
        if (empty($patient['mobile_number'])) {
            return ['success' => false, 'message' => 'Patient has no mobile number'];
        }
        
        return sendSMS($patient['mobile_number'], $message);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send appointment cancellation SMS
 * @param int $appointment_id Appointment ID
 * @param string $reason Cancellation reason (optional)
 * @return array Response with status and message
 */
function sendAppointmentCancellationSMS($appointment_id, $reason = null) {
    try {
        require_once __DIR__ . '/config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get appointment details
        $query = "SELECT a.*, 
                         u.first_name, u.last_name, u.mobile_number
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.patient_id
                  JOIN users u ON p.user_id = u.user_id
                  WHERE a.appointment_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found'];
        }
        
        if (empty($appointment['mobile_number'])) {
            return ['success' => false, 'message' => 'Patient has no mobile number'];
        }
        
        // Format date
        $date = date('M j, Y', strtotime($appointment['appointment_date']));
        
        // Generate message (keep short for IPROG template)
        $message = "Hello {$appointment['first_name']}, your appointment on {$date} is CANCELLED. Please reschedule. Thank you. - Respective Personnel";
        
        return sendSMS($appointment['mobile_number'], $message);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
} 