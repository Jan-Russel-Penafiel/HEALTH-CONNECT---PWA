<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is patient
if ($_SESSION['role'] !== 'patient') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get patient details
try {
    $query = "SELECT p.*, u.* 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE p.user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: /connect/pages/login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching patient details: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}

// Get working hours settings
$working_hours = [
    'start' => '09:00',
    'end' => '17:00',
    'interval' => 30 // minutes
];

try {
    $settings_query = "SELECT name, value FROM settings WHERE name IN ('working_hours_start', 'working_hours_end', 'appointment_duration')";
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

// Get available health workers for selection
$healthWorkers = [];
try {
    $query = "SELECT hw.health_worker_id, CONCAT(u.first_name, ' ', u.last_name) as name
              FROM health_workers hw
              JOIN users u ON hw.user_id = u.user_id
              WHERE u.is_active = 1
              ORDER BY u.first_name, u.last_name";
    $stmt = $pdo->query($query);
    $healthWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching health workers: " . $e->getMessage());
}

// Default health worker ID for API calls
$defaultHealthWorkerId = !empty($healthWorkers) ? $healthWorkers[0]['health_worker_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Debug: Log POST data
    error_log("POST data received - date: " . var_export($appointment_date, true) . ", time: " . var_export($appointment_time, true));
    error_log("Patient ID: " . var_export($patient['patient_id'] ?? 'NOT SET', true));
    
    // Validate required fields
    if (!$appointment_date || !$appointment_time) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Get the first available health worker as default
            $query = "SELECT hw.health_worker_id 
                      FROM health_workers hw
                      JOIN users u ON hw.user_id = u.user_id
                      WHERE u.is_active = 1
                      ORDER BY hw.health_worker_id ASC
                      LIMIT 1";
            $stmt = $pdo->query($query);
            $health_worker_id = $stmt->fetchColumn();
            
            if (!$health_worker_id) {
                $error = "No health workers available. Please contact the administrator.";
            } else {
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
                
                // Log the values being inserted
                error_log("Attempting to insert appointment: patient_id={$patient['patient_id']}, hw_id={$health_worker_id}, date={$appointment_date}, time={$appointment_time}, status={$status_id}");
                
                $result = $stmt->execute([
                    $patient['patient_id'],
                    $health_worker_id,
                    $appointment_date,
                    $appointment_time,
                    $status_id,
                    $reason,
                    $notes
                ]);
                
                // Log execution result
                error_log("Insert result: " . ($result ? "success" : "failed") . ", rows affected: " . $stmt->rowCount());
                
                if ($result) {
                    $success_message = "Appointment successfully scheduled for " . date('F j, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time));
                    error_log("SUCCESS: Appointment inserted with ID: " . $pdo->lastInsertId());
                    
                    // Send SMS notification if enabled
                    if (isset($settings['enable_sms_notifications']) && $settings['enable_sms_notifications'] === '1') {
                        require_once '../../includes/sms.php';
                        $message = "Your appointment at Brgy. Poblacion Health Center has been scheduled for " . 
                                   date('F j, Y', strtotime($appointment_date)) . " at " . 
                                   date('g:i A', strtotime($appointment_time)) . ". Thank you!";
                        sendSMS($patient['mobile_number'], $message);
                    }
                    
                    // Redirect to appointments page
                    header("Location: appointments.php?success=1");
                    exit;
                } else {
                    $error = "Failed to schedule appointment. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Error scheduling appointment: " . $e->getMessage());
            $error = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- FullCalendar JS (v6 doesn't need separate CSS) -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            margin: 0;
        }
        
        .date-display {
            color: #666;
            font-size: 1.1em;
        }
        
        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .booking-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .calendar-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-title {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 15px 20px;
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .calendar-wrapper {
            padding: 20px;
        }
        
        .booking-form-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 12px;
            width: 100%;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #45a049;
        }
        
        .btn-primary:disabled {
            background-color: #a5d6a7;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
        }
        
        .alert-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1565c0;
        }
        
        label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            color: #333;
        }
        
        .required::after {
            content: "*";
            color: #f44336;
            margin-left: 4px;
        }
        
        /* Selected Date Display */
        .selected-date-card {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            border: 2px solid #4CAF50;
        }
        
        .selected-date-card.no-selection {
            background: #f5f5f5;
            border-color: #ddd;
        }
        
        .selected-date-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .selected-date-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .selected-date-card.no-selection .selected-date-value {
            color: #999;
        }
        
        .slots-remaining {
            font-size: 0.85rem;
            color: #4CAF50;
            margin-top: 5px;
        }
        
        .slots-remaining.limited {
            color: #ff9800;
        }
        
        /* FullCalendar Customizations */
        #bookingCalendar .fc-toolbar-title {
            font-size: 1.2rem !important;
        }
        
        #bookingCalendar .fc-button-primary {
            background-color: #4CAF50 !important;
            border-color: #4CAF50 !important;
        }
        
        #bookingCalendar .fc-button-primary:hover {
            background-color: #45a049 !important;
            border-color: #45a049 !important;
        }
        
        #bookingCalendar .fc-day-today {
            background: #e8f5e9 !important;
        }
        
        #bookingCalendar .fc-day-past {
            background: #f5f5f5 !important;
            cursor: not-allowed;
        }
        
        #bookingCalendar .fc-day-past .fc-daygrid-day-number {
            color: #bbb !important;
        }
        
        .fc-daygrid-day.available-date {
            background: #e8f5e9 !important;
            cursor: pointer;
        }
        
        .fc-daygrid-day.available-date:hover {
            background: #c8e6c9 !important;
        }
        
        .fc-daygrid-day.unavailable-date {
            background: #ffebee !important;
            cursor: not-allowed;
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
            cursor: not-allowed;
        }
        
        .fc-daygrid-day.full-slots .fc-daygrid-day-number {
            color: #9e9e9e !important;
            text-decoration: line-through;
        }
        
        .fc-daygrid-day.selected-date {
            background: #c8e6c9 !important;
            box-shadow: inset 0 0 0 2px #4CAF50;
        }
        
        .slot-indicator {
            font-size: 0.6rem;
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
        
        /* Legend */
        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 20px;
            background: #fafafa;
            border-top: 1px solid #eee;
            font-size: 0.85rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        .legend-available { background: #4CAF50; }
        .legend-unavailable { background: #f44336; }
        .legend-limited { background: #ff9800; }
        .legend-full { background: #9e9e9e; }
        
        /* Time slots styling */
        .time-slot-select {
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* Toast */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(150%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-success { background: #4CAF50; }
        .toast-error { background: #f44336; }
        .toast-warning { background: #ff9800; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Schedule an Appointment</h1>
                <p class="text-muted">Select a date from the calendar to book your appointment</p>
            </div>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="booking-layout">
            <!-- Calendar Section -->
            <div class="calendar-section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-alt"></i> Select Appointment Date
                </h3>
                <div class="calendar-wrapper">
                    <div id="bookingCalendar"></div>
                </div>
                <div class="calendar-legend">
                    <div class="legend-item">
                        <div class="legend-color legend-available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-limited"></div>
                        <span>Limited Slots</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-full"></div>
                        <span>Fully Booked</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-unavailable"></div>
                        <span>Unavailable</span>
                    </div>
                </div>
            </div>

            <!-- Booking Form Section -->
            <div class="booking-form-section">
                <h3 class="section-title">
                    <i class="fas fa-edit"></i> Appointment Details
                </h3>
                <div class="card-body">
                    <!-- Selected Date Display -->
                    <div class="selected-date-card no-selection" id="selectedDateCard">
                        <div class="selected-date-label">Selected Date</div>
                        <div class="selected-date-value" id="selectedDateDisplay">Click a date on the calendar</div>
                        <div class="slots-remaining" id="slotsRemainingDisplay"></div>
                    </div>

                    <form method="POST" action="" id="scheduleAppointmentForm">
                        <input type="hidden" id="appointment_date" name="appointment_date" value="">

                        <div class="form-group">
                            <label for="appointment_time" class="required">Preferred Time</label>
                            <select class="form-control" id="appointment_time" name="appointment_time" required disabled>
                                <option value="">Select a date first</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reason" class="required">Reason for Appointment</label>
                            <select class="form-control" id="reason" name="reason" required>
                                <option value="">Select a reason</option>
                                <optgroup label="General Consultation">
                                    <option value="General Checkup">General Checkup</option>
                                    <option value="Follow-up Visit">Follow-up Visit</option>
                                    <option value="Health Consultation">Health Consultation</option>
                                </optgroup>
                                <optgroup label="Preventive Care">
                                    <option value="Vaccination/Immunization">Vaccination/Immunization</option>
                                    <option value="Health Screening">Health Screening</option>
                                    <option value="Annual Physical Exam">Annual Physical Exam</option>
                                </optgroup>
                                <optgroup label="Maternal & Child Health">
                                    <option value="Prenatal Checkup">Prenatal Checkup</option>
                                    <option value="Postnatal Checkup">Postnatal Checkup</option>
                                    <option value="Child Wellness Visit">Child Wellness Visit</option>
                                    <option value="Family Planning">Family Planning</option>
                                </optgroup>
                                <optgroup label="Specific Concerns">
                                    <option value="Illness/Sick Visit">Illness/Sick Visit</option>
                                    <option value="Chronic Disease Management">Chronic Disease Management</option>
                                    <option value="Laboratory Results">Laboratory Results</option>
                                    <option value="Medical Certificate">Medical Certificate</option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="Other">Other (specify in notes)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information the health worker should know"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-calendar-check"></i> Schedule Appointment
                            </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast-notification"></div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <script>
        // =====================================================
        // AVAILABILITY DATA & CALENDAR
        // =====================================================
        let bookingCalendar = null;
        let currentHealthWorkerId = <?php echo $defaultHealthWorkerId; ?>;
        let availabilityData = {
            unavailableDates: [],
            slotLimits: {},
            bookedSlots: {},
            defaultSlotLimit: 10,
            timeSlots: []
        };
        let selectedDate = null;

        // Time slots from PHP
        const phpTimeSlots = [
            <?php
            $start = strtotime($working_hours['start']);
            $end = strtotime($working_hours['end']);
            $interval = $working_hours['interval'] * 60;
            $slots = [];
            for ($time = $start; $time <= $end; $time += $interval) {
                $slots[] = '{ value: "' . date('H:i', $time) . '", label: "' . date('g:i A', $time) . '" }';
            }
            echo implode(",\n            ", $slots);
            ?>
        ];

        async function loadAvailabilityData() {
            try {
                const response = await fetch(`/connect/api/availability/public.php?health_worker_id=${currentHealthWorkerId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Ensure proper data structure with defaults
                    availabilityData = {
                        unavailableDates: result.data.unavailableDates || [],
                        slotLimits: result.data.slotLimits || {},
                        bookedSlots: result.data.bookedSlots || {},
                        bookedTimes: result.data.bookedTimes || {},
                        defaultSlotLimit: result.data.defaultSlotLimit || 10,
                        timeSlots: result.data.timeSlots || phpTimeSlots,
                        workingHours: result.data.workingHours || { start: '08:00', end: '17:00', interval: 30 }
                    };
                    return true;
                }
                return false;
            } catch (error) {
                console.error('Error loading availability:', error);
                availabilityData.timeSlots = phpTimeSlots;
                return false;
            }
        }

        function getDateStatus(dateStr) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const checkDate = new Date(dateStr + 'T00:00:00');
            
            // Past dates
            if (checkDate < today) {
                return { status: 'past', slots: 0, remaining: 0 };
            }
            
            // Weekends are unavailable by default (0 = Sunday, 6 = Saturday)
            const dayOfWeek = checkDate.getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                return { status: 'unavailable', slots: 0, remaining: 0 };
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

        function formatDateDisplay(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        function formatTimeLabel(timeStr) {
            // Convert 24hr format (HH:MM) to 12hr format
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours, 10);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        async function initBookingCalendar() {
            await loadAvailabilityData();
            
            const calendarEl = document.getElementById('bookingCalendar');
            
            bookingCalendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                height: 'auto',
                selectable: false,
                
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
                        case 'past':
                            arg.el.classList.add('fc-day-past');
                            break;
                        case 'unavailable':
                            arg.el.classList.add('unavailable-date');
                            break;
                        case 'full':
                            arg.el.classList.add('full-slots');
                            break;
                        case 'limited':
                            arg.el.classList.add('limited-slots');
                            arg.el.classList.add('available-date');
                            break;
                        case 'available':
                            arg.el.classList.add('available-date');
                            break;
                    }
                    
                    // Mark selected date
                    if (selectedDate === dateStr) {
                        arg.el.classList.add('selected-date');
                    }
                    
                    // Add slot indicator for non-past dates
                    if (status.status !== 'past') {
                        const indicator = document.createElement('div');
                        indicator.className = 'slot-indicator';
                        
                        if (status.status === 'unavailable') {
                            indicator.className += ' slots-unavailable';
                            indicator.innerHTML = '<i class="fas fa-ban"></i>';
                        } else if (status.status === 'full') {
                            indicator.className += ' slots-full';
                            indicator.textContent = 'Full';
                        } else {
                            indicator.className += status.status === 'limited' ? ' slots-limited' : ' slots-available';
                            indicator.textContent = `${status.remaining}`;
                        }
                        
                        const dayFrame = arg.el.querySelector('.fc-daygrid-day-frame');
                        if (dayFrame) {
                            dayFrame.appendChild(indicator);
                        }
                    }
                },
                
                // Handle date click
                dateClick: function(info) {
                    const status = getDateStatus(info.dateStr);
                    
                    // Check if date is selectable
                    if (status.status === 'past' || status.status === 'unavailable' || status.status === 'full') {
                        let message = '';
                        if (status.status === 'past') {
                            message = 'Cannot book appointments for past dates';
                        } else if (status.status === 'unavailable') {
                            message = 'This date is not available for booking';
                        } else {
                            message = 'This date is fully booked';
                        }
                        showToast(message, 'warning');
                        return;
                    }
                    
                    // Select the date
                    selectDate(info.dateStr, status);
                }
            });
            
            bookingCalendar.render();
        }

        function selectDate(dateStr, status) {
            selectedDate = dateStr;
            
            // Update hidden input
            document.getElementById('appointment_date').value = dateStr;
            
            // Update display card
            const card = document.getElementById('selectedDateCard');
            card.classList.remove('no-selection');
            document.getElementById('selectedDateDisplay').textContent = formatDateDisplay(dateStr);
            
            const slotsDisplay = document.getElementById('slotsRemainingDisplay');
            slotsDisplay.textContent = `${status.remaining} slots available`;
            slotsDisplay.className = 'slots-remaining' + (status.status === 'limited' ? ' limited' : '');
            
            // Enable and populate time select
            const timeSelect = document.getElementById('appointment_time');
            timeSelect.disabled = false;
            timeSelect.innerHTML = '<option value="">Select Time</option>';
            
            // Get booked times for this date
            const bookedTimes = availabilityData.bookedTimes ? (availabilityData.bookedTimes[dateStr] || []) : [];
            
            availabilityData.timeSlots.forEach(slot => {
                // Handle both object and string formats
                const value = typeof slot === 'object' ? slot.value : slot;
                const label = typeof slot === 'object' ? slot.label : formatTimeLabel(slot);
                
                // Skip if time is already booked
                if (bookedTimes.includes(value)) {
                    return;
                }
                
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                timeSelect.appendChild(option);
            });
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
            
            // Update calendar visual state without reinitializing
            // Remove selected class from all days
            document.querySelectorAll('.fc-daygrid-day.selected-date').forEach(el => {
                el.classList.remove('selected-date');
            });
            
            // Add selected class to the clicked date
            const dateCell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
            if (dateCell) {
                dateCell.classList.add('selected-date');
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-triangle'}"></i> ${message}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Form validation
        document.getElementById('scheduleAppointmentForm').addEventListener('submit', function(e) {
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;
            const timeSelectElement = document.getElementById('appointment_time');
            
            console.log('Form submission attempt:');
            console.log('- Date:', date);
            console.log('- Time:', time);
            console.log('- Time select disabled?', timeSelectElement.disabled);
            console.log('- Time select options count:', timeSelectElement.options.length);
            console.log('- Form action:', this.action);
            console.log('- Form method:', this.method);
            
            if (!date) {
                e.preventDefault();
                showToast('Please select a date from the calendar', 'error');
                console.error('Validation failed: No date selected');
                return false;
            }
            
            if (!time) {
                e.preventDefault();
                showToast('Please select a time slot', 'error');
                console.error('Validation failed: No time selected');
                return false;
            }
            
            // Ensure time is in proper format (HH:MM:SS)
            if (time && !time.includes(':')) {
                e.preventDefault();
                showToast('Invalid time format', 'error');
                console.error('Validation failed: Invalid time format');
                return false;
            }
            
            // If validation passes, allow form to submit
            console.log('✓ Validation passed, submitting form to server...');
            console.log('✓ Form will POST to:', window.location.href);
            // Do NOT call e.preventDefault() - let form submit naturally
            return true;
        });

        // Auto-fill notes based on selected reason
        document.getElementById('reason').addEventListener('change', function() {
            const reason = this.value;
            const notesField = document.getElementById('notes');
            
            // Mapping of reasons to suggested notes for health worker
            const notesMapping = {
                'General Checkup': 'I would like a routine health checkup. I will bring my medical records and list of current medications.',
                'Follow-up Visit': 'This is a follow-up visit for my previous consultation. I will bring test results and updates on my condition.',
                'Health Consultation': 'I have some health concerns I would like to discuss with you.',
                'Vaccination/Immunization': 'I need vaccination/immunization. I will bring my immunization record. Allergies (if any): ',
                'Health Screening': 'I am scheduling a health screening. Please let me know if fasting is required.',
                'Annual Physical Exam': 'I am here for my annual physical examination. I will bring my current medications list.',
                'Prenatal Checkup': 'This is for my prenatal checkup. Current week of pregnancy: ',
                'Postnatal Checkup': 'This is my postnatal checkup. Delivery date: ',
                'Child Wellness Visit': 'This is a wellness visit for my child. Child\'s age: ',
                'Family Planning': 'I would like to discuss family planning options and methods.',
                'Illness/Sick Visit': 'I am not feeling well. Symptoms: ',
                'Chronic Disease Management': 'Follow-up for chronic disease management. Current condition: ',
                'Laboratory Results': 'I am here to discuss my laboratory test results from: ',
                'Medical Certificate': 'I need a medical certificate for: ',
                'Other': 'Please specify the reason for your visit and any relevant details:'
            };
            
            // Auto-fill the notes field with suggested text
            if (notesMapping[reason]) {
                notesField.value = notesMapping[reason];
            } else {
                notesField.value = '';
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initBookingCalendar();
        });
    </script>
</body>
</html> 