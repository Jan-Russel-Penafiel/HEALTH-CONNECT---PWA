<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get health worker ID from the database
try {
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        // If no health worker record found, redirect to login
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

// Handle search and filters
$where_clauses = ["a.health_worker_id = ?"];
$params = [$health_worker_id];

try {
    // Get appointments
    $query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.notes, a.status_id, a.reason,
                     a.patient_id, u.first_name, u.last_name, u.email, u.mobile_number as patient_phone,
                     s.status_name as status,
                     (SELECT mr.follow_up_date FROM medical_records mr WHERE mr.patient_id = a.patient_id ORDER BY mr.created_at DESC LIMIT 1) as last_follow_up_date
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.health_worker_id = ? AND a.status_id != 3
              ORDER BY CASE WHEN s.status_name = 'Scheduled' THEN 0 ELSE 1 END, a.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}

// Get patients with upcoming follow-up checkups (within 2 days)
$upcoming_followups = [];
try {
    $followup_query = "SELECT mr.record_id, mr.follow_up_date, mr.notes, mr.patient_id,
                              u.first_name, u.last_name, u.mobile_number,
                              DATEDIFF(mr.follow_up_date, CURDATE()) as days_until_followup
                       FROM medical_records mr
                       JOIN patients p ON mr.patient_id = p.patient_id
                       JOIN users u ON p.user_id = u.user_id
                       WHERE mr.health_worker_id = ?
                       AND mr.follow_up_date IS NOT NULL
                       AND mr.follow_up_date >= CURDATE()
                       AND DATEDIFF(mr.follow_up_date, CURDATE()) <= 2
                       ORDER BY mr.follow_up_date ASC";
    
    $stmt = $pdo->prepare($followup_query);
    $stmt->execute([$health_worker_id]);
    $upcoming_followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON from notes field for each record
    foreach ($upcoming_followups as &$followup) {
        if (!empty($followup['notes'])) {
            $notes_data = json_decode($followup['notes'], true);
            if (is_array($notes_data) && isset($notes_data['follow_up_message'])) {
                $followup['follow_up_message'] = $notes_data['follow_up_message'];
            } else {
                $followup['follow_up_message'] = '';
            }
        } else {
            $followup['follow_up_message'] = '';
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching upcoming follow-ups: " . $e->getMessage());
    $upcoming_followups = [];
}

// Get today's appointments for banner (sorted by time ascending)
$today_appointments = [];
$today_count = 0;
try {
    $today_query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.reason,
                           u.first_name, u.last_name, s.status_name as status
                    FROM appointments a 
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users u ON p.user_id = u.user_id
                    JOIN appointment_status s ON a.status_id = s.status_id
                    WHERE a.health_worker_id = ? 
                    AND DATE(a.appointment_date) = CURDATE()
                    AND a.status_id != 3
                    ORDER BY a.appointment_time ASC";
    
    $stmt = $pdo->prepare($today_query);
    $stmt->execute([$health_worker_id]);
    $today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today_count = count($today_appointments);
} catch (PDOException $e) {
    error_log("Error fetching today's appointments: " . $e->getMessage());
    $today_appointments = [];
    $today_count = 0;
}

// Get appointment slots
$working_hours = [
    'start' => '09:00',
    'end' => '17:00',
    'interval' => 30 // minutes
];

try {
    $settings_query = "SELECT * FROM settings WHERE name IN ('working_hours_start', 'working_hours_end', 'appointment_duration')";
    $stmt = $pdo->query($settings_query);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (isset($settings['working_hours_start'])) {
        $working_hours['start'] = $settings['working_hours_start'];
    }
    if (isset($settings['working_hours_end'])) {
        $working_hours['end'] = $settings['working_hours_end'];
    }
    if (isset($settings['appointment_duration'])) {
        $working_hours['interval'] = (int)$settings['appointment_duration'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

// Get patients list for the modal
try {
    $query = "SELECT p.patient_id, u.first_name, u.last_name, u.email 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE u.is_active = 1 
              ORDER BY u.last_name, u.first_name";
    $stmt = $pdo->query($query);
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients list: " . $e->getMessage());
    $patients_list = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- FullCalendar JS (v6 doesn't need separate CSS) -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <!-- Add jsQR library for QR code scanning -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <!-- jsPDF for printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        /* Desktop Table Layout */
        @media (min-width: 992px) {
            .appointments-grid {
                display: none;
            }
            
            .appointments-table-container {
                display: block;
            }
            
            .appointments-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .appointments-table th,
            .appointments-table td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            .appointments-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #333;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .appointments-table tbody tr {
                transition: all 0.2s ease;
            }
            
            .appointments-table tbody tr:hover {
                background-color: #f8f9fa;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .appointments-table tbody tr.highlighted {
                background-color: #fff3cd;
                box-shadow: 0 0 15px rgba(255, 193, 7, 0.5);
                animation: highlight-pulse 2s ease-in-out;
            }
            
            .table-patient-name {
                font-weight: 600;
                color: #333;
            }
            
            .table-contact-info {
                font-size: 0.9rem;
                color: #666;
            }
            
            .table-datetime {
                font-weight: 500;
                color: #2c3e50;
            }
            
            .table-date {
                color: #666;
                font-size: 0.9rem;
            }
            
            .table-status-badge {
                padding: 0.4rem 0.8rem;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.8rem;
                text-transform: uppercase;
                white-space: nowrap;
            }
            
            .table-actions {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            
            .table-actions .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                margin-right: 0.25rem;
                margin-bottom: 0.25rem;
            }
            
            .btn-info {
                background: #17a2b8;
                border: none;
                color: white;
            }
            
            .btn-info:hover {
                background: #138496;
                color: white;
            }            .table-reason {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }

        /* Mobile Card Layout */
        @media (max-width: 991px) {
            .appointments-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.5rem;
                padding: 1.5rem 0;
            }
            
            .appointments-table-container {
                display: none;
            }
        }

        .appointment-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s ease;
        }

        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .appointment-card.highlighted {
            background-color: #fff3cd;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.5);
            animation: highlight-pulse 2s ease-in-out;
        }
        
        /* Highlight for table rows */
        tr.highlighted {
            background-color: #fff3cd !important;
            animation: highlight-pulse 2s ease-in-out;
        }

        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); }
            50% { box-shadow: 0 0 25px rgba(255, 193, 7, 0.8); }
            100% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); }
        }

        .appointment-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .appointment-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .appointment-status {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .status-badge.scheduled,
        .table-status-badge.scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.confirmed,
        .table-status-badge.confirmed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.done,
        .table-status-badge.done {
            background: #f5f5f5;
            color: #616161;
        }

        .status-badge.cancelled,
        .table-status-badge.cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .status-badge.no-show,
        .table-status-badge.no-show {
            background: #fce4ec;
            color: #c2185b;
        }

        .patient-info {
            margin-bottom: 1rem;
        }

        .patient-info h3 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .contact-details {
            font-size: 0.9rem;
            color: #666;
        }

        .contact-details div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .appointment-reason {
            margin: 1rem 0;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .appointment-reason p {
            margin: 0.5rem 0 0 0;
            color: #666;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .appointment-actions .btn {
            flex: 1;
            min-width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        @media (min-width: 992px) {
            .empty-state {
                grid-column: 1 / -1;
            }
        }

        .empty-state i {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }

        .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .appointment-actions {
                flex-direction: column;
            }

            .appointment-actions .btn {
                width: 100%;
            }
        }
        
        .btn-today {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }
        
        .btn-today:hover {
            background: linear-gradient(135deg, #f57c00, #ef6c00);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4);
        }
        
        .btn-today .badge {
            background: white;
            color: #ff9800;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 4px;
        }
        
        .btn-scan {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-scan:hover {
            background: #138496;
            color: white;
        }
        
        /* QR Scanner Modal Styles */
        #qrScannerModal {
            z-index: 1050;
        }
        
        #qrScannerModal .modal-content {
            max-width: 500px;
            width: 95%;
            margin: 1rem auto;
            border-radius: 12px;
            overflow: hidden;
        }
        
                 #qrScannerModal .modal-header {
            background-color: #28a745;
            color: white;
            border-bottom: none;
            padding: 0.75rem 1rem;
        }
        
        #qrScannerModal .modal-close {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.5rem;
            line-height: 1;
            transition: color 0.2s;
        }
        
        #qrScannerModal .modal-close:hover {
            color: white;
        }
        
                 #qrScannerModal .modal-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            margin: 0;
            color: white;
        }
        
        #qrScannerModal .modal-body {
            padding: 1rem;
        }
        
        #qrScannerModal .modal-footer {
            border-top: none;
            padding: 0.75rem 1rem 1rem;
        }
        
        #qrVideo {
            width: 100%;
            border-radius: 8px;
            margin: 0;
            background-color: #f1f1f1;
        }
        
        #qrResult {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 8px;
            display: none;
        }
        
        #qrResult.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        #qrResult.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 576px) {
            #qrScannerModal .modal-content {
                width: 92%;
                margin: 0.5rem auto;
            }
            
            #qrScannerModal .modal-body {
                padding: 0.75rem;
            }
            
            .scanner-container {
                max-width: 100%;
                margin: 0;
            }
            
            .scan-overlay {
                width: 220px;
                height: 220px;
            }
        }

        .scanner-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            aspect-ratio: 4/3;
        }

        .scanner-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: #f8f9fa;
        }

        #qrCanvas {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .scan-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            border: 3px solid #4CAF50;
            border-radius: 12px;
            box-shadow: 0 0 0 100vmax rgba(0, 0, 0, 0.3);
            pointer-events: none;
        }

        .scan-overlay::before {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: #4CAF50;
            border-style: solid;
            border-width: 3px 0 0 3px;
            top: -3px;
            left: -3px;
            border-radius: 8px 0 0 0;
        }

        .scan-overlay::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: #4CAF50;
            border-style: solid;
            border-width: 3px 3px 0 0;
            top: -3px;
            right: -3px;
            border-radius: 0 8px 0 0;
        }

        .scan-overlay-corners::before {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: #4CAF50;
            border-style: solid;
            border-width: 0 0 3px 3px;
            bottom: -3px;
            left: -3px;
            border-radius: 0 0 0 8px;
        }

        .scan-overlay-corners::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border-color: #4CAF50;
            border-style: solid;
            border-width: 0 3px 3px 0;
            bottom: -3px;
            right: -3px;
            border-radius: 0 0 8px 0;
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #4CAF50;
            top: 50%;
            animation: scan 2s linear infinite;
        }

        @keyframes scan {
            0% {
                transform: translateY(-140px);
            }
            50% {
                transform: translateY(140px);
            }
            100% {
                transform: translateY(-140px);
            }
        }

        .scanner-instructions {
            text-align: center;
            margin: 1rem 0;
            color: #495057;
            font-size: 0.9rem;
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        /* =========================================
           CALENDAR AVAILABILITY SECTION STYLES
           ========================================= */
        .calendar-availability-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header .btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        
        .section-header .btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 0;
        }
        
        @media (max-width: 1100px) {
            .calendar-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .calendar-wrapper {
            padding: 20px;
            border-right: 1px solid #eee;
        }
        
        @media (max-width: 1100px) {
            .calendar-wrapper {
                border-right: none;
                border-bottom: 1px solid #eee;
            }
        }
        
        .calendar-sidebar {
            padding: 20px;
            background: #fafafa;
        }
        
        .sidebar-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sidebar-card h4 {
            margin: 0 0 12px 0;
            color: #333;
            font-size: 0.95rem;
            padding-bottom: 8px;
            border-bottom: 2px solid #4CAF50;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            flex-shrink: 0;
        }
        
        .legend-available { background: #4CAF50; }
        .legend-unavailable { background: #f44336; }
        .legend-limited { background: #ff9800; }
        .legend-full { background: #9e9e9e; }
        
        .quick-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .stat-item {
            text-align: center;
            padding: 12px 8px;
            background: #f5f5f5;
            border-radius: 6px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #666;
            margin-top: 4px;
        }
        
        /* FullCalendar Customizations */
        #availabilityCalendar .fc-toolbar-title {
            font-size: 1.2rem !important;
        }
        
        #availabilityCalendar .fc-button-primary {
            background-color: #4CAF50 !important;
            border-color: #4CAF50 !important;
        }
        
        #availabilityCalendar .fc-button-primary:hover {
            background-color: #45a049 !important;
            border-color: #45a049 !important;
        }
        
        #availabilityCalendar .fc-day-today {
            background: #e8f5e9 !important;
        }
        
        .fc-daygrid-day.available-date {
            background: #e8f5e9 !important;
            cursor: pointer;
        }
        
        .fc-daygrid-day.unavailable-date {
            background: #ffebee !important;
            cursor: pointer;
        }
        
        .fc-daygrid-day.unavailable-date .fc-daygrid-day-number {
            color: #c62828 !important;
            text-decoration: line-through;
        }
        
        .fc-daygrid-day.limited-slots {
            background: #fff3e0 !important;
        }
        
        .fc-daygrid-day.full-slots {
            background: #eeeeee !important;
        }
        
        .slot-indicator {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 8px;
            text-align: center;
            margin: 2px 4px;
            display: inline-block;
        }
        
        .slots-available { background: #4CAF50; color: white; }
        .slots-limited { background: #ff9800; color: white; }
        .slots-full { background: #9e9e9e; color: white; }
        .slots-unavailable { background: #f44336; color: white; }
        
        /* Date Appointments List Styles */
        .appointment-list-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #4CAF50;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        
        .appointment-list-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .appointment-list-item.status-scheduled {
            border-left-color: #2196F3;
        }
        
        .appointment-list-item.status-confirmed {
            border-left-color: #4CAF50;
        }
        
        .appointment-list-item.status-done {
            border-left-color: #9e9e9e;
        }
        
        .appointment-list-item.status-cancelled {
            border-left-color: #f44336;
        }
        
        .appointment-list-time {
            font-weight: 600;
            font-size: 0.95rem;
            color: #333;
            min-width: 80px;
        }
        
        .appointment-list-details {
            flex: 1;
            margin-left: 15px;
        }
        
        .appointment-list-patient {
            font-weight: 500;
            color: #333;
            margin-bottom: 3px;
        }
        
        .appointment-list-reason {
            font-size: 0.85rem;
            color: #666;
        }
        
        .appointment-list-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .no-appointments {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .no-appointments i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Availability Modal Styles */
        .availability-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
        }
        
        .availability-modal-overlay.active {
            display: flex;
        }
        
        .availability-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .availability-modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .availability-modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .availability-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .availability-modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .availability-modal-body {
            padding: 20px;
        }
        
        .availability-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #fafafa;
            border-radius: 0 0 12px 12px;
        }
        
        .selected-date-display {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .selected-date-display .date-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .availability-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .availability-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .availability-option:hover {
            border-color: #4CAF50;
        }
        
        .availability-option.selected {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .availability-option input[type="radio"] {
            display: none;
        }
        
        .option-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .option-available .option-icon {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .option-unavailable .option-icon {
            background: #ffebee;
            color: #c62828;
        }
        
        .option-details h4 {
            margin: 0 0 4px 0;
            color: #333;
            font-size: 0.95rem;
        }
        
        .option-details p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        .slot-config-section {
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .slot-config-section.hidden {
            display: none;
        }
        
        .slot-config-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .slot-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .slot-input-group input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .slot-input-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .slot-presets {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .slot-preset-btn {
            padding: 6px 14px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .slot-preset-btn:hover,
        .slot-preset-btn.active {
            border-color: #4CAF50;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Time Slots Distribution Styles */
        .time-slots-distribution {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .time-slot-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }
        
        .time-slot-item:hover {
            border-color: #4CAF50;
        }
        
        .time-slot-item.has-slots {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .time-slot-label {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 500;
            color: #333;
        }
        
        .time-slot-input {
            width: 50px;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .time-slot-input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .slot-distribution-summary {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            margin-top: 10px;
            background: #f5f5f5;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .allocated-count strong {
            color: #4CAF50;
        }
        
        .allocated-count.over-limit strong {
            color: #f44336;
        }
        
        .remaining-count strong {
            color: #666;
        }
        
        /* Filter Toolbar Styles */
        .filter-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-print {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        
        .btn-print:hover {
            background: #1976D2;
        }
        
        .appointment-row-hidden {
            display: none !important;
        }
        
        @media (max-width: 768px) {
            .filter-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
        }
        
        /* Tab Navigation for switching views */
        .view-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .view-tab {
            padding: 12px 24px;
            border: none;
            background: #f5f5f5;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-tab:hover {
            background: #e8f5e9;
        }
        
        .view-tab.active {
            background: #4CAF50;
            color: white;
        }
        
        .view-content {
            display: none;
        }
        
        .view-content.active {
            display: block;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #4CAF50;
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(150%);
            transition: transform 0.3s ease-in-out;
            max-width: 90%;
        }
        
        @media (max-width: 576px) {
            .notification {
                right: 5%;
                left: 5%;
                width: 90%;
                padding: 12px 15px;
            }
            
            .scanner-instructions {
                margin: 0.75rem 0;
                padding: 0.5rem;
            }
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification i {
            font-size: 1.2em;
        }

        .appointment-info {
            flex: 1;
        }

        .appointment-info h4 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
        }

        .appointment-info p {
            margin: 0.5rem 0;
            display: flex;
            gap: 0.5rem;
        }

        .appointment-info strong {
            min-width: 100px;
            color: #2c3e50;
        }

        @media (max-width: 576px) {
            .scan-result-container {
                flex-direction: column;
            }

            .qr-preview {
                flex: 0 0 auto;
                width: 100%;
            }
        }
        

    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../../includes/today_appointments_banner.php'; ?>

    <!-- Toast container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
        <div id="notificationToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i id="toastIcon" class="fas me-2"></i>
                    <span id="toastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <!-- QR Scan Success Notification -->
    <div id="scanSuccessNotification" class="notification">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>QR Code Scanned!</strong>
            <p class="mb-0">Appointment has been highlighted.</p>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Appointments & Availability</h1>
            <div class="header-actions">
                <?php if ($today_count > 0): ?>
                <button class="btn btn-today" onclick="openTodayAppointmentsModal()">
                    <i class="fas fa-calendar-day"></i> Today's <span class="badge"><?php echo $today_count; ?></span>
                </button>
                <?php endif; ?>
                <a href="done_appointments.php" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
                <button class="btn-scan" onclick="showQRScanner()">
                    <i class="fas fa-qrcode"></i> Scan QR
                </button>
            </div>
        </div>

        <!-- View Tabs -->
        <div class="view-tabs">
            <button class="view-tab active" onclick="switchView('appointments')">
                <i class="fas fa-list"></i> Appointments
            </button>
            <button class="view-tab" onclick="switchView('availability')">
                <i class="fas fa-calendar-alt"></i> Manage Availability
            </button>
        </div>

        <!-- Appointments View -->
        <div id="appointmentsView" class="view-content active">
            <!-- Filter Section -->
            <div class="filter-toolbar">
                <div class="filter-group">
                    <label for="searchPatient"><i class="fas fa-search"></i> Search:</label>
                    <input type="text" id="searchPatient" class="filter-select" placeholder="Search by patient name..." oninput="filterAppointments()" style="min-width: 200px;">
                </div>
                <div class="filter-group">
                    <label for="dateFilter"><i class="fas fa-calendar"></i> Filter by Date:</label>
                    <input type="date" id="dateFilter" class="filter-select" onchange="filterAppointments()">
                </div>
                <div class="filter-group">
                    <label for="timeFilter"><i class="fas fa-clock"></i> Filter by Time:</label>
                    <select id="timeFilter" class="filter-select" onchange="filterAppointments()">
                        <option value="all">All Times</option>
                        <?php
                        $start = strtotime($working_hours['start']);
                        $end = strtotime($working_hours['end']);
                        $interval = $working_hours['interval'] * 60;
                        for ($time = $start; $time < $end; $time += $interval) {
                            echo '<option value="' . date('H:i', $time) . '">' . date('g:i A', $time) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-print" onclick="printFilteredAppointments()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Desktop Table Layout -->
            <div class="appointments-table-container">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>There are no appointments matching your search criteria.</p>
            </div>
            <?php else: ?>
            <table class="appointments-table" id="appointmentsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): 
                        $timeValue = date('H:i', strtotime($appointment['appointment_time']));
                        $dateValue = date('Y-m-d', strtotime($appointment['appointment_date']));
                    ?>
                    <tr class="<?php echo strtolower($appointment['status']); ?>" data-appointment-id="<?php echo $appointment['id']; ?>" data-appointment-time="<?php echo $timeValue; ?>" data-date="<?php echo $dateValue; ?>">
                        <td>
                            <div class="table-datetime">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div class="table-date">
                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-patient-name">
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-contact-info">
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="table-reason" title="<?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?>
                            </div>
                        </td>
                        <td>
                            <span class="table-status-badge <?php echo strtolower($appointment['status']); ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="/connect/pages/health_worker/view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-info" title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($appointment['status'] === 'Scheduled'): ?>
                                <button class="btn btn-primary" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Confirmed')" title="Confirm & Send SMS">
                                    <i class="fas fa-sms"></i> Confirm
                                </button>
                                <?php endif; ?>
                                <?php if ($appointment['status'] === 'Confirmed'): ?>
                                <button class="btn btn-warning" onclick="sendSMSReminder(<?php echo $appointment['id']; ?>)" title="Send SMS Reminder">
                                    <i class="fas fa-bell"></i> Remind
                                </button>
                                <button class="btn btn-success" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Done', <?php echo $appointment['patient_id']; ?>)" title="Mark as Done">
                                    <i class="fas fa-check"></i> Done
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Mobile Card Layout -->
        <div class="appointments-grid">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>There are no appointments matching your search criteria.</p>
            </div>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): 
                    $cardTimeValue = date('H:i', strtotime($appointment['appointment_time']));
                    $cardDateValue = date('Y-m-d', strtotime($appointment['appointment_date']));
                ?>
                <div class="appointment-card <?php echo strtolower($appointment['status']); ?>" data-appointment-id="<?php echo $appointment['id']; ?>" data-date="<?php echo $cardDateValue; ?>" data-time="<?php echo $cardTimeValue; ?>">
                    <div class="appointment-date">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></span>
                    </div>
                    
                    <div class="appointment-time">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                    </div>
                    
                    <div class="appointment-status">
                        <span class="status-badge <?php echo strtolower($appointment['status']); ?>">
                            <?php echo ucfirst($appointment['status']); ?>
                        </span>
                    </div>
                    
                    <div class="patient-info">
                        <h3><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h3>
                        <div class="contact-details">
                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></div>
                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                        </div>
                    </div>
                    
                    <div class="appointment-reason">
                        <strong>Reason:</strong>
                        <p><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <div class="appointment-actions">
                        <a href="/connect/pages/health_worker/view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-info" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($appointment['status'] === 'Scheduled'): ?>
                        <button class="btn btn-primary" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Confirmed')" title="Confirm & Send SMS">
                            <i class="fas fa-sms"></i> Confirm
                        </button>
                        <?php endif; ?>
                        <?php if ($appointment['status'] === 'Confirmed'): ?>
                        <button class="btn btn-warning" onclick="sendSMSReminder(<?php echo $appointment['id']; ?>)" title="Send SMS Reminder">
                            <i class="fas fa-bell"></i> Remind
                        </button>
                        <button class="btn btn-success" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Done', <?php echo $appointment['patient_id']; ?>)" title="Mark as Done">
                            <i class="fas fa-check"></i> Done
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div><!-- End Appointments View -->

        <!-- Availability Calendar View -->
        <div id="availabilityView" class="view-content">
            <div class="calendar-availability-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-check"></i> Manage Your Availability</h2>
                    <span class="text-white-50">Click on a date to set availability and slot limits</span>
                </div>
                <div class="calendar-layout">
                    <div class="calendar-wrapper">
                        <div id="availabilityCalendar"></div>
                    </div>
                    <div class="calendar-sidebar">
                        <div class="sidebar-card">
                            <h4><i class="fas fa-palette"></i> Legend</h4>
                            <div class="legend-item">
                                <div class="legend-color legend-available"></div>
                                <span>Available (has slots)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-unavailable"></div>
                                <span>Unavailable (blocked)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-limited"></div>
                                <span>Limited (≤3 slots left)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-full"></div>
                                <span>Fully booked</span>
                            </div>
                        </div>
                        <div class="sidebar-card">
                            <h4><i class="fas fa-chart-pie"></i> Quick Stats</h4>
                            <div class="quick-stats">
                                <div class="stat-item">
                                    <div class="stat-value" id="statUnavailable">0</div>
                                    <div class="stat-label">Unavailable Days</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value" id="statTotalSlots">0</div>
                                    <div class="stat-label">Total Slots</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value" id="statBooked">0</div>
                                    <div class="stat-label">Booked</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value" id="statAvailable">0</div>
                                    <div class="stat-label">Available</div>
                                </div>
                            </div>
                        </div>
                        <div class="sidebar-card">
                            <h4><i class="fas fa-info-circle"></i> Instructions</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 0.85rem; color: #666;">
                                <li>Click a date to configure</li>
                                <li>Set as available or unavailable</li>
                                <li>Adjust daily slot limits</li>
                                <li>Changes save automatically</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- End Availability View -->
    </div>

    <!-- Date Appointments Modal -->
    <div class="availability-modal-overlay" id="dateAppointmentsModal">
        <div class="availability-modal-content" style="max-width: 600px;">
            <div class="availability-modal-header">
                <h3><i class="fas fa-calendar-day"></i> Appointments for <span id="dateAppointmentsTitle"></span></h3>
                <button class="availability-modal-close" onclick="closeDateAppointmentsModal()">&times;</button>
            </div>
            <div class="availability-modal-body">
                <div class="date-appointments-info" style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div class="stat-item" style="flex: 1; text-align: center; padding: 10px; background: #e8f5e9; border-radius: 8px;">
                        <div class="stat-value" id="dateSlotLimit" style="font-size: 1.5rem; font-weight: 600; color: #4CAF50;">0</div>
                        <div class="stat-label" style="font-size: 0.8rem; color: #666;">Total Slots</div>
                    </div>
                    <div class="stat-item" style="flex: 1; text-align: center; padding: 10px; background: #fff3e0; border-radius: 8px;">
                        <div class="stat-value" id="dateBooked" style="font-size: 1.5rem; font-weight: 600; color: #ff9800;">0</div>
                        <div class="stat-label" style="font-size: 0.8rem; color: #666;">Booked</div>
                    </div>
                    <div class="stat-item" style="flex: 1; text-align: center; padding: 10px; background: #e3f2fd; border-radius: 8px;">
                        <div class="stat-value" id="dateRemaining" style="font-size: 1.5rem; font-weight: 600; color: #2196F3;">0</div>
                        <div class="stat-label" style="font-size: 0.8rem; color: #666;">Available</div>
                    </div>
                </div>
                <div id="dateAppointmentsList" style="max-height: 400px; overflow-y: auto;">
                    <div class="loading-spinner" style="text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading appointments...
                    </div>
                </div>
            </div>
            <div class="availability-modal-footer">
                <button class="btn btn-secondary" onclick="closeDateAppointmentsModal()">Close</button>
                <button class="btn btn-primary" onclick="closeDateAppointmentsModal(); openAvailabilityModal(selectedAvailabilityDate);">
                    <i class="fas fa-cog"></i> Configure Availability
                </button>
            </div>
        </div>
    </div>

    <!-- Availability Configuration Modal -->
    <div class="availability-modal-overlay" id="availabilityModal">
        <div class="availability-modal-content">
            <div class="availability-modal-header">
                <h3><i class="fas fa-calendar-day"></i> Configure Date</h3>
                <button class="availability-modal-close" onclick="closeAvailabilityModal()">&times;</button>
            </div>
            <div class="availability-modal-body">
                <div class="selected-date-display">
                    <div class="date-text" id="modalDateDisplay">January 8, 2026</div>
                </div>
                
                <div class="availability-options">
                    <label class="availability-option option-available" onclick="setAvailabilityOption('available')">
                        <input type="radio" name="availabilityType" value="available" checked>
                        <div class="option-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="option-details">
                            <h4>Available</h4>
                            <p>Accept appointments on this date</p>
                        </div>
                    </label>
                    
                    <label class="availability-option option-unavailable" onclick="setAvailabilityOption('unavailable')">
                        <input type="radio" name="availabilityType" value="unavailable">
                        <div class="option-icon">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="option-details">
                            <h4>Unavailable</h4>
                            <p>Block all appointments on this date</p>
                        </div>
                    </label>
                </div>
                
                <div class="slot-config-section" id="slotConfigSection">
                    <label for="slotLimitInput">Daily Appointment Limit</label>
                    <div class="slot-input-group">
                        <input type="number" id="slotLimitInput" min="1" max="50" value="10" placeholder="Slots">
                    </div>
                    <div class="slot-presets">
                        <button type="button" class="slot-preset-btn" onclick="setSlotPreset(5)">5</button>
                        <button type="button" class="slot-preset-btn" onclick="setSlotPreset(10)">10</button>
                        <button type="button" class="slot-preset-btn" onclick="setSlotPreset(15)">15</button>
                        <button type="button" class="slot-preset-btn" onclick="setSlotPreset(20)">20</button>
                        <button type="button" class="slot-preset-btn" onclick="setSlotPreset(25)">25</button>
                    </div>
                    
                    <div class="time-slots-distribution" id="timeSlotsDistribution">
                        <label style="margin-top: 15px; margin-bottom: 10px;">
                            <i class="fas fa-clock"></i> Preferred Time Slots Distribution
                        </label>
                        <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                            Allocate slots for each available time. Only times with slots > 0 will be shown to patients.
                        </p>
                        <div class="time-slots-grid" id="timeSlotsGrid">
                            <!-- Time slots will be populated by JavaScript -->
                        </div>
                        <div class="slot-distribution-summary" id="slotDistributionSummary">
                            <span class="allocated-count">Allocated: <strong>0</strong></span>
                            <span class="remaining-count">/ Total: <strong>10</strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="availability-modal-footer">
                <button class="btn btn-secondary" onclick="closeAvailabilityModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveAvailability()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Add Appointment Modal -->
    <div class="modal" id="addAppointmentModal" tabindex="-1" role="dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Schedule Appointment</h3>
                <button type="button" class="modal-close" onclick="closeModal('addAppointmentModal')">
                    <span>&times;</span>
                </button>
            </div>
            <form id="appointmentForm" method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="patient_id">Patient</label>
                        <select class="form-control" id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients_list as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>">
                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' (' . $patient['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="appointment_date">Date</label>
                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" required
                               min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="appointment_time">Time</label>
                        <select class="form-control" id="appointment_time" name="appointment_time" required>
                            <option value="">Select Time</option>
                            <?php
                            $start = strtotime($working_hours['start']);
                            $end = strtotime($working_hours['end']);
                            $interval = $working_hours['interval'] * 60; // convert to seconds

                            for ($time = $start; $time <= $end; $time += $interval) {
                                $formatted_time = date('H:i', $time);
                                echo "<option value=\"$formatted_time\">".date('g:i A', $time)."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Visit</label>
                        <input type="text" class="form-control" id="reason" name="reason" placeholder="Brief reason for the appointment">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addAppointmentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add QR Scanner Modal -->
    <div id="qrScannerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-qrcode"></i> Scan QR Code</h3>
                <button type="button" class="modal-close" onclick="closeQRScanner()">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="scanner-container">
                    <video id="qrVideo" playsinline></video>
                    <canvas id="qrCanvas"></canvas>
                    <div class="scan-overlay">
                        <div class="scan-line"></div>
                    </div>
                    <div class="scan-overlay-corners"></div>
                </div>
                <div class="scanner-instructions">
                    <i class="fas fa-info-circle me-1"></i> Position the QR code within the frame to scan
                </div>
                <div id="qrScannerResult" class="mt-3 text-center"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100" onclick="closeQRScanner()">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    // =====================================================
    // FILTER AND PRINT FUNCTIONS
    // =====================================================
    
    function filterAppointments() {
        const searchTerm = document.getElementById('searchPatient').value.toLowerCase();
        const dateFilter = document.getElementById('dateFilter').value;
        const timeFilter = document.getElementById('timeFilter').value;
        const rows = document.querySelectorAll('#appointmentsTable tbody tr');
        const cards = document.querySelectorAll('.appointment-card');
        
        // Filter table rows (desktop view)
        rows.forEach(row => {
            const patientName = row.querySelector('.table-patient-name')?.textContent.toLowerCase() || '';
            const appointmentDate = row.getAttribute('data-date') || '';
            const appointmentTime = row.dataset.appointmentTime || '';
            
            let matchesSearch = patientName.includes(searchTerm);
            let matchesDate = !dateFilter || appointmentDate === dateFilter;
            let matchesTime = timeFilter === 'all' || appointmentTime === timeFilter;
            
            if (matchesSearch && matchesDate && matchesTime) {
                row.classList.remove('appointment-row-hidden');
            } else {
                row.classList.add('appointment-row-hidden');
            }
        });
        
        // Filter mobile cards
        cards.forEach(card => {
            const patientName = card.querySelector('.patient-info h3')?.textContent.toLowerCase() || '';
            const appointmentDate = card.getAttribute('data-date') || '';
            const appointmentTime = card.getAttribute('data-time') || '';
            
            let matchesSearch = patientName.includes(searchTerm);
            let matchesDate = !dateFilter || appointmentDate === dateFilter;
            let matchesTime = timeFilter === 'all' || appointmentTime === timeFilter;
            
            if (matchesSearch && matchesDate && matchesTime) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update count display
        const visibleRows = document.querySelectorAll('#appointmentsTable tbody tr:not(.appointment-row-hidden)');
        console.log(`Showing ${visibleRows.length} appointments`);
    }
    
    function filterAppointmentsByTime() {
        // Legacy function - redirect to new unified filter
        filterAppointments();
    }
    
    function printFilteredAppointments() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Get all filter values for title
        const searchTerm = document.getElementById('searchPatient').value;
        const dateFilter = document.getElementById('dateFilter').value;
        const timeSelect = document.getElementById('timeFilter');
        const timeFilter = timeSelect.value;
        const timeText = timeFilter === 'all' ? 'All Times' : timeSelect.options[timeSelect.selectedIndex].text;
        
        // Build filter description
        let filterParts = [];
        if (searchTerm) filterParts.push(`Search: "${searchTerm}"`);
        if (dateFilter) filterParts.push(`Date: ${dateFilter}`);
        if (timeFilter !== 'all') filterParts.push(`Time: ${timeText}`);
        const filterText = filterParts.length > 0 ? filterParts.join(', ') : 'No filters applied';
        
        // Title
        doc.setFontSize(18);
        doc.setTextColor(76, 175, 80);
        doc.text('HealthConnect - Appointments Report', 14, 20);
        
        // Subtitle with filter info
        doc.setFontSize(11);
        doc.setTextColor(100);
        doc.text(`Filters: ${filterText}`, 14, 28);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 34);
        
        // Collect visible appointment data
        const rows = document.querySelectorAll('#appointmentsTable tbody tr:not(.appointment-row-hidden)');
        const tableData = [];
        
        rows.forEach((row, index) => {
            const dateTime = row.querySelector('.table-datetime')?.textContent.trim() || '';
            const date = row.querySelector('.table-date')?.textContent.trim() || '';
            const patient = row.querySelector('.table-patient-name')?.textContent.trim() || '';
            const contactInfo = row.querySelector('.table-contact-info');
            const phone = contactInfo ? contactInfo.querySelectorAll('div')[1]?.textContent.trim().replace(/^\s*/, '') : '';
            const reason = row.querySelector('.table-reason')?.textContent.trim() || '';
            const status = row.querySelector('.table-status-badge')?.textContent.trim() || '';
            
            tableData.push([
                index + 1, // Add numbering
                `${dateTime}\n${date}`,
                patient,
                phone,
                reason,
                status
            ]);
        });
        
        // Create table
        doc.autoTable({
            startY: 42,
            head: [['#', 'Time & Date', 'Patient', 'Phone', 'Reason', 'Status']],
            body: tableData,
            theme: 'striped',
            headStyles: {
                fillColor: [76, 175, 80],
                textColor: 255,
                fontStyle: 'bold'
            },
            styles: {
                fontSize: 9,
                cellPadding: 4,
            },
            columnStyles: {
                0: { cellWidth: 10, halign: 'center' }, // Numbering column
                1: { cellWidth: 32 },
                2: { cellWidth: 38 },
                3: { cellWidth: 32 },
                4: { cellWidth: 42 },
                5: { cellWidth: 22 }
            },
            didDrawPage: function(data) {
                // Footer
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text(
                    `Page ${doc.internal.getNumberOfPages()}`,
                    doc.internal.pageSize.width / 2,
                    doc.internal.pageSize.height - 10,
                    { align: 'center' }
                );
            }
        });
        
        // Add summary
        const finalY = doc.lastAutoTable.finalY || 42;
        doc.setFontSize(10);
        doc.setTextColor(60);
        doc.text(`Total Appointments: ${tableData.length}`, 14, finalY + 10);
        
        // Save PDF
        const fileName = `appointments_${filterValue === 'all' ? 'all' : filterValue.replace(':', '')}_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(fileName);
        
        showToast(`PDF downloaded: ${fileName}`, 'success');
    }
    
    // =====================================================
    // VIEW SWITCHING
    // =====================================================
    function switchView(view) {
        // Update tab buttons
        document.querySelectorAll('.view-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.getAttribute('onclick')?.includes(view)) {
                tab.classList.add('active');
            }
        });
        
        // Update view content
        document.querySelectorAll('.view-content').forEach(content => content.classList.remove('active'));
        const viewElement = document.getElementById(view + 'View');
        if (viewElement) {
            viewElement.classList.add('active');
        }
        
        // Save current view to localStorage
        localStorage.setItem('activeAppointmentView', view);
        
        // Initialize calendar if switching to availability view
        if (view === 'availability' && !window.availabilityCalendarInitialized) {
            initAvailabilityCalendar();
        }
    }

    // =====================================================
    // AVAILABILITY CALENDAR
    // =====================================================
    let availabilityCalendar = null;
    let availabilityData = {
        unavailableDates: [],
        slotLimits: {},
        timeSlotLimits: {},
        bookedSlots: {},
        defaultSlotLimit: 10
    };
    let selectedAvailabilityDate = null;
    let currentAvailabilityType = 'available';
    
    // Time slots from PHP
    const workingTimeSlots = [
        <?php
        $start = strtotime($working_hours['start']);
        $end = strtotime($working_hours['end']);
        $interval = $working_hours['interval'] * 60;
        $slots = [];
        for ($time = $start; $time < $end; $time += $interval) {
            $slots[] = '{ value: "' . date('H:i', $time) . '", label: "' . date('g:i A', $time) . '" }';
        }
        echo implode(",\n        ", $slots);
        ?>
    ];

    async function loadAvailabilityData() {
        try {
            const response = await fetch('/connect/api/availability/get.php');
            const result = await response.json();
            
            if (result.success) {
                // Ensure proper data structure
                availabilityData = {
                    unavailableDates: result.data.unavailableDates || [],
                    slotLimits: result.data.slotLimits || {},
                    timeSlotLimits: result.data.timeSlotLimits || {},
                    bookedSlots: result.data.bookedSlots || {},
                    defaultSlotLimit: result.data.defaultSlotLimit || 10
                };
                updateAvailabilityStats();
                return true;
            } else {
                console.error('Failed to load availability:', result.message);
                return false;
            }
        } catch (error) {
            console.error('Error loading availability:', error);
            return false;
        }
    }

    function getDateStatus(dateStr) {
        // Weekends are unavailable by default (0 = Sunday, 6 = Saturday)
        const checkDate = new Date(dateStr + 'T00:00:00');
        const dayOfWeek = checkDate.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            return { status: 'weekend', slots: 0, remaining: 0 };
        }
        
        // Check if unavailable
        if (availabilityData.unavailableDates.includes(dateStr)) {
            return { status: 'unavailable', slots: 0, remaining: 0 };
        }
        
        // Get slot limit
        const slotLimit = availabilityData.slotLimits[dateStr] || availabilityData.defaultSlotLimit;
        const booked = availabilityData.bookedSlots[dateStr] || 0;
        const remaining = Math.max(0, slotLimit - booked);
        
        if (remaining === 0) {
            return { status: 'full', slots: slotLimit, remaining: 0 };
        } else if (remaining <= 3) {
            return { status: 'limited', slots: slotLimit, remaining: remaining };
        } else {
            return { status: 'available', slots: slotLimit, remaining: remaining };
        }
    }

    function updateAvailabilityStats() {
        const unavailableCount = availabilityData.unavailableDates.length;
        let totalSlots = 0;
        let totalBooked = 0;
        
        // Calculate from slot limits
        Object.entries(availabilityData.slotLimits).forEach(([date, slots]) => {
            if (!availabilityData.unavailableDates.includes(date)) {
                totalSlots += slots;
            }
        });
        
        // Calculate booked
        Object.values(availabilityData.bookedSlots).forEach(booked => {
            totalBooked += booked;
        });
        
        document.getElementById('statUnavailable').textContent = unavailableCount;
        document.getElementById('statTotalSlots').textContent = totalSlots;
        document.getElementById('statBooked').textContent = totalBooked;
        document.getElementById('statAvailable').textContent = Math.max(0, totalSlots - totalBooked);
    }

    async function initAvailabilityCalendar() {
        // Load data first
        await loadAvailabilityData();
        
        const calendarEl = document.getElementById('availabilityCalendar');
        
        availabilityCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            height: 'auto',
            selectable: true,
            
            // Custom date cell rendering
            dayCellDidMount: function(arg) {
                // Use local date formatting to avoid timezone issues
                const year = arg.date.getFullYear();
                const month = String(arg.date.getMonth() + 1).padStart(2, '0');
                const day = String(arg.date.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${day}`;
                const status = getDateStatus(dateStr);
                
                // Add custom class based on status
                switch(status.status) {
                    case 'weekend':
                    case 'unavailable':
                        arg.el.classList.add('unavailable-date');
                        // Make weekends non-clickable visually
                        if (status.status === 'weekend') {
                            arg.el.style.cursor = 'not-allowed';
                        }
                        break;
                    case 'full':
                        arg.el.classList.add('full-slots');
                        break;
                    case 'limited':
                        arg.el.classList.add('limited-slots');
                        break;
                    case 'available':
                        arg.el.classList.add('available-date');
                        break;
                }
                
                // Add slot indicator
                const indicator = document.createElement('div');
                indicator.className = 'slot-indicator';
                
                if (status.status === 'weekend' || status.status === 'unavailable') {
                    indicator.className += ' slots-unavailable';
                    indicator.innerHTML = '<i class="fas fa-ban"></i>';
                    // Make weekends visually non-clickable
                    if (status.status === 'weekend') {
                        indicator.style.cursor = 'not-allowed';
                    }
                } else if (status.status === 'full') {
                    indicator.className += ' slots-full';
                    indicator.textContent = 'Full';
                } else {
                    indicator.className += status.status === 'limited' ? ' slots-limited' : ' slots-available';
                    indicator.textContent = `${status.remaining}`;
                    // Make clickable to show appointments
                    indicator.style.cursor = 'pointer';
                    indicator.title = 'Click to view appointments';
                }
                
                const dayFrame = arg.el.querySelector('.fc-daygrid-day-frame');
                if (dayFrame) {
                    dayFrame.appendChild(indicator);
                }
            },
            
            // Handle date click
            dateClick: function(info) {
                selectedAvailabilityDate = info.dateStr;
                const status = getDateStatus(info.dateStr);
                
                // Prevent clicking on weekends (make them non-clickable)
                if (status.status === 'weekend') {
                    return; // Do nothing for weekends
                }
                
                // If it's unavailable (not weekend), show config modal
                // If it has bookings, show appointments modal
                if (status.status === 'unavailable') {
                    openAvailabilityModal(info.dateStr);
                } else {
                    showDateAppointmentsModal(info.dateStr, status);
                }
            }
        });
        
        availabilityCalendar.render();
        window.availabilityCalendarInitialized = true;
    }

    function formatDateDisplay(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }

    function openAvailabilityModal(dateStr) {
        selectedAvailabilityDate = dateStr;
        const status = getDateStatus(dateStr);
        
        document.getElementById('modalDateDisplay').textContent = formatDateDisplay(dateStr);
        document.getElementById('availabilityModal').classList.add('active');
        
        // Set current availability type
        if (status.status === 'unavailable') {
            setAvailabilityOption('unavailable');
        } else {
            setAvailabilityOption('available');
            const slotLimit = availabilityData.slotLimits[dateStr] || availabilityData.defaultSlotLimit;
            document.getElementById('slotLimitInput').value = slotLimit;
            
            // Populate time slots distribution
            populateTimeSlotsGrid(dateStr, slotLimit);
        }
    }
    
    function populateTimeSlotsGrid(dateStr, totalSlots) {
        const grid = document.getElementById('timeSlotsGrid');
        grid.innerHTML = '';
        
        // Get existing time slot limits for this date
        const existingTimeSlots = availabilityData.timeSlotLimits[dateStr] || {};
        
        workingTimeSlots.forEach(slot => {
            const existingLimit = existingTimeSlots[slot.value] || 0;
            
            const item = document.createElement('div');
            item.className = 'time-slot-item' + (existingLimit > 0 ? ' has-slots' : '');
            item.innerHTML = `
                <span class="time-slot-label">${slot.label}</span>
                <input type="number" class="time-slot-input" 
                       data-time="${slot.value}" 
                       min="0" max="50" 
                       value="${existingLimit}"
                       onchange="updateTimeSlotAllocation(this)"
                       onfocus="this.select()">
            `;
            grid.appendChild(item);
        });
        
        updateSlotDistributionSummary(totalSlots);
    }
    
    function updateTimeSlotAllocation(input) {
        const item = input.closest('.time-slot-item');
        const value = parseInt(input.value) || 0;
        
        if (value > 0) {
            item.classList.add('has-slots');
        } else {
            item.classList.remove('has-slots');
        }
        
        const totalSlots = parseInt(document.getElementById('slotLimitInput').value) || 10;
        updateSlotDistributionSummary(totalSlots);
    }
    
    function updateSlotDistributionSummary(totalSlots) {
        const inputs = document.querySelectorAll('.time-slot-input');
        let allocated = 0;
        
        inputs.forEach(input => {
            allocated += parseInt(input.value) || 0;
        });
        
        const summary = document.getElementById('slotDistributionSummary');
        const allocatedSpan = summary.querySelector('.allocated-count');
        const remainingSpan = summary.querySelector('.remaining-count');
        
        allocatedSpan.innerHTML = `Allocated: <strong>${allocated}</strong>`;
        remainingSpan.innerHTML = `/ Total: <strong>${totalSlots}</strong>`;
        
        if (allocated > totalSlots) {
            allocatedSpan.classList.add('over-limit');
        } else {
            allocatedSpan.classList.remove('over-limit');
        }
    }

    function closeAvailabilityModal() {
        document.getElementById('availabilityModal').classList.remove('active');
    }

    // Date Appointments Modal Functions
    async function showDateAppointmentsModal(dateStr, status) {
        document.getElementById('dateAppointmentsTitle').textContent = formatDateDisplay(dateStr);
        document.getElementById('dateSlotLimit').textContent = status.slots;
        document.getElementById('dateBooked').textContent = status.slots - status.remaining;
        document.getElementById('dateRemaining').textContent = status.remaining;
        document.getElementById('dateAppointmentsModal').classList.add('active');
        
        // Load appointments for this date
        await loadDateAppointments(dateStr);
    }
    
    function closeDateAppointmentsModal() {
        document.getElementById('dateAppointmentsModal').classList.remove('active');
    }
    
    async function loadDateAppointments(dateStr) {
        const listContainer = document.getElementById('dateAppointmentsList');
        listContainer.innerHTML = '<div class="loading-spinner" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>';
        
        try {
            // Filter appointments for this date from the PHP data
            const allAppointments = <?php echo json_encode($appointments); ?>;
            const dateAppointments = allAppointments.filter(apt => apt.appointment_date === dateStr);
            
            if (dateAppointments.length === 0) {
                listContainer.innerHTML = `
                    <div class="no-appointments">
                        <i class="fas fa-calendar-check"></i>
                        <h4>No Appointments</h4>
                        <p>There are no scheduled appointments for this date.</p>
                    </div>
                `;
                return;
            }
            
            // Sort by time
            dateAppointments.sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
            
            let html = '';
            dateAppointments.forEach(apt => {
                const statusClass = apt.status.toLowerCase().replace(/\s+/g, '-');
                const time = formatTime12Hour(apt.appointment_time);
                
                let statusBg = '#2196F3';
                if (apt.status === 'Confirmed') statusBg = '#4CAF50';
                else if (apt.status === 'Done') statusBg = '#9e9e9e';
                else if (apt.status === 'Cancelled') statusBg = '#f44336';
                else if (apt.status === 'No Show') statusBg = '#ff9800';
                
                html += `
                    <div class="appointment-list-item status-${statusClass}" data-appointment-id="${apt.id}" onclick="goToAppointmentFromModal('${apt.id}')">
                        <div class="appointment-list-time">${time}</div>
                        <div class="appointment-list-details">
                            <div class="appointment-list-patient">${apt.first_name} ${apt.last_name}</div>
                            <div class="appointment-list-reason">${apt.reason || 'No reason provided'}</div>
                        </div>
                        <span class="appointment-list-status" style="background: ${statusBg}; color: white;">${apt.status}</span>
                    </div>
                `;
            });
            
            listContainer.innerHTML = html;
        } catch (error) {
            console.error('Error loading appointments:', error);
            listContainer.innerHTML = `
                <div class="no-appointments">
                    <i class="fas fa-exclamation-circle" style="color: #f44336;"></i>
                    <h4>Error Loading Appointments</h4>
                    <p>Please try again later.</p>
                </div>
            `;
        }
    }
    
    function formatTime12Hour(time24) {
        const [hours, minutes] = time24.split(':');
        const hour = parseInt(hours, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    function goToAppointmentFromModal(appointmentId) {
        // Close the modal
        closeDateAppointmentsModal();
        
        // Switch to appointments view
        switchView('appointments');
        
        // Small delay to ensure view has switched, then highlight
        setTimeout(() => {
            highlightAppointmentInList(appointmentId);
        }, 500);
    }
    
    function highlightAppointmentInList(appointmentId) {
        // Find all elements with this appointment ID (could be table row or card)
        const elements = document.querySelectorAll('[data-appointment-id="' + appointmentId + '"]');
        
        if (elements.length > 0) {
            elements.forEach(element => {
                // Scroll into view
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Add highlight class
                element.classList.add('highlighted');
                
                // Remove highlight after animation
                setTimeout(() => {
                    element.classList.remove('highlighted');
                }, 3000);
            });
        }
    }

    function setAvailabilityOption(type) {
        currentAvailabilityType = type;
        
        // Update UI
        document.querySelectorAll('.availability-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        const selectedOption = document.querySelector(`.option-${type}`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
            selectedOption.querySelector('input').checked = true;
        }
        
        // Show/hide slot config
        const slotSection = document.getElementById('slotConfigSection');
        if (type === 'available') {
            slotSection.classList.remove('hidden');
        } else {
            slotSection.classList.add('hidden');
        }
    }

    function setSlotPreset(value) {
        document.getElementById('slotLimitInput').value = value;
        
        // Update preset buttons
        document.querySelectorAll('.slot-preset-btn').forEach(btn => {
            btn.classList.remove('active');
            if (parseInt(btn.textContent) === value) {
                btn.classList.add('active');
            }
        });
        
        // Update distribution summary
        updateSlotDistributionSummary(value);
    }

    async function saveAvailability() {
        if (!selectedAvailabilityDate) {
            showToast('No date selected', 'error');
            return;
        }
        
        const isAvailable = currentAvailabilityType === 'available';
        const slotLimit = parseInt(document.getElementById('slotLimitInput').value) || availabilityData.defaultSlotLimit;
        
        // Collect time slot limits
        const timeSlotLimits = {};
        if (isAvailable) {
            document.querySelectorAll('.time-slot-input').forEach(input => {
                const time = input.dataset.time;
                const limit = parseInt(input.value) || 0;
                if (limit > 0) {
                    timeSlotLimits[time] = limit;
                }
            });
        }
        
        try {
            const response = await fetch('/connect/api/availability/save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date: selectedAvailabilityDate,
                    is_available: isAvailable,
                    slot_limit: slotLimit,
                    time_slot_limits: timeSlotLimits
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update local data from the response
                if (result.data) {
                    availabilityData.unavailableDates = result.data.unavailableDates || [];
                    availabilityData.slotLimits = result.data.slotLimits || {};
                    availabilityData.timeSlotLimits = result.data.timeSlotLimits || {};
                } else {
                    // Fallback: update manually if no data returned
                    if (!isAvailable) {
                        if (!availabilityData.unavailableDates.includes(selectedAvailabilityDate)) {
                            availabilityData.unavailableDates.push(selectedAvailabilityDate);
                        }
                        delete availabilityData.slotLimits[selectedAvailabilityDate];
                        delete availabilityData.timeSlotLimits[selectedAvailabilityDate];
                    } else {
                        const index = availabilityData.unavailableDates.indexOf(selectedAvailabilityDate);
                        if (index > -1) {
                            availabilityData.unavailableDates.splice(index, 1);
                        }
                        availabilityData.slotLimits[selectedAvailabilityDate] = slotLimit;
                        availabilityData.timeSlotLimits[selectedAvailabilityDate] = timeSlotLimits;
                    }
                }
                
                // Update stats
                updateAvailabilityStats();
                
                // Refresh calendar to reflect changes
                if (availabilityCalendar) {
                    availabilityCalendar.destroy();
                    await initAvailabilityCalendar();
                }
                
                closeAvailabilityModal();
                showToast('Availability updated successfully', 'success');
            } else {
                showToast(result.message || 'Failed to save', 'error');
            }
        } catch (error) {
            console.error('Error saving availability:', error);
            showToast('Error saving availability', 'error');
        }
    }

    // Close availability modal on outside click
    document.getElementById('availabilityModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAvailabilityModal();
        }
    });
    
    // Close date appointments modal on outside click
    document.getElementById('dateAppointmentsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDateAppointmentsModal();
        }
    });

    // =====================================================
    // ORIGINAL APPOINTMENT FUNCTIONS
    // =====================================================
    
    // Show/hide modal functions
    function showAddAppointmentModal() {
        document.getElementById('addAppointmentModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking on X or outside the modal
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we should open the Today's Appointments modal
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('openTodayModal') === '1') {
            openTodayAppointmentsModal();
            // Clean up URL
            const url = new URL(window.location);
            url.searchParams.delete('openTodayModal');
            window.history.replaceState({}, '', url);
        }
        
        // Close when clicking the X button
        const closeButtons = document.querySelectorAll('.modal-close');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Close when clicking outside the modal
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Cancel button closes modal
        const cancelButtons = document.querySelectorAll('.modal-footer .btn-secondary');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
    });

    // Handle form submission
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const patientId = formData.get('patient_id');
        const date = formData.get('appointment_date');
        const time = formData.get('appointment_time');
        const reason = formData.get('reason');
        const notes = formData.get('notes');
        
        // Create a POST request to save the appointment
        fetch('/connect/pages/health_worker/save_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'patient_id': patientId,
                'appointment_date': date,
                'appointment_time': time,
                'reason': reason,
                'notes': notes,
                'health_worker_id': '<?php echo $health_worker_id; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error scheduling appointment: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while scheduling the appointment');
        });
    });

    // Function to show toast notification (CSS-based, no Bootstrap JS required)
    function showToast(message, status = 'success') {
        // Remove any existing custom toasts
        const existingToasts = document.querySelectorAll('.custom-toast');
        existingToasts.forEach(t => t.remove());
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'custom-toast';
        
        // Set background color based on status
        let bgColor, icon;
        switch(status) {
            case 'success':
                bgColor = '#28a745';
                icon = 'fa-check-circle';
                break;
            case 'error':
                bgColor = '#dc3545';
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                bgColor = '#ffc107';
                icon = 'fa-exclamation-triangle';
                break;
            case 'info':
            default:
                bgColor = '#17a2b8';
                icon = 'fa-info-circle';
                break;
        }
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 400px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        toast.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }

    // Check for URL parameters and show toast if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Restore active tab from localStorage
        const savedView = localStorage.getItem('activeAppointmentView');
        if (savedView && (savedView === 'appointments' || savedView === 'availability')) {
            switchView(savedView);
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        
        // Check if we should open the Today's Appointments modal
        if (urlParams.get('openTodayModal') === '1') {
            openTodayAppointmentsModal();
            // Clean up URL
            const url = new URL(window.location);
            url.searchParams.delete('openTodayModal');
            window.history.replaceState({}, '', url);
        }
        
        const smsStatus = urlParams.get('sms_status');
        const statusUpdate = urlParams.get('status_update');
        const message = urlParams.get('message');

        if (message) {
            let status = 'info';
            if (smsStatus === 'success' || statusUpdate === 'success') {
                status = 'success';
            } else if (smsStatus === 'error') {
                status = 'error';
            }
            
            // Decode the message and show toast
            const decodedMessage = decodeURIComponent(message);
            showToast(decodedMessage, status);
            
            // Clean up URL without reloading the page
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    });

    // Function to update status with toast notification
    function updateStatus(id, status, patientId = null) {
        let confirmMessage = 'Are you sure you want to mark this appointment as ' + status + '?';
        if (status === 'Confirmed') {
            confirmMessage = 'Confirm this appointment and send SMS notification to the patient?';
        }
        if (status === 'Done') {
            confirmMessage = 'Mark this appointment as Done and add medical record for the patient?';
        }
        
        if (confirm(confirmMessage)) {
            // Show loading state
            showToast('Processing...', 'info');
            
            fetch('/connect/api/appointments/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    appointment_id: id,
                    status: status
                }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // If status is Done, redirect to add medical record page
                    if (status === 'Done' && patientId) {
                        setTimeout(() => {
                            window.location.href = '/connect/pages/health_worker/add_medical_record.php?patient_id=' + patientId + '&appointment_id=' + id;
                        }, 1000);
                        return;
                    }
                    
                    // If there's an SMS result, show it as well
                    if (data.sms_result) {
                        const smsMessage = data.sms_result.success ? 
                            '✓ SMS notification sent successfully!' : 
                            'SMS: ' + data.sms_result.message;
                        const smsStatus = data.sms_result.success ? 'success' : 'warning';
                        
                        setTimeout(() => {
                            showToast(smsMessage, smsStatus);
                        }, 1000);
                        
                        // If SMS was sent, reload faster
                        if (data.sms_result.success) {
                            setTimeout(() => location.reload(), 2000);
                            return;
                        }
                    }
                    
                    // Reload the page after showing notifications
                    setTimeout(() => location.reload(), 2500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating the appointment status', 'error');
            });
        }
    }
    
    // Function to send SMS reminder for confirmed appointments
    function sendSMSReminder(id) {
        if (confirm('Send an SMS reminder to the patient for this appointment?')) {
            // Show loading state
            showToast('Sending SMS reminder...', 'info');
            
            fetch('/connect/api/appointments/send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    appointment_id: id
                }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('✓ SMS reminder sent successfully!', 'success');
                    // Auto reload after sending SMS
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message, 'warning');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while sending the SMS reminder', 'error');
            });
        }
    }
    
    // Function to send follow-up reminder SMS
    function sendFollowUpReminder(recordId, patientName) {
        if (confirm('Send a follow-up checkup reminder SMS to ' + patientName + '?')) {
            // Show loading state
            showToast('Sending follow-up reminder...', 'info');
            
            fetch('/connect/api/medical_records/send_follow_up_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    record_id: recordId
                }),
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('✓ ' + data.message, 'success');
                    // Auto reload after sending SMS
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message, 'warning');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while sending the follow-up reminder', 'error');
            });
        }
    }
    </script>

    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script>
        let selectedDeviceId;
        const codeReader = new ZXing.BrowserMultiFormatReader();
        
        function showNotification() {
            const notification = document.getElementById('scanSuccessNotification');
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        async function showQRScanner() {
            const modal = document.getElementById('qrScannerModal');
            const resultElement = document.getElementById('qrScannerResult');
            const videoElement = document.getElementById('qrVideo');
            modal.style.display = 'block';

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: "environment",
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                });
                
                videoElement.srcObject = stream;
                videoElement.setAttribute('playsinline', true);
                await videoElement.play();

                const canvasElement = document.getElementById('qrCanvas');
                const canvas = canvasElement.getContext('2d', { willReadFrequently: true });
                
                function tick() {
                    if (videoElement.readyState === videoElement.HAVE_ENOUGH_DATA) {
                        canvasElement.height = videoElement.videoHeight;
                        canvasElement.width = videoElement.videoWidth;
                        canvas.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                        
                        const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: "dontInvert",
                        });
                        
                        if (code) {
                            try {
                                const appointmentData = JSON.parse(code.data);
                                if (appointmentData.appointment_id) {
                                    // Close the scanner first
                                    closeQRScanner();
                                    
                                    // Switch to appointments view if not already there
                                    switchView('appointments');
                                    
                                    // Small delay to ensure view has switched
                                    setTimeout(() => {
                                        // Use the existing highlightAppointmentInList function
                                        highlightAppointmentInList(appointmentData.appointment_id);
                                        
                                        // Show success notification
                                        showNotification();
                                    }, 300);
                                    
                                    return;
                                }
                            } catch (err) {
                                console.error('Error parsing QR code data:', err);
                            }
                        }
                    }
                    requestAnimationFrame(tick);
                }
                
                tick();
                
            } catch (err) {
                resultElement.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> ${err.message}<br>
                        Please make sure you have:
                        <ul>
                            <li>Allowed camera access in your browser</li>
                            <li>A working camera connected to your device</li>
                            <li>Not opened the camera in another application</li>
                        </ul>
                    </div>
                `;
                console.error('Error:', err);
            }
        }

        function closeQRScanner() {
            const modal = document.getElementById('qrScannerModal');
            const videoElement = document.getElementById('qrVideo');
            
            // Stop all video streams
            if (videoElement && videoElement.srcObject) {
                const tracks = videoElement.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                videoElement.srcObject = null;
            }
            
            modal.style.display = 'none';
            
            try {
                // Clear the canvas
                const canvas = document.getElementById('qrCanvas');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            } catch (err) {
                console.error('Error cleaning up scanner:', err);
            }
        }
        
        // Make sure to clean up when the page is unloaded
        window.addEventListener('beforeunload', () => {
            try {
                codeReader.reset();
            } catch (err) {
                console.error('Error cleaning up scanner:', err);
            }
        });

        function markAsPresent(appointmentId) {
            if (confirm('Mark this appointment as present?')) {
                updateStatus(appointmentId, 'Confirmed');
                closeQRScanner();
            }
        }
        
        // Close QR scanner when clicking outside the modal
        window.addEventListener('click', function(event) {
            const qrModal = document.getElementById('qrScannerModal');
            if (event.target === qrModal) {
                closeQRScanner();
            }
        });
    </script>
</body>
</html> 