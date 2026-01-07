<?php
/**
 * Get Health Worker Availability Data
 * Returns unavailable dates, slot limits, and booked counts from JSON file
 */

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header first
header('Content-Type: application/json');

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once __DIR__ . '/../../includes/config/database.php';
    
    // Check if user is logged in and is health worker
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'health_worker') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get health worker ID
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Health worker not found']);
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
    
    // JSON file path for this health worker
    $dataDir = __DIR__ . '/../../data/availability';
    $jsonFile = $dataDir . '/' . $health_worker_id . '.json';
    
    // Default data structure
    $availabilityData = [
        'unavailableDates' => [],
        'slotLimits' => new stdClass(),
        'defaultSlotLimit' => 10
    ];
    
    // Load from JSON file if exists
    if (file_exists($jsonFile)) {
        $jsonContent = file_get_contents($jsonFile);
        $savedData = json_decode($jsonContent, true);
        
        if ($savedData) {
            $availabilityData['unavailableDates'] = $savedData['unavailableDates'] ?? [];
            // Handle slotLimits - ensure it's an associative array/object, not an empty indexed array
            $slotLimits = $savedData['slotLimits'] ?? [];
            if (is_array($slotLimits) && !empty($slotLimits) && array_keys($slotLimits) !== range(0, count($slotLimits) - 1)) {
                // It's an associative array
                $availabilityData['slotLimits'] = (object)$slotLimits;
            } else {
                $availabilityData['slotLimits'] = new stdClass();
            }
            $availabilityData['defaultSlotLimit'] = $savedData['defaultSlotLimit'] ?? 10;
        }
    }
    
    // Get booked appointments from database
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t', strtotime('+2 months'));
    
    $bookedData = [];
    try {
        $query = "SELECT DATE(appointment_date) as date, COUNT(*) as booked_count 
                  FROM appointments 
                  WHERE health_worker_id = ? 
                  AND appointment_date BETWEEN ? AND ?
                  AND status_id NOT IN (3, 4)
                  GROUP BY DATE(appointment_date)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$health_worker_id, $startDate, $endDate]);
        $bookedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // Continue with empty booked data
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'unavailableDates' => $availabilityData['unavailableDates'],
            'slotLimits' => $availabilityData['slotLimits'],
            'bookedSlots' => !empty($bookedData) ? (object)$bookedData : new stdClass(),
            'defaultSlotLimit' => $availabilityData['defaultSlotLimit'],
            'healthWorkerId' => $health_worker_id
        ]
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Availability API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
