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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
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
                $result = $stmt->execute([
                    $patient['patient_id'],
                    $health_worker_id,
                    $appointment_date,
                    $appointment_time,
                    $status_id,
                    $reason,
                    $notes
                ]);
                
                if ($result) {
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
        
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: 500;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #333;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px;
            width: 100%;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: #4a90e2;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a7bc8;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }
        
        .required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <h1>Schedule an Appointment</h1>
                <p class="text-muted">Book your appointment with a health worker</p>
            </div>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Appointment Details</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="appointment_date" class="required">Date</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" required
                                min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="appointment_time" class="required">Time</label>
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
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Appointment</label>
                        <input type="text" class="form-control" id="reason" name="reason" placeholder="Brief reason for the appointment">
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information the health worker should know"></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Schedule Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html> 