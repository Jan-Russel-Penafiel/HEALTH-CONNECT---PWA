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
    // Query to get medical records for the patient
    $query = "SELECT mr.*, 
              CONCAT(hw_user.first_name, ' ', hw_user.last_name) as health_worker_name
              FROM medical_records mr
              JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              JOIN users hw_user ON hw.user_id = hw_user.user_id
              WHERE mr.patient_id = ?
              ORDER BY mr.visit_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($records);
    
} catch (PDOException $e) {
    error_log("Error fetching medical history: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 