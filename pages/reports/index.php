<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

$database = new Database();
$conn = $database->getConnection();

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['report_type'])) {
        switch ($_POST['report_type']) {
            case 'appointments':
                $query = "SELECT a.*, 
                         CONCAT(p_u.first_name, ' ', p_u.last_name) as patient_name,
                         CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
                         s.status_name
                         FROM appointments a
                         JOIN patients p ON a.patient_id = p.patient_id
                         JOIN users p_u ON p.user_id = p_u.user_id
                         JOIN health_workers h ON a.health_worker_id = h.health_worker_id
                         JOIN users h_u ON h.user_id = h_u.user_id
                         JOIN appointment_status s ON a.status_id = s.status_id
                         WHERE a.appointment_date BETWEEN :start_date AND :end_date
                         ORDER BY a.appointment_date, a.appointment_time";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':start_date' => $_POST['start_date'],
                    ':end_date' => $_POST['end_date']
                ]);
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'patients':
                $query = "SELECT p.*, u.*,
                         (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) as appointment_count,
                         (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.patient_id) as record_count,
                         (SELECT COUNT(*) FROM immunization_records ir WHERE ir.patient_id = p.patient_id) as immunization_count
                         FROM patients p
                         JOIN users u ON p.user_id = u.user_id
                         ORDER BY u.last_name, u.first_name";
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'medical_records':
                $query = "SELECT mr.*, 
                         CONCAT(p_u.first_name, ' ', p_u.last_name) as patient_name,
                         CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name
                         FROM medical_records mr
                         JOIN patients p ON mr.patient_id = p.patient_id
                         JOIN users p_u ON p.user_id = p_u.user_id
                         JOIN health_workers h ON mr.health_worker_id = h.health_worker_id
                         JOIN users h_u ON h.user_id = h_u.user_id
                         WHERE mr.visit_date BETWEEN :start_date AND :end_date
                         ORDER BY mr.visit_date DESC";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':start_date' => $_POST['start_date'],
                    ':end_date' => $_POST['end_date']
                ]);
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'appointment_slip':
                $query = "SELECT a.*, 
                         CONCAT(p_u.first_name, ' ', p_u.last_name) as patient_name,
                         p_u.mobile_number as patient_mobile,
                         CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
                         h_u.position as health_worker_position,
                         s.status_name
                         FROM appointments a
                         JOIN patients p ON a.patient_id = p.patient_id
                         JOIN users p_u ON p.user_id = p_u.user_id
                         JOIN health_workers h ON a.health_worker_id = h.health_worker_id
                         JOIN users h_u ON h.user_id = h_u.user_id
                         JOIN appointment_status s ON a.status_id = s.status_id
                         WHERE a.appointment_id = :appointment_id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':appointment_id' => $_POST['appointment_id']]);
                $report_data = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
        }
    }
}

// Get all appointments for dropdown
$query = "SELECT a.appointment_id, 
          CONCAT(u.first_name, ' ', u.last_name) as patient_name,
          a.appointment_date, a.appointment_time
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON p.user_id = u.user_id
          WHERE a.appointment_date >= CURDATE()
          ORDER BY a.appointment_date, a.appointment_time";
$stmt = $conn->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h2>Generate Reports</h2>
        </div>
        
        <div class="dashboard-section">
            <div class="report-types">
                <div class="report-card" onclick="showReportForm('appointments')">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Appointments List</h3>
                    <p>Generate a list of appointments for a specific date range</p>
                </div>
                
                <div class="report-card" onclick="showReportForm('patients')">
                    <i class="fas fa-users"></i>
                    <h3>Patient List</h3>
                    <p>Generate a comprehensive list of all patients</p>
                </div>
                
                <div class="report-card" onclick="showReportForm('medical_records')">
                    <i class="fas fa-file-medical"></i>
                    <h3>Medical Records</h3>
                    <p>Generate medical records report for a specific date range</p>
                </div>
                
                <div class="report-card" onclick="showReportForm('appointment_slip')">
                    <i class="fas fa-receipt"></i>
                    <h3>Appointment Slip</h3>
                    <p>Generate an appointment slip for a specific appointment</p>
                </div>
            </div>
            
            <!-- Report Forms -->
            <div id="appointmentsForm" class="report-form" style="display: none;">
                <h3>Generate Appointments List</h3>
                <form method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="appointments">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Generate Report</button>
                </form>
            </div>
            
            <div id="patientsForm" class="report-form" style="display: none;">
                <h3>Generate Patient List</h3>
                <form method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="patients">
                    <p>This report will generate a comprehensive list of all patients with their details and statistics.</p>
                    <button type="submit" class="btn">Generate Report</button>
                </form>
            </div>
            
            <div id="medicalRecordsForm" class="report-form" style="display: none;">
                <h3>Generate Medical Records Report</h3>
                <form method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="medical_records">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Generate Report</button>
                </form>
            </div>
            
            <div id="appointmentSlipForm" class="report-form" style="display: none;">
                <h3>Generate Appointment Slip</h3>
                <form method="POST" target="_blank">
                    <input type="hidden" name="report_type" value="appointment_slip">
                    
                    <div class="form-group">
                        <label for="appointment_id">Select Appointment</label>
                        <select id="appointment_id" name="appointment_id" class="form-control" required>
                            <option value="">Select an appointment</option>
                            <?php foreach ($appointments as $appointment): ?>
                                <option value="<?php echo $appointment['appointment_id']; ?>">
                                    <?php 
                                        echo htmlspecialchars($appointment['patient_name']) . ' - ' . 
                                             date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']));
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Generate Slip</button>
                </form>
            </div>
            
            <!-- Report Preview -->
            <?php if (isset($report_data)): ?>
                <div class="report-preview">
                    <?php if ($_POST['report_type'] === 'appointment_slip'): ?>
                        <!-- Appointment Slip Format -->
                        <div class="appointment-slip">
                            <div class="slip-header">
                                <h2>Brgy. Poblacion Health Center</h2>
                                <h3>Appointment Slip</h3>
                            </div>
                            
                            <div class="slip-content">
                                <p><strong>Patient:</strong> <?php echo htmlspecialchars($report_data['patient_name']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($report_data['patient_mobile']); ?></p>
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($report_data['appointment_date'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($report_data['appointment_time'])); ?></p>
                                <p><strong>Health Worker:</strong> <?php echo htmlspecialchars($report_data['health_worker_name']); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($report_data['health_worker_position']); ?></p>
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($report_data['status_name']); ?></p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($report_data['reason']); ?></p>
                                
                                <div class="slip-notes">
                                    <p>Please arrive 15 minutes before your scheduled appointment.</p>
                                    <p>Bring this slip and any relevant medical records.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Table Format for Other Reports -->
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div class="report-actions">
                        <button onclick="window.print()" class="btn">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        function showReportForm(type) {
            // Hide all forms
            document.querySelectorAll('.report-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Show selected form
            document.getElementById(type + 'Form').style.display = 'block';
            
            // Scroll to form
            document.getElementById(type + 'Form').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html> 