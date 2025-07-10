<?php
/**
 * SMS functionality using PhilSMS API
 * This file provides functions to send SMS notifications
 */

// Function to send SMS using PhilSMS API
function sendSMS($recipient_number, $message, $appointment_id = null) {
    global $pdo;
    
    try {
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

        // Get SMS settings from database
        if (!isset($pdo)) {
            require_once __DIR__ . '/../includes/config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
        }
        
        $query = "SELECT value FROM settings WHERE name = ?";
        $stmt = $pdo->prepare($query);
        
        // Get API key
        $stmt->execute(['sms_api_key']);
        $api_key = $stmt->fetchColumn();
        
        // Get sender ID
        $stmt->execute(['sms_sender_id']);
        $sender_id = $stmt->fetchColumn();
        
        // Check if SMS notifications are enabled
        $stmt->execute(['enable_sms_notifications']);
        $sms_enabled = $stmt->fetchColumn();
        
        if ($sms_enabled != '1') {
            return [
                'success' => false,
                'message' => 'SMS notifications are disabled in settings'
            ];
        }
        
        // Format phone number (remove any non-numeric characters)
        $recipient_number = preg_replace('/[^0-9]/', '', $recipient_number);
        
        // Add country code if not present (assuming Philippines +63)
        if (strlen($recipient_number) == 10 && substr($recipient_number, 0, 1) == '9') {
            $recipient_number = '63' . $recipient_number;
        } else if (strlen($recipient_number) == 11 && substr($recipient_number, 0, 1) == '0') {
            $recipient_number = '63' . substr($recipient_number, 1);
        }
        
        // PhilSMS API endpoint
        $url = 'https://app.philsms.com/api/v3/sms/send';
        
        // Prepare data for API request
        $data = [
            'recipient' => $recipient_number,
            'message' => $message,
            'sender_id' => $sender_id
        ];
        
        // Initialize cURL session
        $ch = curl_init($url);
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        
        // Execute cURL request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Close cURL session
        curl_close($ch);
        
        // Parse response
        $response_data = json_decode($response, true);
        
        // Log SMS in database
        if ($appointment_id) {
            $log_query = "INSERT INTO sms_logs (appointment_id, recipient_number, message, status, sent_at) 
                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $status = ($http_code == 200 && isset($response_data['success']) && $response_data['success']) ? 'sent' : 'failed';
            $log_stmt->execute([$appointment_id, $recipient_number, $message, $status]);
        }
        
        // Return response
        if ($http_code == 200 && isset($response_data['success']) && $response_data['success']) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'data' => $response_data
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . ($response_data['message'] ?? 'Unknown error'),
                'data' => $response_data
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
} 