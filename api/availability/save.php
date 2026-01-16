<?php
/**
 * Save Health Worker Availability Settings
 * Handles saving unavailable dates and slot limits to JSON file
 */

// Start output buffering
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
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }
    
    // JSON file path for this health worker
    $dataDir = __DIR__ . '/../../data/availability';
    $jsonFile = $dataDir . '/' . $health_worker_id . '.json';
    
    // Create directory if it doesn't exist
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    // Load existing data or create new
    $existingData = [
        'unavailableDates' => [],
        'slotLimits' => [],
        'timeSlotLimits' => [], // Per-time-slot limits: date => [time => limit]
        'defaultSlotLimit' => 10,
        'lastUpdated' => null
    ];
    
    if (file_exists($jsonFile)) {
        $jsonContent = file_get_contents($jsonFile);
        $savedData = json_decode($jsonContent, true);
        if ($savedData) {
            $existingData = array_merge($existingData, $savedData);
            // Ensure timeSlotLimits exists
            if (!isset($existingData['timeSlotLimits'])) {
                $existingData['timeSlotLimits'] = [];
            }
        }
    }
    
    // Handle single date update (from modal)
    if (isset($input['date']) && isset($input['is_available'])) {
        $date = $input['date'];
        $isAvailable = $input['is_available'];
        $slotLimit = $input['slot_limit'] ?? $existingData['defaultSlotLimit'];
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit();
        }
        
        if (!$isAvailable) {
            // Add to unavailable dates if not already there
            if (!in_array($date, $existingData['unavailableDates'])) {
                $existingData['unavailableDates'][] = $date;
            }
            // Remove from slot limits
            unset($existingData['slotLimits'][$date]);
            unset($existingData['timeSlotLimits'][$date]);
        } else {
            // Remove from unavailable dates
            $existingData['unavailableDates'] = array_values(array_filter(
                $existingData['unavailableDates'],
                function($d) use ($date) { return $d !== $date; }
            ));
            // Set slot limit
            $existingData['slotLimits'][$date] = (int)$slotLimit;
            
            // Handle time slot limits if provided
            if (isset($input['time_slot_limits']) && is_array($input['time_slot_limits'])) {
                $existingData['timeSlotLimits'][$date] = [];
                foreach ($input['time_slot_limits'] as $time => $limit) {
                    if (preg_match('/^\d{2}:\d{2}$/', $time) && (int)$limit > 0) {
                        $existingData['timeSlotLimits'][$date][$time] = (int)$limit;
                    }
                }
            }
        }
    }
    // Handle bulk update (if sending full arrays)
    else if (isset($input['unavailableDates']) || isset($input['slotLimits'])) {
        if (isset($input['unavailableDates'])) {
            $existingData['unavailableDates'] = array_values(array_filter($input['unavailableDates'], function($date) {
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
            }));
        }
        if (isset($input['slotLimits'])) {
            $existingData['slotLimits'] = [];
            foreach ($input['slotLimits'] as $date => $limit) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $existingData['slotLimits'][$date] = (int)$limit;
                }
            }
        }
    }
    
    // Update timestamp
    $existingData['lastUpdated'] = date('c');
    
    // Save to JSON file
    $jsonResult = file_put_contents($jsonFile, json_encode($existingData, JSON_PRETTY_PRINT));
    
    if ($jsonResult === false) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to save data to file']);
        exit();
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Availability saved successfully',
        'data' => $existingData
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Save Availability Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save availability: ' . $e->getMessage()]);
}
