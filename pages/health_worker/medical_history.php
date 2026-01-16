<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker or admin
if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get patient ID from request
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Patient ID is required']);
    exit();
}

try {
    // First, get patient info to verify the patient exists
    $patient_query = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as patient_name
                      FROM patients p
                      JOIN users u ON p.user_id = u.user_id
                      WHERE p.patient_id = ?";
    $patient_stmt = $pdo->prepare($patient_query);
    $patient_stmt->execute([$patient_id]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Patient not found', 'patient_id' => $patient_id]);
        exit();
    }
    
    // Query to get medical records for the patient
    $query = "SELECT mr.*, 
              COALESCE(CONCAT(hw_user.first_name, ' ', hw_user.last_name), 'Unknown') as health_worker_name
              FROM medical_records mr
              LEFT JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              LEFT JOIN users hw_user ON hw.user_id = hw_user.user_id
              WHERE mr.patient_id = ?
              ORDER BY mr.visit_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response with patient info
    header('Content-Type: application/json');
    echo json_encode([
        'patient' => $patient,
        'records' => $records,
        'count' => count($records)
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching medical history: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 