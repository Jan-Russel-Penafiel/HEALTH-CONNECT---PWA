<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Check if appointment_id is provided
if (!isset($_GET['id'])) {
    header('Location: /connect/pages/health_worker/appointments.php');
    exit();
}

$appointment_id = $_GET['id'];

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
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

// Get appointment details
try {
    $query = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.notes, 
                     a.status_id, a.reason, a.created_at, a.updated_at, 
                     COALESCE(a.sms_notification_sent, 0) as sms_notification_sent,
                     u.first_name, u.last_name, u.email, 
                     COALESCE(u.mobile_number, '') as patient_phone,
                     u.date_of_birth, u.address,
                     p.patient_id, 
                     COALESCE(p.blood_type, '') as blood_type, 
                     COALESCE(p.height, 0) as height,
                     COALESCE(p.weight, 0) as weight,
                     COALESCE(p.emergency_contact_name, '') as emergency_contact_name, 
                     COALESCE(p.emergency_contact_number, '') as emergency_contact_number,
                     COALESCE(p.emergency_contact_relationship, '') as emergency_contact_relationship,
                     s.status_name as status,
                     hw_user.first_name as hw_first_name, hw_user.last_name as hw_last_name
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users hw_user ON hw.user_id = hw_user.user_id
              WHERE a.appointment_id = ? AND a.health_worker_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $health_worker_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        error_log("Appointment not found or not authorized. ID: $appointment_id, Health Worker ID: $health_worker_id");
        header('Location: /connect/pages/health_worker/appointments.php?error=not_found');
        exit();
    }

} catch (PDOException $e) {
    error_log("Error fetching appointment: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    error_log("SQL State: " . $pdo->errorInfo()[0]);
    header('Location: /connect/pages/health_worker/appointments.php?error=database&msg=' . urlencode($e->getMessage()));
    exit();
}

// Get patient's medical history
try {
    $query = "SELECT mr.record_id, mr.visit_date, mr.diagnosis, mr.treatment, mr.notes,
                     u.first_name as doctor_first_name, u.last_name as doctor_last_name
              FROM medical_records mr
              LEFT JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              LEFT JOIN users u ON hw.user_id = u.user_id
              WHERE mr.patient_id = ?
              ORDER BY mr.visit_date DESC
              LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment['patient_id']]);
    $medical_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching medical history: " . $e->getMessage());
    $medical_history = [];
}

// Get patient's immunization records
try {
    $query = "SELECT i.immunization_id, i.administered_date, 
                     v.vaccine_name, v.description as vaccine_description
              FROM immunizations i
              JOIN vaccines v ON i.vaccine_id = v.vaccine_id
              WHERE i.patient_id = ?
              ORDER BY i.administered_date DESC
              LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment['patient_id']]);
    $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching immunizations: " . $e->getMessage());
    $immunizations = [];
}

// Calculate patient's age
$age = '';
if ($appointment['date_of_birth']) {
    $dob = new DateTime($appointment['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- jsPDF for printing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .page-container {
            padding-top: 80px;
            padding-bottom: 30px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
        }

        .back-link i {
            font-size: 1rem;
        }

        .appointment-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .appointment-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .appointment-header h1 i {
            font-size: 1.5rem;
        }

        .appointment-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .appointment-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .appointment-meta-item i {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 992px) {
            .content-grid {
                grid-template-columns: 2fr 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .card-header i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            margin-bottom: 1rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: var(--text-primary);
            font-size: 1rem;
            line-height: 1.5;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-badge.scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-badge.confirmed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.completed, 
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

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .action-buttons .btn {
            flex: 1;
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
        }

        .btn-success {
            background: #28a745;
            border: none;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3);
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            color: #000;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
        }

        .history-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .history-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .history-item:last-child {
            margin-bottom: 0;
        }

        .history-date {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .history-diagnosis {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .history-doctor {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .notes-section {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .notes-section h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notes-section p {
            margin: 0;
            color: #666;
        }

        .sms-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .sms-indicator.sent {
            background: #d4edda;
            color: #155724;
        }

        .sms-indicator.not-sent {
            background: #f8d7da;
            color: #721c24;
        }

        .immunization-item {
            padding: 1rem;
            background: #f0f8ff;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #2196f3;
            transition: all 0.3s ease;
        }

        .immunization-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }

        .immunization-item:last-child {
            margin-bottom: 0;
        }

        .immunization-date {
            font-weight: 600;
            color: #2196f3;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .immunization-vaccine {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .immunization-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .appointment-header {
                padding: 1.5rem;
            }

            .appointment-header h1 {
                font-size: 1.5rem;
            }

            .appointment-meta {
                flex-direction: column;
                gap: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
                    <i id="toastIcon" class="fas fa-check-circle me-2"></i>
                    <span id="toastMessage"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <div class="container page-container">
        <a href="/connect/pages/health_worker/appointments.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Appointments
        </a>

        <div class="appointment-header">
            <h1>
                <i class="fas fa-calendar-check"></i>
                Appointment Details
            </h1>
            <div class="appointment-meta">
                <div class="appointment-meta-item">
                    <i class="fas fa-user"></i>
                    <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                </div>
                <div class="appointment-meta-item">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                </div>
                <div class="appointment-meta-item">
                    <i class="fas fa-clock"></i>
                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                </div>
                <div class="appointment-meta-item">
                    <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $appointment['status'])); ?>">
                        <?php echo htmlspecialchars($appointment['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Patient Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-circle"></i>
                        <h2>Patient Information</h2>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <div class="info-value"><?php echo $age ? $age . ' years old' : 'N/A'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo $appointment['date_of_birth'] ? date('F j, Y', strtotime($appointment['date_of_birth'])) : 'N/A'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Blood Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['blood_type'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Height</div>
                            <div class="info-value"><?php echo $appointment['height'] > 0 ? $appointment['height'] . ' cm' : 'N/A'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Weight</div>
                            <div class="info-value"><?php echo $appointment['weight'] > 0 ? $appointment['weight'] . ' kg' : 'N/A'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['patient_phone'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['address'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-phone-alt"></i>
                        <h2>Emergency Contact</h2>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Contact Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['emergency_contact_name'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['emergency_contact_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Relationship</div>
                            <div class="info-value"><?php echo htmlspecialchars($appointment['emergency_contact_relationship'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h2>Appointment Details</h2>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Reason for Visit</div>
                        <div class="info-value"><?php echo htmlspecialchars($appointment['reason'] ?: 'N/A'); ?></div>
                    </div>
                    <?php if ($appointment['notes']): ?>
                    <div class="notes-section">
                        <h3><i class="fas fa-sticky-note"></i> Notes</h3>
                        <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="info-item" style="margin-top: 1rem;">
                        <div class="info-label">Health Worker</div>
                        <div class="info-value">Dr. <?php echo htmlspecialchars($appointment['hw_first_name'] . ' ' . $appointment['hw_last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created At</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($appointment['updated_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">SMS Notification</div>
                        <div class="info-value">
                            <span class="sms-indicator <?php echo $appointment['sms_notification_sent'] ? 'sent' : 'not-sent'; ?>">
                                <i class="fas <?php echo $appointment['sms_notification_sent'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $appointment['sms_notification_sent'] ? 'Sent' : 'Not Sent'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Medical History -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-medical"></i>
                        <h2>Recent Medical History</h2>
                    </div>
                    <?php if (!empty($medical_history)): ?>
                        <?php foreach ($medical_history as $record): ?>
                        <div class="history-item">
                            <div class="history-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($record['visit_date'])); ?>
                            </div>
                            <div class="history-diagnosis">
                                <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?>
                            </div>
                            <?php if ($record['treatment']): ?>
                            <div class="history-diagnosis">
                                <strong>Treatment:</strong> <?php echo htmlspecialchars($record['treatment']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($record['doctor_first_name']): ?>
                            <div class="history-doctor">
                                <i class="fas fa-user-md"></i>
                                Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <a href="/connect/pages/health_worker/view_medical_history.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-secondary" style="margin-top: 1rem;">
                            <i class="fas fa-history"></i>
                            View Full History
                        </a>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No medical history available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Immunization Records -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-syringe"></i>
                        <h2>Recent Immunizations</h2>
                    </div>
                    <?php if (!empty($immunizations)): ?>
                        <?php foreach ($immunizations as $immunization): ?>
                        <div class="immunization-item">
                            <div class="immunization-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($immunization['administered_date'])); ?>
                            </div>
                            <div class="immunization-vaccine">
                                <?php echo htmlspecialchars($immunization['vaccine_name']); ?>
                            </div>
                            <?php if ($immunization['vaccine_description']): ?>
                            <div class="immunization-description">
                                <?php echo htmlspecialchars($immunization['vaccine_description']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <a href="/connect/pages/health_worker/view_immunization.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-secondary" style="margin-top: 1rem;">
                            <i class="fas fa-history"></i>
                            View Full Immunization Record
                        </a>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>No immunization records available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <?php if ($appointment['status_id'] != 5): // Hide Quick Actions for No Show ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i>
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="action-buttons" style="flex-direction: column;">
                        <?php if ($appointment['status_id'] == 1): // Scheduled ?>
                            <button onclick="updateStatus(<?php echo $appointment_id; ?>, 'confirmed')" class="btn btn-success">
                                <i class="fas fa-sms"></i>
                                Confirm & Send SMS
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($appointment['status_id'] == 2 && !empty($appointment['patient_phone'])): // Confirmed ?>
                            <button onclick="sendSMSReminder(<?php echo $appointment_id; ?>)" class="btn btn-warning">
                                <i class="fas fa-bell"></i>
                                Send SMS Reminder
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($appointment['status_id'] != 3 && $appointment['status_id'] != 4): // Not completed or cancelled ?>
                            <button onclick="updateStatus(<?php echo $appointment_id; ?>, 'done')" class="btn btn-primary">
                                <i class="fas fa-check-double"></i>
                                Mark as Done
                            </button>
                            <button onclick="updateStatus(<?php echo $appointment_id; ?>, 'no show')" class="btn btn-warning">
                                <i class="fas fa-user-times"></i>
                                Mark as No Show
                            </button>
                        <?php endif; ?>
                        
                        <a href="/connect/pages/health_worker/add_medical_record.php?patient_id=<?php echo $appointment['patient_id']; ?>&appointment_id=<?php echo $appointment_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Medical Record
                        </a>
                        
                        <button onclick="printAppointmentSlip()" class="btn btn-secondary">
                            <i class="fas fa-print"></i>
                            Print Appointment Slip
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <script>
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

    // Function to update status
    function updateStatus(id, status) {
        let confirmMessage = 'Are you sure you want to mark this appointment as ' + status + '?';
        if (status === 'confirmed') {
            confirmMessage = 'Confirm this appointment and send SMS notification to the patient?';
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
                    
                    // If there's an SMS result, show it as well
                    if (data.sms_result) {
                        const smsMessage = data.sms_result.success ? 
                            '✓ SMS notification sent successfully!' : 
                            'SMS: ' + data.sms_result.message;
                        const smsStatus = data.sms_result.success ? 'success' : 'info';
                        
                        setTimeout(() => {
                            showToast(smsMessage, smsStatus);
                        }, 1000);
                        
                        // If SMS was sent, redirect faster
                        if (data.sms_result.success) {
                            setTimeout(() => {
                                window.location.href = '/connect/pages/health_worker/appointments.php?status_update=success&sms_sent=1&message=' + encodeURIComponent(data.message);
                            }, 2000);
                            return;
                        }
                    }
                    
                    // Redirect back to appointments page
                    setTimeout(() => {
                        window.location.href = '/connect/pages/health_worker/appointments.php?status_update=success&message=' + encodeURIComponent(data.message);
                    }, 1500);
                } else {
                    showToast(data.message || 'Failed to update appointment status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating the appointment status', 'error');
            });
        }
    }
    
    // Function to send SMS reminder
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

    // Check for URL parameters and show toast if needed
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const status = urlParams.get('status');

        if (message) {
            const decodedMessage = decodeURIComponent(message);
            showToast(decodedMessage, status || 'info');
            
            // Clean up URL without reloading the page
            const newUrl = window.location.pathname + '?id=<?php echo $appointment_id; ?>';
            window.history.replaceState({}, document.title, newUrl);
        }
    });

    // Function to print appointment slip
    async function printAppointmentSlip() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a5'
        });
        
        const pageWidth = doc.internal.pageSize.getWidth();
        const centerX = pageWidth / 2;
        
        // Appointment data from PHP
        const appointmentData = {
            id: <?php echo json_encode($appointment_id); ?>,
            patientName: <?php echo json_encode($appointment['first_name'] . ' ' . $appointment['last_name']); ?>,
            patientPhone: <?php echo json_encode($appointment['patient_phone'] ?: 'N/A'); ?>,
            appointmentDate: <?php echo json_encode(date('l, F j, Y', strtotime($appointment['appointment_date']))); ?>,
            appointmentTime: <?php echo json_encode(date('g:i A', strtotime($appointment['appointment_time']))); ?>,
            healthWorker: <?php echo json_encode($appointment['hw_first_name'] . ' ' . $appointment['hw_last_name']); ?>,
            position: 'Health Worker',
            reason: <?php echo json_encode($appointment['reason'] ?: 'Not specified'); ?>
        };
        
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
        
        showToast('Appointment slip generated successfully!', 'success');
    }
    </script>
</body>
</html>
        const dateStr = appointmentData.appointmentDate.replace(/[,\\s]+/g, '_');
        const fileName = `Appointment_Slip_${formatName}_${dateStr}_${new Date().toISOString().split('T')[0].replace(/-/g, '')}.pdf`;
        doc.save(fileName);
        
        showToast('Appointment slip generated successfully!', 'success');
    }
    </script>
</body>
</html>
