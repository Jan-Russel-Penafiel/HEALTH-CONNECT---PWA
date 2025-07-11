<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Get health worker ID from the health_workers table
$query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
$health_worker_id = $health_worker['health_worker_id'];

// Get today's date
$today = date('Y-m-d');

// Initialize variables with default values
$today_appointments = [];
$upcoming_appointments = [];
$total_patients = 0;
$total_appointments = 0;

try {
    // Get total unique patients (all time)
    $query = "SELECT COUNT(DISTINCT p.patient_id) as count 
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id 
              WHERE a.health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get total appointments (all time)
    $query = "SELECT COUNT(*) as count 
              FROM appointments 
              WHERE health_worker_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get today's appointments count
    $query = "SELECT COUNT(*) as count 
              FROM appointments 
              WHERE health_worker_id = ? 
              AND DATE(appointment_date) = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id, $today]);
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get upcoming appointments count (next 7 days)
    $query = "SELECT COUNT(*) as count 
              FROM appointments 
              WHERE health_worker_id = ? 
              AND DATE(appointment_date) > ? 
              AND DATE(appointment_date) <= DATE_ADD(?, INTERVAL 7 DAY)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id, $today, $today]);
    $upcoming_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get today's appointments details
    $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.mobile_number as patient_phone,
                s.status_name,
                s.status_id
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.health_worker_id = ? 
              AND DATE(a.appointment_date) = ?
              ORDER BY a.appointment_date ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id, $today]);
    $today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get upcoming appointments details
    $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.mobile_number as patient_phone,
                s.status_name,
                s.status_id
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON p.user_id = u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.health_worker_id = ? 
              AND DATE(a.appointment_date) > ? 
              AND DATE(a.appointment_date) <= DATE_ADD(?, INTERVAL 7 DAY)
              ORDER BY a.appointment_date ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id, $today, $today]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Worker Dashboard - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Health Worker Dashboard</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Patients</h3>
                <p class="number"><?php echo number_format($total_patients); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Total Appointments</h3>
                <p class="number"><?php echo number_format($total_appointments); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <h3>Today's Appointments</h3>
                <p class="number"><?php echo number_format($today_count); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>Upcoming Appointments</h3>
                <p class="number"><?php echo number_format($upcoming_count); ?></p>
            </div>
        </div>

        <div class="recent-activity">
            <h2>Today's Appointments</h2>
            <?php if (empty($today_appointments)): ?>
                <div class="no-activity">
                    <p>No appointments scheduled for today.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php 
                                    $fullName = trim($appointment['first_name'] . ' ' . 
                                               ($appointment['middle_name'] ? $appointment['middle_name'] . ' ' : '') . 
                                               $appointment['last_name']);
                                    echo htmlspecialchars($fullName); 
                                ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_phone']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo ucfirst($appointment['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($appointment['status_name'] === 'Scheduled'): ?>
                                        <button class="btn-action btn-edit" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'Done')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn-action btn-delete" onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>, 'Cancelled')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="recent-activity">
            <h2>Upcoming Appointments</h2>
            <?php if (empty($upcoming_appointments)): ?>
                <div class="no-activity">
                    <p>No upcoming appointments scheduled.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php 
                                    $fullName = trim($appointment['first_name'] . ' ' . 
                                               ($appointment['middle_name'] ? $appointment['middle_name'] . ' ' : '') . 
                                               $appointment['last_name']);
                                    echo htmlspecialchars($fullName); 
                                ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_phone']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo ucfirst($appointment['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
    function updateStatus(appointmentId, status) {
        // Convert status name to status_id
        const statusMap = {
            'Scheduled': 1,
            'Confirmed': 2,
            'Done': 3,
            'Cancelled': 4,
            'No Show': 5
        };
        
        const statusId = statusMap[status];
        
        if (confirm('Are you sure you want to mark this appointment as ' + status + '?')) {
            fetch(`/connect/api/appointments/update_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    status_id: statusId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating appointment status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the appointment status');
            });
        }
    }
    </script>
</body>
</html> 