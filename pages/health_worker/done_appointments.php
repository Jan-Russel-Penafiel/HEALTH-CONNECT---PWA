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
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

try {
    // Get completed appointments
    $query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.notes, a.status_id, a.reason,
                     u.first_name, u.last_name, u.email, u.mobile_number as patient_phone,
                     s.status_name as status
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.health_worker_id = ? AND a.status_id = 3
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Appointments - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
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

        .appointment-date {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .appointments-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Completed Appointments</h1>
            <div class="header-actions">
                <a href="appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar"></i> Active Appointments
                </a>
            </div>
        </div>

        <div class="appointments-grid">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Completed Appointments</h3>
                <p>There are no completed appointments to display.</p>
            </div>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                <div class="appointment-card">
                    <div class="appointment-date">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y', strtotime($appointment['appointment_date'])); ?></span>
                    </div>
                    
                    <div class="appointment-time">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
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
                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-info w-100">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 