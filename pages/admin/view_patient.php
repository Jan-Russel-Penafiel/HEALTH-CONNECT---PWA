<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Get patient ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get patient details
    $query = "SELECT u.*, p.*, 
              (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) as appointment_count,
              (SELECT COUNT(*) FROM immunization_records ir WHERE ir.patient_id = p.patient_id) as immunization_count,
              (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.patient_id) as medical_record_count
              FROM users u 
              JOIN patients p ON u.user_id = p.user_id
              WHERE u.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header('Location: patients.php');
        exit();
    }

    // Get recent appointments
    $query = "SELECT a.*, s.status_name, hw.position,
              CONCAT(hw_u.first_name, ' ', hw_u.last_name) as health_worker_name
              FROM appointments a 
              JOIN appointment_status s ON a.status_id = s.status_id
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE a.patient_id = :patient_id 
              ORDER BY a.appointment_date DESC, a.appointment_time DESC 
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':patient_id', $patient['patient_id'], PDO::PARAM_INT);
    $stmt->execute();
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get immunization records
    $query = "SELECT ir.*, it.name as immunization_name, 
              CONCAT(hw_u.first_name, ' ', hw_u.last_name) as health_worker_name
              FROM immunization_records ir
              JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id
              JOIN health_workers hw ON ir.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE ir.patient_id = :patient_id
              ORDER BY ir.date_administered DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':patient_id', $patient['patient_id'], PDO::PARAM_INT);
    $stmt->execute();
    $immunization_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get medical records
    $query = "SELECT mr.*, 
              CONCAT(hw_u.first_name, ' ', hw_u.last_name) as health_worker_name
              FROM medical_records mr
              JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE mr.patient_id = :patient_id
              ORDER BY mr.visit_date DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':patient_id', $patient['patient_id'], PDO::PARAM_INT);
    $stmt->execute();
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching patient details: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching patient details. Please try again.";
    header('Location: patients.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        .patient-info {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .patient-name {
            font-size: 24px;
            color: #333;
            margin: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
        }

        .records-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 18px;
            color: #333;
            margin: 0;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table th,
        .records-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .records-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-confirmed { background: #e8f5e9; color: #2e7d32; }
        .status-completed { background: #f5f5f5; color: #616161; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-no-show { background: #fff3e0; color: #ef6c00; }

        .btn-back {
            padding: 8px 16px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #e4e4e4;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="header-actions">
                <a href="patients.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Patients
                </a>
            </div>
        </div>

        <div class="patient-info">
            <div class="info-header">
                <h2 class="patient-name">
                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                    <?php if ($patient['middle_name']): ?>
                        <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                    <?php endif; ?>
                </h2>
                <a href="edit_patient.php?id=<?php echo $patient['user_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Patient
                </a>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Mobile Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['mobile_number']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['gender']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Blood Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['blood_type'] ?: 'Not specified'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Height</div>
                    <div class="info-value"><?php echo $patient['height'] ? htmlspecialchars($patient['height']) . ' cm' : 'Not specified'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Weight</div>
                    <div class="info-value"><?php echo $patient['weight'] ? htmlspecialchars($patient['weight']) . ' kg' : 'Not specified'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['address']); ?></div>
                </div>
            </div>

            <div class="info-grid" style="margin-top: 20px;">
                <div class="info-item">
                    <div class="info-label">Emergency Contact</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($patient['emergency_contact_name']); ?><br>
                        <?php echo htmlspecialchars($patient['emergency_contact_number']); ?><br>
                        <small>(<?php echo htmlspecialchars($patient['emergency_contact_relationship']); ?>)</small>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Record Summary</div>
                    <div class="info-value">
                        <div><?php echo $patient['appointment_count']; ?> appointments</div>
                        <div><?php echo $patient['immunization_count']; ?> immunizations</div>
                        <div><?php echo $patient['medical_record_count']; ?> medical records</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="records-section">
            <div class="section-header">
                <h3 class="section-title">Recent Appointments</h3>
                <a href="../health_worker/appointments.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Health Worker</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_appointments)): ?>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <?php 
                                        echo date('M d, Y', strtotime($appointment['appointment_date'])) . '<br>';
                                        echo date('h:i A', strtotime($appointment['appointment_time']));
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($appointment['health_worker_name']); ?><br>
                                    <small><?php echo htmlspecialchars($appointment['position']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No appointments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Immunization Records -->
        <div class="records-section">
            <div class="section-header">
                <h3 class="section-title">Recent Immunizations</h3>
                <a href="../health_worker/immunization.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Immunization</th>
                        <th>Health Worker</th>
                        <th>Next Schedule</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($immunization_records)): ?>
                        <?php foreach ($immunization_records as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date_administered'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($record['immunization_name']); ?><br>
                                    <small>Dose <?php echo htmlspecialchars($record['dose_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($record['health_worker_name']); ?></td>
                                <td>
                                    <?php 
                                        echo $record['next_schedule_date'] 
                                            ? date('M d, Y', strtotime($record['next_schedule_date']))
                                            : 'Not scheduled';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No immunization records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Medical Records -->
        <div class="records-section">
            <div class="section-header">
                <h3 class="section-title">Recent Medical Records</h3>
                <a href="../health_worker/medical_history.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Visit Date</th>
                        <th>Health Worker</th>
                        <th>Diagnosis</th>
                        <th>Follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($medical_records)): ?>
                        <?php foreach ($medical_records as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['health_worker_name']); ?></td>
                                <td>
                                    <strong>Complaint:</strong> <?php echo htmlspecialchars($record['chief_complaint']); ?><br>
                                    <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                                </td>
                                <td>
                                    <?php 
                                        echo $record['follow_up_date'] 
                                            ? date('M d, Y', strtotime($record['follow_up_date']))
                                            : 'Not scheduled';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No medical records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 