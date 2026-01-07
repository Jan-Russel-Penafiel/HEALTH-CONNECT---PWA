<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is a patient
if ($_SESSION['role'] !== 'patient') {
    header("Location: ../dashboard.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get patient details
$query = "SELECT p.*, u.* 
          FROM patients p 
          JOIN users u ON p.user_id = u.user_id 
          WHERE p.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Check if appointment belongs to patient
    $query = "SELECT * FROM appointments 
              WHERE appointment_id = :id 
              AND patient_id = :patient_id
              AND status_id IN (1, 2)"; // Only scheduled or confirmed
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':id' => $appointment_id,
        ':patient_id' => $patient['patient_id']
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Update appointment status to cancelled
        $query = "UPDATE appointments 
                  SET status_id = 4 
                  WHERE appointment_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $appointment_id]);
        
        // Send SMS notification
        require_once '../../includes/sms.php';
        $message = "Your appointment at Brgy. Poblacion Health Center has been cancelled. Please schedule a new appointment if needed.";
        sendSMS($patient['mobile_number'], $message);
    }
    
    header("Location: appointments.php");
    exit;
}

// Get all appointments
$query = "SELECT a.*, 
          CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
          hw.position as health_worker_position,
          s.status_name,
          s.status_id
          FROM appointments a
          JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
          JOIN users h_u ON hw.user_id = h_u.user_id
          JOIN appointment_status s ON a.status_id = s.status_id
          WHERE a.patient_id = :patient_id 
          ORDER BY a.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([':patient_id' => $patient['patient_id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by status
$upcoming = [];
$past = [];
$cancelled = [];

foreach ($appointments as $appointment) {
    $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
    $now = time();
    
    if ($appointment['status_id'] == 4) { // Cancelled
        $cancelled[] = $appointment;
    } elseif ($appointmentDateTime > $now && in_array($appointment['status_id'], [1, 2])) { // Upcoming
        $upcoming[] = $appointment;
    } else { // Past or completed
        $past[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
    <!-- jsPDF for printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            margin: 0;
        }
        
        .date-display {
            color: #666;
            font-size: 1.1em;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1rem 0;
        }
        
        /* Table styles for desktop */
        .appointments-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .appointments-table th,
        .appointments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .appointments-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        
        .appointments-table tr:hover {
            background: #f8f9fa;
        }
        
        .appointments-table tr:last-child td {
            border-bottom: none;
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        /* Desktop view - show table, hide cards */
        @media (min-width: 992px) {
            .appointments-grid {
                display: none;
            }
            
            .appointments-table {
                display: table;
            }
        }
        
        /* Mobile view - show cards, hide table */
        @media (max-width: 991px) {
            .appointments-grid {
                display: grid;
                grid-template-columns: 1fr;
            }
            
            .appointments-table {
                display: none;
            }
        }
        
        .appointment-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .appointment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .appointment-date {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .appointment-time {
            color: #34495e;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .health-worker-info {
            margin: 1rem 0;
            padding: 0.5rem 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .health-worker-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .health-worker-position {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .appointment-reason {
            margin: 1rem 0;
            color: #34495e;
        }
        
        .status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status.scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status.confirmed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status.completed {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status.cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .card-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-print {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print:hover {
            background: #218838;
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .no-data p {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .no-data i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .btn {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #3a7bc8;
            color: white;
        }
        
        @media (max-width: 768px) {
            .appointments-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .appointment-slip {
                display: block !important;
            }
        }
        
        .appointment-slip {
            display: none;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        
        .slip-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .slip-details {
            margin: 20px 0;
            line-height: 1.6;
        }
        
        .slip-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.9em;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Appointments</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
                <strong>Success!</strong> Your appointment has been scheduled successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']); 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <div></div>
            <a href="schedule_appointment.php" class="btn">
                <i class="fas fa-plus"></i> Schedule New Appointment
            </a>
        </div>
        
        <!-- All Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>All Appointments</h3>
                <span style="color: #666; font-size: 0.9rem;"><?php echo count($appointments); ?> total</span>
            </div>
            
            <?php if (count($appointments) > 0): ?>
                <!-- Desktop Table View -->
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Health Worker</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <?php if ($appointment['status_id'] == 2): ?>
                                            <button onclick="printAppointmentSlipPDF(<?php echo htmlspecialchars(json_encode([
                                                'id' => $appointment['appointment_id'],
                                                'patientName' => $patient['first_name'] . ' ' . $patient['last_name'],
                                                'patientPhone' => $patient['mobile_number'] ?? 'N/A',
                                                'appointmentDate' => date('l, F j, Y', strtotime($appointment['appointment_date'])),
                                                'appointmentTime' => date('g:i A', strtotime($appointment['appointment_time'])),
                                                'healthWorker' => $appointment['health_worker_name'],
                                                'position' => $appointment['health_worker_position'],
                                                'reason' => $appointment['reason'] ?: 'Not specified'
                                            ])); ?>)" class="btn-print">
                                                <i class="fas fa-print"></i> Print Slip
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($appointment['status_id'], [1, 2])): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn-danger">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Card View -->
                <div class="appointments-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                            <div class="appointment-date">
                                <?php echo date('l, M j, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                            <div class="appointment-time">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            
                            <div class="health-worker-info">
                                <div class="health-worker-name">
                                    <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                </div>
                                <div class="health-worker-position">
                                    <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                </div>
                            </div>
                            
                            <div class="appointment-reason">
                                <strong>Reason:</strong><br>
                                <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                            </div>
                            
                            <div>
                                <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
                                </span>
                            </div>
                            
                            <div class="card-actions">
                                <?php if ($appointment['status_id'] == 2): ?>
                                    <button onclick="printAppointmentSlipPDF(<?php echo htmlspecialchars(json_encode([
                                        'id' => $appointment['appointment_id'],
                                        'patientName' => $patient['first_name'] . ' ' . $patient['last_name'],
                                        'patientPhone' => $patient['mobile_number'] ?? 'N/A',
                                        'appointmentDate' => date('l, F j, Y', strtotime($appointment['appointment_date'])),
                                        'appointmentTime' => date('g:i A', strtotime($appointment['appointment_time'])),
                                        'healthWorker' => $appointment['health_worker_name'],
                                        'position' => $appointment['health_worker_position'],
                                        'reason' => $appointment['reason'] ?: 'Not specified'
                                    ])); ?>)" class="btn-print">
                                        <i class="fas fa-print"></i> Print Slip
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($appointment['status_id'], [1, 2])): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn-danger">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No appointments found</p>
                    <a href="schedule_appointment.php" class="btn">Schedule Your First Appointment</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Upcoming Appointments</h3>
                <span style="color: #666; font-size: 0.9rem;"><?php echo count($upcoming); ?> upcoming</span>
            </div>
            
            <?php if (count($upcoming) > 0): ?>
                <!-- Desktop Table View -->
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Health Worker</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $appointment): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <?php if ($appointment['status_id'] == 2): ?>
                                            <button onclick="printAppointmentSlipPDF(<?php echo htmlspecialchars(json_encode([
                                                'id' => $appointment['appointment_id'],
                                                'patientName' => $patient['first_name'] . ' ' . $patient['last_name'],
                                                'patientPhone' => $patient['mobile_number'] ?? 'N/A',
                                                'appointmentDate' => date('l, F j, Y', strtotime($appointment['appointment_date'])),
                                                'appointmentTime' => date('g:i A', strtotime($appointment['appointment_time'])),
                                                'healthWorker' => $appointment['health_worker_name'],
                                                'position' => $appointment['health_worker_position'],
                                                'reason' => $appointment['reason'] ?: 'Not specified'
                                            ])); ?>)" class="btn-print" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($appointment['status_id'], [1, 2])): ?>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn-danger" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Card View -->
                <div class="appointments-grid">
                    <?php foreach ($upcoming as $appointment): ?>
                        <div class="appointment-card" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                            <div class="appointment-date">
                                <?php echo date('l, M j, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                            <div class="appointment-time">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            
                            <div class="health-worker-info">
                                <div class="health-worker-name">
                                    <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                </div>
                                <div class="health-worker-position">
                                    <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                </div>
                            </div>
                            
                            <div class="appointment-reason">
                                <strong>Reason:</strong><br>
                                <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                            </div>
                            
                            <div>
                                <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
                                </span>
                            </div>
                            
                            <div class="card-actions">
                                <?php if ($appointment['status_id'] == 2): ?>
                                    <button onclick="printAppointmentSlipPDF(<?php echo htmlspecialchars(json_encode([
                                        'id' => $appointment['appointment_id'],
                                        'patientName' => $patient['first_name'] . ' ' . $patient['last_name'],
                                        'patientPhone' => $patient['mobile_number'] ?? 'N/A',
                                        'appointmentDate' => date('l, F j, Y', strtotime($appointment['appointment_date'])),
                                        'appointmentTime' => date('g:i A', strtotime($appointment['appointment_time'])),
                                        'healthWorker' => $appointment['health_worker_name'],
                                        'position' => $appointment['health_worker_position'],
                                        'reason' => $appointment['reason'] ?: 'Not specified'
                                    ])); ?>)" class="btn-print">
                                        <i class="fas fa-print"></i> Print Appointment Slip
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($appointment['status_id'], [1, 2])): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                        <button type="submit" name="cancel_appointment" class="btn-danger">
                                            <i class="fas fa-times"></i> Cancel Appointment
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No upcoming appointments</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Past Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Past Appointments</h3>
                <span style="color: #666; font-size: 0.9rem;"><?php echo count($past); ?> past</span>
            </div>
            
            <?php if (count($past) > 0): ?>
                <!-- Desktop Table View -->
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Health Worker</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $appointment): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Card View -->
                <div class="appointments-grid">
                    <?php foreach ($past as $appointment): ?>
                        <div class="appointment-card">
                            <div class="appointment-date">
                                <?php echo date('l, M j, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                            <div class="appointment-time">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            
                            <div class="health-worker-info">
                                <div class="health-worker-name">
                                    <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                </div>
                                <div class="health-worker-position">
                                    <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                </div>
                            </div>
                            
                            <div class="appointment-reason">
                                <strong>Reason:</strong><br>
                                <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                            </div>
                            
                            <div>
                                <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-history"></i>
                    <p>No past appointments</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cancelled Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Cancelled Appointments</h3>
                <span style="color: #666; font-size: 0.9rem;"><?php echo count($cancelled); ?> cancelled</span>
            </div>
            
            <?php if (count($cancelled) > 0): ?>
                <!-- Desktop Table View -->
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Health Worker</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cancelled as $appointment): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile Card View -->
                <div class="appointments-grid">
                    <?php foreach ($cancelled as $appointment): ?>
                        <div class="appointment-card">
                            <div class="appointment-date">
                                <?php echo date('l, M j, Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                            <div class="appointment-time">
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            
                            <div class="health-worker-info">
                                <div class="health-worker-name">
                                    <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                </div>
                                <div class="health-worker-position">
                                    <?php echo htmlspecialchars($appointment['health_worker_position']); ?>
                                </div>
                            </div>
                            
                            <div class="appointment-reason">
                                <strong>Reason:</strong><br>
                                <?php echo !empty($appointment['reason']) ? htmlspecialchars($appointment['reason']) : 'Not specified'; ?>
                            </div>
                            
                            <div>
                                <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-ban"></i>
                    <p>No cancelled appointments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
    async function printAppointmentSlipPDF(appointmentData) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a5'
        });
        
        const pageWidth = doc.internal.pageSize.getWidth();
        const centerX = pageWidth / 2;
        
        // Header - Brgy. Poblacion Health Center
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('Brgy. Poblacion Health Center', centerX, 20, { align: 'center' });
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text('Appointment Confirmation Slip', centerX, 27, { align: 'center' });
        doc.text('Contact: (123) 456-7890', centerX, 33, { align: 'center' });
        
        // Line separator
        doc.setLineWidth(0.5);
        doc.line(15, 38, pageWidth - 15, 38);
        
        // Appointment Details
        let yPos = 48;
        const labelX = 15;
        const valueX = 55;
        
        doc.setFontSize(10);
        
        // Appointment ID
        doc.setFont('helvetica', 'bold');
        doc.text('Appointment ID:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(String(appointmentData.id), valueX, yPos);
        yPos += 7;
        
        // Patient Name
        doc.setFont('helvetica', 'bold');
        doc.text('Patient Name:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.patientName, valueX, yPos);
        yPos += 7;
        
        // Contact Number
        doc.setFont('helvetica', 'bold');
        doc.text('Contact Number:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.patientPhone, valueX, yPos);
        yPos += 7;
        
        // Date
        doc.setFont('helvetica', 'bold');
        doc.text('Date:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.appointmentDate, valueX, yPos);
        yPos += 7;
        
        // Time
        doc.setFont('helvetica', 'bold');
        doc.text('Time:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.appointmentTime, valueX, yPos);
        yPos += 7;
        
        // Health Worker
        doc.setFont('helvetica', 'bold');
        doc.text('Health Worker:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.healthWorker, valueX, yPos);
        yPos += 7;
        
        // Position
        doc.setFont('helvetica', 'bold');
        doc.text('Position:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.position, valueX, yPos);
        yPos += 7;
        
        // Reason for Visit
        doc.setFont('helvetica', 'bold');
        doc.text('Reason for Visit:', labelX, yPos);
        doc.setFont('helvetica', 'normal');
        doc.text(appointmentData.reason, valueX, yPos);
        yPos += 12;
        
        // Important Notes Section with background
        const notesStartY = yPos;
        const notesHeight = 55;
        doc.setFillColor(248, 249, 250);
        doc.setDrawColor(200, 200, 200);
        doc.roundedRect(15, notesStartY, pageWidth - 30, notesHeight, 2, 2, 'FD');
        
        yPos += 6;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.text('Important Notes:', labelX + 3, yPos);
        
        yPos += 6;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        
        const notes = [
            '• Please arrive 15 minutes before your scheduled appointment time',
            '• Bring this slip and a valid ID',
            '• If you need to cancel or reschedule, please do so at least 24 hours in advance',
            '• Follow health protocols (wear mask if required)',
            '• For any questions or concerns, contact the health center'
        ];
        
        notes.forEach(note => {
            doc.text(note, labelX + 3, yPos);
            yPos += 5;
        });
        
        // QR Code
        const qrData = `Appointment ID: ${appointmentData.id}`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrData)}`;
        
        try {
            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.src = qrUrl;
            
            await new Promise((resolve) => {
                img.onload = () => {
                    doc.addImage(img, 'PNG', pageWidth - 55, notesStartY + 18, 25, 25);
                    resolve();
                };
                img.onerror = () => resolve();
                setTimeout(resolve, 3000);
            });
        } catch (error) {
            console.log('QR code generation skipped');
        }
        
        // QR Code label
        doc.setFontSize(7);
        doc.text('Scan for quick check-in', pageWidth - 42.5, notesStartY + 47, { align: 'center' });
        
        // Footer
        yPos = notesStartY + notesHeight + 10;
        doc.setLineWidth(0.3);
        doc.line(15, yPos - 3, pageWidth - 15, yPos - 3);
        
        doc.setFontSize(8);
        doc.setFont('helvetica', 'italic');
        doc.setTextColor(100);
        doc.text('This is an automatically generated appointment slip. For verification, please contact the health center.', centerX, yPos + 3, { align: 'center', maxWidth: pageWidth - 30 });
        
        // Save PDF
        const fileName = `Appointment_Slip_${appointmentData.id}.pdf`;
        doc.save(fileName);
    }
    </script>
</body>
</html> 