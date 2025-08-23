<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers to prevent caching and specify JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include database configuration
require_once '../../includes/config/database.php';

// Function to return JSON error
function return_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    return_error('Unauthorized access. You must be logged in as admin.', 403);
}

// Get user_id from query parameter
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    return_error('Missing or invalid user_id parameter');
}

// Database operations
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if patient exists
    $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        return_error('Patient not found', 404);
    }
    
    $patient_id = $patient['patient_id'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Delete related records in correct order (respecting foreign key constraints)
        
        // First, get all appointment IDs for this patient to delete related SMS logs
        $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $appointment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete SMS logs related to appointments
        if (!empty($appointment_ids)) {
            $placeholders = str_repeat('?,', count($appointment_ids) - 1) . '?';
            try {
                $stmt = $conn->prepare("DELETE FROM sms_logs WHERE appointment_id IN ($placeholders)");
                $stmt->execute($appointment_ids);
            } catch (PDOException $e) {
                // If sms_logs table doesn't exist or column name is different, continue
                error_log("Warning: Could not delete SMS logs: " . $e->getMessage());
            }
        }
        
        // Delete other appointment-related records if they exist
        if (!empty($appointment_ids)) {
            // Delete appointment status updates or other related records
            try {
                $stmt = $conn->prepare("DELETE FROM appointment_reminders WHERE appointment_id IN ($placeholders)");
                $stmt->execute($appointment_ids);
            } catch (PDOException $e) {
                // If table doesn't exist, continue
                error_log("Warning: Could not delete appointment reminders: " . $e->getMessage());
            }
        }
        
        // Delete medical records
        $stmt = $conn->prepare("DELETE FROM medical_records WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        
        // Delete immunization records (and their related SMS logs if any)
        $stmt = $conn->prepare("SELECT immunization_record_id FROM immunization_records WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $immunization_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($immunization_ids)) {
            $placeholders = str_repeat('?,', count($immunization_ids) - 1) . '?';
            // Delete SMS logs related to immunizations if that table exists
            try {
                $stmt = $conn->prepare("DELETE FROM sms_logs WHERE immunization_record_id IN ($placeholders)");
                $stmt->execute($immunization_ids);
            } catch (PDOException $e) {
                // If immunization_record_id column doesn't exist in sms_logs, continue
                error_log("Warning: Could not delete immunization SMS logs: " . $e->getMessage());
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM immunization_records WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        
        // Delete appointments (now that all dependent records are deleted)
        $stmt = $conn->prepare("DELETE FROM appointments WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        
        // Delete any other patient-related records that might exist
        // Delete patient notifications
        try {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            // If notifications table doesn't exist, continue
            error_log("Warning: Could not delete notifications: " . $e->getMessage());
        }
        
        // Delete patient sessions
        try {
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            // If user_sessions table doesn't exist, continue
            error_log("Warning: Could not delete user sessions: " . $e->getMessage());
        }
        
        // Delete patient record
        $stmt = $conn->prepare("DELETE FROM patients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete user record
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Patient and all related records deleted successfully',
            'user_id' => $user_id
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete Patient API Error: " . $e->getMessage());
    return_error('Database error: ' . $e->getMessage(), 500);
}
?>
