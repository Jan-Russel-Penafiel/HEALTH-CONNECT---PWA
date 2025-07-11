<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker or admin
if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get patient ID from request
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header('Location: patients.php');
    exit();
}

try {
    // Get patient information
    $query = "SELECT p.*, 
              u.first_name, u.middle_name, u.last_name, u.email, u.mobile_number, u.gender, u.date_of_birth, u.address
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.patient_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php');
        exit();
    }
    
    // Query to get medical records for the patient
    $query = "SELECT mr.*, 
              CONCAT(hw_user.first_name, ' ', hw_user.last_name) as health_worker_name,
              hw.position as health_worker_position
              FROM medical_records mr
              JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
              JOIN users hw_user ON hw.user_id = hw_user.user_id
              WHERE mr.patient_id = ?
              ORDER BY mr.visit_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching medical history: " . $e->getMessage());
    header('Location: patients.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
    <style>
        .patient-info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .patient-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .patient-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .demographics {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9em;
        }
        
        .demographics span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-section {
            margin-bottom: 15px;
        }

        .info-section-title {
            font-size: 0.9em;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-section-title i {
            color: #4a90e2;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            font-size: 0.9em;
        }

        .info-item i {
            color: #4a90e2;
            width: 16px;
            text-align: center;
        }
        
        .medical-timeline {
            padding: 20px 0;
        }

        .timeline-item {
            position: relative;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .timeline-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-date {
            display: inline-block;
            padding: 8px 16px;
            background: #4a90e2;
            color: white;
            border-radius: 20px;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .timeline-content {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .timeline-content h4 {
            color: #2c3e50;
            font-size: 1em;
            margin: 15px 0 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline-content h4:first-child {
            margin-top: 0;
        }

        .timeline-content h4 i {
            color: #4a90e2;
            width: 20px;
        }

        .timeline-content p {
            color: #555;
            font-size: 0.95em;
            margin: 0;
            padding: 0 0 0 28px;
            line-height: 1.5;
        }

        .timeline-content .metadata {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
            padding: 15px 0 0 0;
            border-top: 1px solid #e0e0e0;
        }

        .timeline-content .metadata-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #666;
        }

        .timeline-content .metadata-item i {
            color: #4a90e2;
        }
        
        .health-worker-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        
        .health-worker-info i {
            color: #4a90e2;
        }
        
        .no-records {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            margin-top: 20px;
            grid-column: 1 / -1;
        }

        .no-records i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .no-records h3 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        
        .no-records p {
            color: #6c757d;
            margin: 0;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Medical History</h1>
            <div class="header-actions">
                <a href="patients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Patients
                </a>
            </div>
        </div>
        
        <div class="patient-info-card">
            <div class="patient-header">
                <h2 class="patient-name">
                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                    <?php if ($patient['middle_name']): ?>
                        <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                    <?php endif; ?>
                </h2>
                <div class="demographics">
                    <span>
                        <i class="fas fa-venus-mars"></i>
                        <?php echo htmlspecialchars($patient['gender']); ?>
                    </span>
                    <span>
                        <i class="fas fa-birthday-cake"></i>
                        <?php 
                            $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                            echo $age . ' years';
                        ?>
                    </span>
                    <?php if ($patient['blood_type']): ?>
                        <span>
                            <i class="fas fa-tint"></i>
                            <?php echo htmlspecialchars($patient['blood_type']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item" style="margin-bottom: 8px;">
                <i class="fas fa-phone"></i>
                <span><?php echo htmlspecialchars($patient['mobile_number']); ?></span>
            </div>
            
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-id-card"></i> Contact Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($patient['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($patient['address']); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($patient['height'] || $patient['weight']): ?>
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-weight"></i> Physical Data
                </div>
                <div class="info-grid">
                    <?php if ($patient['height']): ?>
                        <div class="info-item">
                            <i class="fas fa-ruler-vertical"></i>
                            <span><?php echo htmlspecialchars($patient['height']); ?> cm</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($patient['weight']): ?>
                        <div class="info-item">
                            <i class="fas fa-weight"></i>
                            <span><?php echo htmlspecialchars($patient['weight']); ?> kg</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="medical-timeline">
            <?php if (empty($records)): ?>
                <div class="no-records">
                    <i class="fas fa-file-medical"></i>
                    <h3>No Medical Records Found</h3>
                    <p>There are no medical records available for this patient.</p>
                </div>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo date('l, F j, Y', strtotime($record['visit_date'])); ?>
                        </div>
                        
                        <div class="health-worker-info">
                            <i class="fas fa-user-md"></i>
                            <span>Attended by: <strong><?php echo htmlspecialchars($record['health_worker_name']); ?></strong>
                            <?php if (!empty($record['health_worker_position'])): ?>
                                (<?php echo htmlspecialchars($record['health_worker_position']); ?>)
                            <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="timeline-content">
                            <h4><i class="fas fa-comment-medical"></i> Chief Complaint</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?></p>

                            <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>

                            <h4><i class="fas fa-procedures"></i> Treatment</h4>
                            <p><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>

                            <?php if (!empty($record['prescription'])): ?>
                                <h4><i class="fas fa-prescription"></i> Prescription</h4>
                                <p><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($record['notes'])): ?>
                                <h4><i class="fas fa-notes-medical"></i> Notes</h4>
                                <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                            <?php endif; ?>

                            <div class="metadata">
                                <?php if (!empty($record['follow_up_date'])): ?>
                                    <div class="metadata-item">
                                        <i class="fas fa-calendar-check"></i>
                                        Follow-up: <?php echo date('F j, Y', strtotime($record['follow_up_date'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="metadata-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('h:i A', strtotime($record['visit_date'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html> 