<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get form data
$patient_id = isset($_POST['patient_id']) ? $_POST['patient_id'] : null;
$health_worker_id = isset($_POST['health_worker_id']) ? $_POST['health_worker_id'] : null;
$appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
$appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// If health_worker_id is not provided, get it from the database
if (!$health_worker_id) {
    try {
        $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($health_worker) {
            $health_worker_id = $health_worker['health_worker_id'];
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Health worker record not found']);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching health worker ID: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error fetching health worker ID']);
        exit();
    }
}

// Validate required fields
if (!$patient_id || !$appointment_date || !$appointment_time || !$health_worker_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Get the status_id for 'Scheduled'
    $query = "SELECT status_id FROM appointment_status WHERE status_name = 'Scheduled'";
    $stmt = $pdo->query($query);
    $status_id = $stmt->fetchColumn();
    
    if (!$status_id) {
        $status_id = 1; // Default to 1 if not found
    }
    
    // Insert the appointment
    $query = "INSERT INTO appointments (patient_id, health_worker_id, appointment_date, appointment_time, status_id, reason, notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $patient_id,
        $health_worker_id,
        $appointment_date,
        $appointment_time,
        $status_id,
        $reason,
        $notes
    ]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Appointment scheduled successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to schedule appointment']);
    }
} catch (PDOException $e) {
    error_log("Error creating appointment: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 