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

// Include email configuration
$emailConfig = require_once '../../includes/config/email_config.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../vendor/autoload.php';

// Function to send approval email
function sendApprovalEmail($email, $firstName, $lastName) {
    global $emailConfig;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $emailConfig['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['smtp_username'];
        $mail->Password   = $emailConfig['smtp_password'];
        $mail->SMTPSecure = $emailConfig['smtp_secure'];
        $mail->Port       = $emailConfig['smtp_port'];

        // Recipients
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        $mail->addAddress($email, $firstName . ' ' . $lastName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Registration Approved - ' . $emailConfig['app_name'];
        
        $loginUrl = $emailConfig['app_url'] . '/pages/login.php';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; padding: 12px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Registration Approved!</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . ',</h2>
                    <p>Great news! Your registration with <strong>' . $emailConfig['app_name'] . '</strong> has been approved by our administrator.</p>
                    <p>You can now access all features of your patient account, including:</p>
                    <ul>
                        <li>📅 Schedule appointments with health workers</li>
                        <li>📋 View your medical records</li>
                        <li>💉 Track your immunization history</li>
                        <li>👤 Update your profile information</li>
                    </ul>
                    <p>Click the button below to log in and get started:</p>
                    <a href="' . $loginUrl . '" class="button">Log In Now</a>
                    <p>If you have any questions or need assistance, please contact us at ' . $emailConfig['support_email'] . '</p>
                    <p>Thank you for choosing ' . $emailConfig['app_name'] . '!</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . $emailConfig['app_name'] . '. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = 'Hello ' . $firstName . ' ' . $lastName . ',\n\n' .
                        'Great news! Your registration with ' . $emailConfig['app_name'] . ' has been approved.\n\n' .
                        'You can now log in at: ' . $loginUrl . '\n\n' .
                        'Thank you for choosing ' . $emailConfig['app_name'] . '!';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

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

// Get and validate JSON data
$json = file_get_contents('php://input');
if (!$json) {
    return_error('No data received');
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    return_error('Invalid JSON data: ' . json_last_error_msg());
}

// Validate required parameters
if (!isset($data['user_id']) || !isset($data['is_approved'])) {
    return_error('Missing required parameters (user_id or is_approved)');
}

// Initialize variables
$user_id = (int)$data['user_id'];
$is_approved = (bool)$data['is_approved'];
$delete_on_disapprove = isset($data['delete_on_disapprove']) ? (bool)$data['delete_on_disapprove'] : false;

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
    
    // Process based on approval action
    if ($is_approved) {
        // Get patient details for email notification
        $stmt = $conn->prepare("SELECT u.email, u.first_name, u.last_name 
                                FROM users u 
                                WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Approve patient
        $stmt = $conn->prepare("UPDATE patients SET is_approved = 1, approved_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Send approval email notification
        $emailSent = false;
        if ($userDetails && !empty($userDetails['email'])) {
            $emailSent = sendApprovalEmail(
                $userDetails['email'],
                $userDetails['first_name'],
                $userDetails['last_name']
            );
        }
        
        $message = 'Patient approved successfully';
        if ($emailSent) {
            $message .= ' and notification email sent';
        } else if ($userDetails && !empty($userDetails['email'])) {
            $message .= ' but failed to send notification email';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message, 
            'user_id' => $user_id,
            'email_sent' => $emailSent
        ]);
    } else if ($delete_on_disapprove) {
        // Delete patient and related records
        $conn->beginTransaction();
        
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
            // Delete appointment reminders or other related records
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
        
        // Delete patient and user records
        $stmt = $conn->prepare("DELETE FROM patients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Patient deleted successfully', 'user_id' => $user_id]);
    } else {
        // Just disapprove
        $stmt = $conn->prepare("UPDATE patients SET is_approved = 0, approved_at = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Patient approval revoked', 'user_id' => $user_id]);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("API Error: " . $e->getMessage());
    return_error('Database error: ' . $e->getMessage(), 500);
} 