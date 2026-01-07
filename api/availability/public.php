<?php
/**
 * Public Health Worker Availability for Patient Booking
 * Returns available dates and time slots for a specific health worker
 * Uses JSON file storage for availability data
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
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get health worker ID from query parameter
    $health_worker_id = isset($_GET['health_worker_id']) ? (int)$_GET['health_worker_id'] : 0;
    
    if ($health_worker_id <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Health worker ID required']);
        exit();
    }
    
    // Get date range - from today to 2 months ahead
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+2 months'));
    
    // Get working hours from settings
    $workingHours = [
        'start' => '08:00',
        'end' => '17:00',
        'interval' => 30
    ];
    
    try {
        $settingsQuery = "SELECT name, value FROM settings 
                         WHERE name IN ('working_hours_start', 'working_hours_end', 'appointment_duration')";
        $settingsStmt = $pdo->query($settingsQuery);
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['name']) {
                case 'working_hours_start':
                    $workingHours['start'] = $row['value'];
                    break;
                case 'working_hours_end':
                    $workingHours['end'] = $row['value'];
                    break;
                case 'appointment_duration':
                    $workingHours['interval'] = (int)$row['value'];
                    break;
            }
        }
    } catch (PDOException $e) {
        // Use defaults
    }
    
    // Load availability from JSON file
    $jsonFile = __DIR__ . '/../../data/availability/' . $health_worker_id . '.json';
    
    $unavailableDates = [];
    $slotLimits = [];
    $defaultSlotLimit = 10;
    
    if (file_exists($jsonFile)) {
        $jsonContent = file_get_contents($jsonFile);
        $savedData = json_decode($jsonContent, true);
        
        if ($savedData) {
            $unavailableDates = $savedData['unavailableDates'] ?? [];
            $slotLimits = $savedData['slotLimits'] ?? [];
            $defaultSlotLimit = $savedData['defaultSlotLimit'] ?? 10;
        }
    }
    
    // Get booked appointments with time slots from database
    $bookedAppointments = [];
    try {
        $query = "SELECT DATE(appointment_date) as date, appointment_time as time, status_id 
                  FROM appointments 
                  WHERE health_worker_id = ? 
                  AND DATE(appointment_date) BETWEEN ? AND ?
                  AND status_id NOT IN (3, 4)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$health_worker_id, $startDate, $endDate]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['date'];
            if (!isset($bookedAppointments[$date])) {
                $bookedAppointments[$date] = [];
            }
            // Handle both TIME format from appointment_time column
            $time = $row['time'];
            if (strlen($time) > 5) {
                $time = substr($time, 0, 5); // Convert HH:MM:SS to HH:MM
            }
            $bookedAppointments[$date][] = $time;
        }
    } catch (PDOException $e) {
        // Continue with empty booked data
    }
    
    // Calculate booked counts per day
    $bookedCounts = [];
    foreach ($bookedAppointments as $date => $times) {
        $bookedCounts[$date] = count($times);
    }
    
    // Generate time slots
    $timeSlots = [];
    $startTime = strtotime($workingHours['start']);
    $endTime = strtotime($workingHours['end']);
    $interval = $workingHours['interval'] * 60;
    
    while ($startTime < $endTime) {
        $timeSlots[] = date('H:i', $startTime);
        $startTime += $interval;
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'unavailableDates' => $unavailableDates,
            'slotLimits' => !empty($slotLimits) ? (object)$slotLimits : new stdClass(),
            'bookedSlots' => !empty($bookedCounts) ? (object)$bookedCounts : new stdClass(),
            'bookedTimes' => !empty($bookedAppointments) ? (object)$bookedAppointments : new stdClass(),
            'defaultSlotLimit' => $defaultSlotLimit,
            'timeSlots' => $timeSlots,
            'workingHours' => $workingHours
        ]
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Public Availability Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
