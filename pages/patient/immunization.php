<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is patient
if ($_SESSION['role'] !== 'patient') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Get patient ID
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT patient_id FROM patients WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception("Patient record not found");
    }
    
    $patient_id = $patient['patient_id'];

    // Get immunization records
    $query = "SELECT ir.*, 
              it.name as immunization_name, 
              it.description as immunization_description,
              it.recommended_age, 
              it.dose_count,
              CONCAT(hw_u.first_name, ' ', hw_u.last_name) as health_worker_name
              FROM immunization_records ir 
              JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id 
              JOIN health_workers hw ON ir.health_worker_id = hw.health_worker_id
              JOIN users hw_u ON hw.user_id = hw_u.user_id
              WHERE ir.patient_id = :patient_id 
              ORDER BY ir.date_administered DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient_id]);
    $immunization_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recommended immunizations
    $query = "SELECT it.*,
              (SELECT COUNT(*) FROM immunization_records ir 
               WHERE ir.immunization_type_id = it.immunization_type_id 
               AND ir.patient_id = :patient_id) as doses_received,
              (SELECT MAX(next_schedule_date) FROM immunization_records ir 
               WHERE ir.immunization_type_id = it.immunization_type_id 
               AND ir.patient_id = :patient_id) as next_schedule_date
              FROM immunization_types it
              WHERE 1=1
              ORDER BY it.recommended_age ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient_id]);
    $recommended_immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching immunization data: " . $e->getMessage());
    $immunization_records = [];
    $recommended_immunizations = [];
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

// Group immunizations by type
$immunizations_by_type = [];
foreach ($immunization_records as $record) {
    $type_id = $record['immunization_type_id'];
    if (!isset($immunizations_by_type[$type_id])) {
        $immunizations_by_type[$type_id] = [];
    }
    $immunizations_by_type[$type_id][] = $record;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Records - HealthConnect</title>
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
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -36px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4CAF50;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #4CAF50;
        }
        
        .timeline-date {
            font-weight: 600;
            color: #2196F3;
            margin-bottom: 10px;
        }
        
        .timeline-content h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: #333;
        }
        
        .vaccine-details {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        
        .next-schedule {
            margin-top: 10px;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .badge-danger {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .badge-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .list-group-item {
            border: none;
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        .progress {
            height: 10px;
            border-radius: 10px;
            background-color: #f0f0f0;
            margin: 10px 0;
        }
        
        .progress-bar {
            border-radius: 10px;
        }
        
        .bg-success {
            background-color: #4CAF50 !important;
        }
        
        .bg-warning {
            background-color: #FFC107 !important;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        .btn-outline-primary {
            color: #2196F3;
            border-color: #2196F3;
        }
        
        .btn-outline-primary:hover {
            background-color: #2196F3;
            color: #fff;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Immunization Records</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="row">
            <!-- Immunization History -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Immunization History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($immunization_records)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-syringe fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No immunization records found.</p>
                        </div>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($immunization_records as $record): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y', strtotime($record['date_administered'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <h4><?php echo htmlspecialchars($record['immunization_name']); ?></h4>
                                    <p class="text-muted">
                                        Administered by <?php echo htmlspecialchars($record['health_worker_name']); ?>
                                    </p>
                                    <div class="vaccine-details">
                                        <p class="description">
                                            <?php echo htmlspecialchars($record['immunization_description']); ?>
                                        </p>
                                        <p>
                                            <strong>Dose:</strong> <?php echo $record['dose_number']; ?> of <?php echo $record['dose_count']; ?>
                                        </p>
                                        <?php if (!empty($record['next_schedule_date'])): ?>
                                        <p class="next-schedule">
                                            <strong>Next Dose:</strong> 
                                            <span class="badge <?php echo strtotime($record['next_schedule_date']) < time() ? 'badge-danger' : 'badge-info'; ?>">
                                                <?php echo date('M d, Y', strtotime($record['next_schedule_date'])); ?>
                                            </span>
                                        </p>
                                        <?php endif; ?>
                                        <?php if (!empty($record['notes'])): ?>
                                        <p class="notes">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recommended Vaccines -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Recommended Immunizations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recommended_immunizations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-muted">No recommended immunizations found.</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recommended_immunizations as $immunization): ?>
                            <div class="list-group-item">
                                <h5 class="mb-1"><?php echo htmlspecialchars($immunization['name']); ?></h5>
                                <p class="mb-1"><?php echo htmlspecialchars($immunization['description']); ?></p>
                                <div class="vaccine-status">
                                    <small class="text-muted">
                                        Recommended age: <?php echo htmlspecialchars($immunization['recommended_age']); ?>
                                    </small>
                                    <div class="progress mt-2">
                                        <?php
                                        $doses_received = (int)$immunization['doses_received'];
                                        $doses_required = (int)$immunization['dose_count'];
                                        $progress = ($doses_required > 0) ? ($doses_received / $doses_required) * 100 : 0;
                                        ?>
                                        <div class="progress-bar <?php echo $progress >= 100 ? 'bg-success' : 'bg-warning'; ?>" 
                                             role="progressbar" style="width: <?php echo $progress; ?>%">
                                            <?php echo $doses_received; ?>/<?php echo $doses_required; ?> doses
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Next Due Vaccines -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Upcoming Immunizations</h3>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php
                            $upcoming_immunizations = array_filter($recommended_immunizations, function($immunization) {
                                return !empty($immunization['next_schedule_date']) && 
                                       (int)$immunization['doses_received'] < (int)$immunization['dose_count'];
                            });

                            usort($upcoming_immunizations, function($a, $b) {
                                if (empty($a['next_schedule_date'])) return 1;
                                if (empty($b['next_schedule_date'])) return -1;
                                return strtotime($a['next_schedule_date']) - strtotime($b['next_schedule_date']);
                            });

                            if (empty($upcoming_immunizations)):
                            ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                                <p class="text-muted">No upcoming immunizations scheduled.</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_immunizations as $immunization): ?>
                                <div class="list-group-item">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($immunization['name']); ?></h5>
                                    <p class="mb-1">
                                        Next dose: <?php echo (int)$immunization['doses_received'] + 1; ?> of <?php echo $immunization['dose_count']; ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Scheduled for:</strong> 
                                        <span class="badge <?php echo strtotime($immunization['next_schedule_date']) < time() ? 'badge-danger' : 'badge-info'; ?>">
                                            <?php echo date('M d, Y', strtotime($immunization['next_schedule_date'])); ?>
                                        </span>
                                    </p>
                                    <small class="text-muted">
                                        <a href="../health_worker/appointments.php" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                                        </a>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 