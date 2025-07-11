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
        // Approve patient
        $stmt = $conn->prepare("UPDATE patients SET is_approved = 1, approved_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Patient approved successfully', 'user_id' => $user_id]);
    } else if ($delete_on_disapprove) {
        // Delete patient and related records
        $conn->beginTransaction();
        
        // Delete related records
        $conn->exec("DELETE FROM medical_records WHERE patient_id = $patient_id");
        $conn->exec("DELETE FROM appointments WHERE patient_id = $patient_id");
        $conn->exec("DELETE FROM immunization_records WHERE patient_id = $patient_id");
        
        // Delete patient and user
        $conn->exec("DELETE FROM patients WHERE user_id = $user_id");
        $conn->exec("DELETE FROM users WHERE user_id = $user_id");
        
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