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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$where_clauses = ["a.health_worker_id = ?"];
$params = [$health_worker_id];

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_clauses[] = "a.status_id = ?"; // Changed from 'a.status' to 'a.status_id'
    $params[] = $status;
}

if (!empty($date)) {
    $where_clauses[] = "DATE(a.appointment_date) = ?";
    $params[] = $date;
}

$where_clause = implode(' AND ', $where_clauses);

try {
    // Get appointments
    $query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.notes, a.status_id, a.reason,
                     u.first_name, u.last_name, u.email, u.mobile_number as patient_phone,
                     s.status_name as status
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE $where_clause 
              ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
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
    <!-- Add jsQR library for QR code scanning -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <style>
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem 0;
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

        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); }
            50% { box-shadow: 0 0 25px rgba(255, 193, 7, 0.8); }
            100% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.5); }
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

        .status-badge.scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.confirmed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.done {
            background: #f5f5f5;
            color: #616161;
        }

        .status-badge.cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .status-badge.no-show {
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
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 8px;
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
            .appointments-grid {
                grid-template-columns: 1fr;
            }

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
        
        #qrModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        #qrModal .modal-content {
            max-width: 500px;
            width: 90%;
        }
        
        #qrVideo {
            width: 100%;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        #qrResult {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 4px;
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

        .scanner-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .scanner-container video {
            width: 100%;
            height: auto;
            display: block;
            background: #f8f9fa;
        }

        #qrCanvas {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
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
            color: #666;
            font-size: 0.9rem;
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

    <div class="container">
        <div class="page-header">
            <h1>Appointments</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showAddAppointmentModal()">
                    <i class="fas fa-plus"></i> Schedule Appointment
                </button>
                <button class="btn-scan" onclick="showQRScanner()">
                    <i class="fas fa-qrcode"></i> Scan QR Code
                </button>
            </div>
        </div>

        <div class="filters-section">
            <form action="" method="GET" class="filter-grid">
                <div class="form-group">
                    <input type="text" name="search" class="form-control" placeholder="Search patients..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="3" <?php echo $status === '3' ? 'selected' : ''; ?>>Done</option>
                        <option value="4" <?php echo $status === '4' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="5" <?php echo $status === '5' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <input type="date" name="date" class="form-control" value="<?php echo $date; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?date=<?php echo $date; ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="appointments-grid">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p>There are no appointments matching your search criteria.</p>
            </div>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-card <?php echo strtolower($appointment['status']); ?>" data-appointment-id="<?php echo $appointment['id']; ?>">
                    <div class="appointment-time">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></span>
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
                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-view" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($appointment['status'] === 'Scheduled' || $appointment['status'] === 'Confirmed'): ?>
                        <button class="btn btn-success" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Done')" title="Mark as Done">
                            <i class="fas fa-check"></i> Done
                        </button>
                        <button class="btn btn-danger" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'Cancelled')" title="Cancel Appointment">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                                echo "<option value=\"$formatted_time\">$formatted_time</option>";
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
                <h3 class="modal-title">Scan Appointment QR Code</h3>
                <button type="button" class="modal-close" onclick="closeQRScanner()">×</button>
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
                    Position the QR code within the frame to scan
                </div>
                <div id="qrScannerResult" class="mt-3 text-center"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeQRScanner()">Close</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    // Show/hide modal functions
    function showAddAppointmentModal() {
        document.getElementById('addAppointmentModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking on X or outside the modal
    document.addEventListener('DOMContentLoaded', function() {
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

    // Function to show toast notification
    function showToast(message, status = 'success') {
        const toast = document.getElementById('notificationToast');
        const toastMessage = document.getElementById('toastMessage');
        const toastIcon = document.getElementById('toastIcon');
        
        // Remove existing background classes
        toast.className = 'toast align-items-center text-white border-0';
        
        // Set classes and icon based on status
        switch(status) {
            case 'success':
                toast.classList.add('bg-success');
                toastIcon.className = 'fas fa-check-circle me-2';
                break;
            case 'error':
                toast.classList.add('bg-danger');
                toastIcon.className = 'fas fa-exclamation-circle me-2';
                break;
            case 'info':
                toast.classList.add('bg-info');
                toastIcon.className = 'fas fa-info-circle me-2';
                break;
            default:
                toast.classList.add('bg-success');
                toastIcon.className = 'fas fa-check-circle me-2';
        }
        
        // Set message
        toastMessage.textContent = message;
        
        // Initialize and show toast
        const bsToast = new bootstrap.Toast(toast, {
            animation: true,
            autohide: true,
            delay: 5000
        });
        bsToast.show();
    }

    // Check for URL parameters and show toast if needed
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
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
    function updateStatus(id, status) {
        if (confirm('Are you sure you want to mark this appointment as ' + status + '?')) {
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
                    
                    // If there's an SMS result, show it as well
                    if (data.sms_result) {
                        setTimeout(() => {
                            showToast(data.sms_result.message, data.sms_result.success ? 'success' : 'info');
                        }, 1000);
                    }
                    
                    // Reload the page after showing notifications
                    setTimeout(() => location.reload(), 3000);
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
                                    // Remove highlight from any previously highlighted card
                                    const previousHighlight = document.querySelector('.appointment-card.highlighted');
                                    if (previousHighlight) {
                                        previousHighlight.classList.remove('highlighted');
                                    }

                                    // Find and highlight the corresponding appointment card
                                    const appointmentCard = document.querySelector(`.appointment-card[data-appointment-id="${appointmentData.appointment_id}"]`);
                                    if (appointmentCard) {
                                        appointmentCard.classList.add('highlighted');
                                        appointmentCard.scrollIntoView({ 
                                            behavior: 'smooth', 
                                            block: 'center' 
                                        });
                                        
                                        // Show success notification
                                        showNotification();
                                    }

                                    // Close the scanner after successful scan
                                    closeQRScanner();
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