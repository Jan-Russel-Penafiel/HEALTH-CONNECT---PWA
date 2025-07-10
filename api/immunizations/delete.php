<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a DELETE request or has the DELETE parameter
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && !isset($_GET['_method']) && $_GET['_method'] !== 'DELETE') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get immunization record ID
$immunization_record_id = isset($_GET['immunization_record_id']) ? (int)$_GET['immunization_record_id'] : 0;

if (!$immunization_record_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Immunization record ID is required']);
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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Health worker record not found']);
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error fetching health worker ID']);
    exit();
}

// Check if immunization record belongs to this health worker
try {
    $query = "SELECT * FROM immunization_records WHERE immunization_record_id = ? AND health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$immunization_record_id, $health_worker_id]);
    
    if ($stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Immunization record not found or not assigned to you']);
        exit();
    }
} catch (PDOException $e) {
    error_log("Error checking immunization record: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error checking immunization record']);
    exit();
}

// Delete immunization record
try {
    $query = "DELETE FROM immunization_records WHERE immunization_record_id = ?";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$immunization_record_id]);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Immunization record deleted successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to delete immunization record']);
    }
} catch (PDOException $e) {
    error_log("Error deleting immunization record: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 