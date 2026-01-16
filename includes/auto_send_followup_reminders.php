<?php
/**
 * Automatic Follow-up Reminder System
 * This script automatically sends SMS reminders for follow-up appointments due today
 * It runs on every page load in the health worker section
 */

// Only run for health workers and admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    return;
}

// Check if reminders were already processed today (use session to avoid multiple checks per session)
$today_key = 'followup_reminders_checked_' . date('Y-m-d');
if (isset($_SESSION[$today_key]) && $_SESSION[$today_key] === true) {
    return; // Already processed today in this session
}

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/sms.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if SMS notifications are enabled
    $sms_query = "SELECT value FROM settings WHERE name = 'enable_sms_notifications'";
    $sms_stmt = $conn->query($sms_query);
    $sms_enabled = $sms_stmt->fetchColumn();
    
    if ($sms_enabled != '1') {
        $_SESSION[$today_key] = true;
        return; // SMS not enabled
    }
    
    // Get all follow-ups due today that haven't been reminded yet
    $today = date('Y-m-d');
    $query = "SELECT 
                mr.record_id,
                mr.patient_id,
                mr.follow_up_date,
                mr.notes,
                u.first_name,
                u.last_name,
                u.mobile_number
              FROM medical_records mr
              JOIN patients p ON mr.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              WHERE mr.follow_up_date = ?
              AND u.mobile_number IS NOT NULL
              AND u.mobile_number != ''";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$today]);
    $followup_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reminders_sent = 0;
    $reminder_flag = 'reminder_sent_' . $today;
    
    foreach ($followup_records as $record) {
        // Decode notes to check if reminder was already sent
        $notes_data = json_decode($record['notes'], true);
        
        // Skip if reminder already sent today
        if ($notes_data && isset($notes_data[$reminder_flag]) && $notes_data[$reminder_flag] === true) {
            continue;
        }
        
        // Extract follow-up message if exists
        $follow_up_message = '';
        if ($notes_data && isset($notes_data['follow_up_message'])) {
            $follow_up_message = $notes_data['follow_up_message'];
        }
        
        // Prepare SMS message
        $patient_name = $record['first_name'];
        $doctor_note = !empty($follow_up_message) ? " Doctor's note: " . $follow_up_message : "";
        $message = "Hello {$patient_name}, this is a reminder for your scheduled follow-up checkup today at Brgy. Poblacion Health Center.{$doctor_note} Thank you. - Respective Personnel";
        
        // Send SMS
        $sms_result = sendSMS($record['mobile_number'], $message);
        
        if ($sms_result) {
            // Update notes to mark reminder as sent
            if (!is_array($notes_data)) {
                $notes_data = [];
            }
            $notes_data[$reminder_flag] = true;
            $updated_notes = json_encode($notes_data);
            
            $update_query = "UPDATE medical_records SET notes = ? WHERE record_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$updated_notes, $record['record_id']]);
            
            $reminders_sent++;
            
            // Wait 20 seconds before sending next SMS to avoid rate limiting
            if ($reminders_sent < count($followup_records)) {
                sleep(20);
            }
        }
    }
    
    // Mark as processed for today in this session
    $_SESSION[$today_key] = true;
    
    // Log the activity
    if ($reminders_sent > 0) {
        error_log("Auto-sent {$reminders_sent} follow-up reminders for " . date('Y-m-d'));
    }
    
} catch (Exception $e) {
    error_log("Error in auto_send_followup_reminders: " . $e->getMessage());
    // Don't break the page if there's an error
}
?>
