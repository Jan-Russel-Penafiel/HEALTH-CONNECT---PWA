<?php
session_start();
require_once '../includes/config/database.php';
require_once '../includes/auth_check.php';

$database = new Database();
$conn = $database->getConnection();

// Get total appointments
$query = "SELECT COUNT(*) as total FROM appointments";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total patients
$query = "SELECT COUNT(*) as total FROM patients";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get today's appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()";
$stmt = $conn->prepare($query);
$stmt->execute();
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get appointment statistics by status
$query = "SELECT s.status_name, COUNT(a.appointment_id) as count
          FROM appointment_status s
          LEFT JOIN appointments a ON s.status_id = a.status_id
          GROUP BY s.status_id, s.status_name
          ORDER BY s.status_id";
$stmt = $conn->prepare($query);
$stmt->execute();
$appointment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily appointments for the last 7 days
$query = "SELECT DATE(appointment_date) as date, COUNT(*) as count
          FROM appointments
          WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          GROUP BY DATE(appointment_date)
          ORDER BY date";
$stmt = $conn->prepare($query);
$stmt->execute();
$daily_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent appointments
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
          WHERE a.appointment_date >= CURDATE()
          ORDER BY a.appointment_date, a.appointment_time
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HealthConnect</title>
    <?php include '../includes/header_links.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h2>Dashboard</h2>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Appointments</h3>
                <div class="number"><?php echo number_format($total_appointments); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Patients</h3>
                <div class="number"><?php echo number_format($total_patients); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Today's Appointments</h3>
                <div class="number"><?php echo number_format($today_appointments); ?></div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="dashboard-section">
            <div class="charts-grid">
                <!-- Appointment Status Chart -->
                <div class="chart-container">
                    <h3>Appointment Status Distribution</h3>
                    <canvas id="statusChart"></canvas>
                </div>
                
                <!-- Daily Appointments Chart -->
                <div class="chart-container">
                    <h3>Daily Appointments (Last 7 Days)</h3>
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Appointments -->
        <div class="dashboard-section">
            <h3>Upcoming Appointments</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Health Worker</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <?php 
                                        echo date('M j, Y', strtotime($appointment['appointment_date'])) . '<br>' .
                                             date('g:i A', strtotime($appointment['appointment_time']));
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['health_worker_name']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo htmlspecialchars($appointment['status_name']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Prepare chart data
        const statusData = {
            labels: <?php echo json_encode(array_column($appointment_stats, 'status_name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($appointment_stats, 'count')); ?>,
                backgroundColor: [
                    '#4CAF50', // Scheduled
                    '#2196F3', // Confirmed
                    '#FFC107', // Completed
                    '#F44336', // Cancelled
                    '#9E9E9E'  // No Show
                ]
            }]
        };
        
        const dailyData = {
            labels: <?php 
                $dates = array_column($daily_appointments, 'date');
                $formatted_dates = array_map(function($date) {
                    return date('M j', strtotime($date));
                }, $dates);
                echo json_encode($formatted_dates);
            ?>,
            datasets: [{
                label: 'Appointments',
                data: <?php echo json_encode(array_column($daily_appointments, 'count')); ?>,
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4,
                fill: true
            }]
        };
        
        // Initialize charts
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        new Chart(document.getElementById('dailyChart'), {
            type: 'line',
            data: dailyData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html> 