<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker or admin
if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get health worker ID
try {
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Health worker not found']);
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// Get and validate input
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$visit_date = $_POST['visit_date'] ?? '';
$chief_complaint = trim($_POST['chief_complaint'] ?? '');
$diagnosis = trim($_POST['diagnosis'] ?? '');
$treatment = trim($_POST['treatment'] ?? '');
$prescription = trim($_POST['prescription'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$follow_up_date = $_POST['follow_up_date'] ?? '';

// Validation
$errors = [];

if (!$patient_id) {
    $errors[] = 'Patient ID is required';
}

if (empty($visit_date)) {
    $errors[] = 'Visit date is required';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
    $errors[] = 'Invalid visit date format';
}

if (empty($diagnosis)) {
    $errors[] = 'Diagnosis is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit();
}

// Insert medical record
try {
    $pdo->beginTransaction();
    
    // Verify patient exists
    $verify_query = "SELECT patient_id FROM patients WHERE patient_id = ?";
    $verify_stmt = $pdo->prepare($verify_query);
    $verify_stmt->execute([$patient_id]);
    
    if (!$verify_stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Insert medical record
    $query = "INSERT INTO medical_records 
              (patient_id, health_worker_id, visit_date, chief_complaint, diagnosis, treatment, prescription, notes, follow_up_date, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $patient_id,
        $health_worker_id,
        $visit_date,
        $chief_complaint,
        $diagnosis,
        $treatment,
        $prescription,
        $notes,
        $follow_up_date ?: null
    ]);
    
    $record_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Medical record added successfully',
        'record_id' => $record_id
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error adding medical record: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add medical record']);
}
?>
