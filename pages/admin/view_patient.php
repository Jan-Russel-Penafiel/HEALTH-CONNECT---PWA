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
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            color: #333;
            margin: 0;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
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

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .record-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            transition: transform 0.2s;
        }

        .record-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }

        .card-subtitle {
            font-size: 14px;
            color: #666;
            margin: 5px 0 0;
        }

        .card-body {
            padding: 5px 0;
        }

        .card-item {
            margin-bottom: 8px;
            display: flex;
        }

        .card-item-label {
            font-weight: 600;
            color: #666;
            width: 100px;
            flex-shrink: 0;
        }

        .card-item-value {
            color: #333;
            flex-grow: 1;
        }

        .card-footer {
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .empty-state i {
            font-size: 2em;
            color: #ccc;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .info-grid, .cards-grid {
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
            
            <?php if (!empty($recent_appointments)): ?>
                <div class="cards-grid">
                    <?php foreach ($recent_appointments as $appointment): ?>
                        <div class="record-card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-calendar-check"></i> 
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                </h4>
                                <p class="card-subtitle">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                </p>
                            </div>
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-label">Health Worker:</div>
                                    <div class="card-item-value">
                                        <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                        <div><small><?php echo htmlspecialchars($appointment['position']); ?></small></div>
                                    </div>
                                </div>
                                <?php if (!empty($appointment['reason'])): ?>
                                <div class="card-item">
                                    <div class="card-item-label">Reason:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <span class="status-badge status-<?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h4>No Appointments Found</h4>
                    <p>This patient has no appointment records.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Immunization Records -->
        <div class="records-section">
            <div class="section-header">
                <h3 class="section-title">Recent Immunizations</h3>
                <a href="../health_worker/immunization.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            
            <?php if (!empty($immunization_records)): ?>
                <div class="cards-grid">
                    <?php foreach ($immunization_records as $record): ?>
                        <div class="record-card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-syringe"></i> 
                                    <?php echo htmlspecialchars($record['immunization_name']); ?>
                                </h4>
                                <p class="card-subtitle">
                                    Dose <?php echo htmlspecialchars($record['dose_number']); ?>
                                </p>
                            </div>
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-label">Date:</div>
                                    <div class="card-item-value">
                                        <?php echo date('M d, Y', strtotime($record['date_administered'])); ?>
                                    </div>
                                </div>
                                <div class="card-item">
                                    <div class="card-item-label">Health Worker:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($record['health_worker_name']); ?></div>
                                </div>
                                <?php if (!empty($record['notes'])): ?>
                                <div class="card-item">
                                    <div class="card-item-label">Notes:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($record['notes']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <?php if ($record['next_schedule_date']): ?>
                                    <span>
                                        <i class="fas fa-calendar-alt"></i> 
                                        Next: <?php echo date('M d, Y', strtotime($record['next_schedule_date'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span>No follow-up scheduled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-notes-medical"></i>
                    <h4>No Immunization Records Found</h4>
                    <p>This patient has no immunization records.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Medical Records -->
        <div class="records-section">
            <div class="section-header">
                <h3 class="section-title">Recent Medical Records</h3>
                <a href="../health_worker/medical_history.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            
            <?php if (!empty($medical_records)): ?>
                <div class="cards-grid">
                    <?php foreach ($medical_records as $record): ?>
                        <div class="record-card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="fas fa-file-medical"></i> 
                                    Visit: <?php echo date('M d, Y', strtotime($record['visit_date'])); ?>
                                </h4>
                                <p class="card-subtitle">
                                    <i class="fas fa-user-md"></i> 
                                    <?php echo htmlspecialchars($record['health_worker_name']); ?>
                                </p>
                            </div>
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-label">Complaint:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($record['chief_complaint']); ?></div>
                                </div>
                                <div class="card-item">
                                    <div class="card-item-label">Diagnosis:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($record['diagnosis']); ?></div>
                                </div>
                                <?php if (!empty($record['treatment'])): ?>
                                <div class="card-item">
                                    <div class="card-item-label">Treatment:</div>
                                    <div class="card-item-value"><?php echo htmlspecialchars($record['treatment']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <?php if ($record['follow_up_date']): ?>
                                    <span>
                                        <i class="fas fa-calendar-plus"></i> 
                                        Follow-up: <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span>No follow-up scheduled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h4>No Medical Records Found</h4>
                    <p>This patient has no medical records.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 