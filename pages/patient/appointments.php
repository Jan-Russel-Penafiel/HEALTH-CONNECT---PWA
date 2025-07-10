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
          ORDER BY a.appointment_date DESC, a.appointment_time DESC";
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
        
        <div class="dashboard-header">
            <div></div>
            <a href="schedule_appointment.php" class="btn">
                <i class="fas fa-plus"></i> Schedule New Appointment
            </a>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Upcoming Appointments</h3>
            </div>
            
            <?php if (count($upcoming) > 0): ?>
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
                                <?php if ($appointment['status_id'] == 2): // Confirmed appointments ?>
                                    <a href="generate_slip.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" 
                                       target="_blank" 
                                       class="btn-print">
                                        <i class="fas fa-print"></i> Print Appointment Slip
                                    </a>
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
                    <a href="schedule_appointment.php" class="btn">Schedule Now</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Past Appointments -->
        <div class="dashboard-section">
            <div class="section-header">
                <h3>Past Appointments</h3>
            </div>
            
            <?php if (count($past) > 0): ?>
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
            </div>
            
            <?php if (count($cancelled) > 0): ?>
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
    function printAppointmentSlip(appointmentData) {
        // Get the appointment slip element
        const slipElement = document.getElementById('appointment-slip-' + appointmentData.id);
        
        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Appointment Slip</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        padding: 20px;
                    }
                    .slip-header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .slip-details {
                        margin: 20px 0;
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
                ${slipElement.innerHTML}
            </body>
            </html>
        `);
        
        // Print the window
        printWindow.document.close();
        printWindow.focus();
        
        // Add a small delay to ensure content is loaded
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
    </script>
</body>
</html> 