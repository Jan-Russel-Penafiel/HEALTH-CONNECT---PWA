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

// Initialize variables
$stats = [
    'total_patients' => 0,
    'total_health_workers' => 0,
    'total_appointments' => 0,
    'pending_appointments' => 0
];
$recent_activities = [];
$error_message = '';

// Data for charts
$monthly_appointments = [];
$appointment_status_counts = [];
$daily_appointments = [];

try {
    // Get total patients
    $query = "SELECT COUNT(*) as count FROM users u 
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient') 
              AND u.is_active = 1";
    $stmt = $conn->query($query);
    $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get total health workers
    $query = "SELECT COUNT(*) as count FROM users u 
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'health_worker') 
              AND u.is_active = 1";
    $stmt = $conn->query($query);
    $stats['total_health_workers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get total appointments
    $query = "SELECT COUNT(*) as count FROM appointments";
    $stmt = $conn->query($query);
    $stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get pending appointments (status_id = 1 for 'Scheduled')
    $query = "SELECT COUNT(*) as count FROM appointments WHERE status_id = 1";
    $stmt = $conn->query($query);
    $stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get recent activities (appointments)
    $query = "SELECT 
                a.appointment_date,
                a.appointment_time,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                CONCAT(h.first_name, ' ', h.last_name) as health_worker_name,
                s.status_name as status
              FROM appointments a
              JOIN patients pt ON a.patient_id = pt.patient_id
              JOIN users p ON pt.user_id = p.user_id
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users h ON hw.user_id = h.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly appointments for the current year
    $current_year = date('Y');
    $query = "SELECT 
                MONTH(appointment_date) as month, 
                COUNT(*) as count 
              FROM appointments 
              WHERE YEAR(appointment_date) = ?
              GROUP BY MONTH(appointment_date)
              ORDER BY month";
    $stmt = $conn->prepare($query);
    $stmt->execute([$current_year]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize all months with zero counts
    for ($i = 1; $i <= 12; $i++) {
        $monthly_appointments[$i] = 0;
    }
    
    // Fill in actual data
    foreach ($monthly_data as $data) {
        $monthly_appointments[$data['month']] = (int)$data['count'];
    }
    
    // Get appointment status distribution
    $query = "SELECT 
                s.status_name, 
                COUNT(*) as count 
              FROM appointments a
              JOIN appointment_status s ON a.status_id = s.status_id
              GROUP BY a.status_id";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_data as $data) {
        $appointment_status_counts[$data['status_name']] = (int)$data['count'];
    }
    
    // Get daily appointments for the last 7 days
    $query = "SELECT 
                appointment_date, 
                COUNT(*) as count 
              FROM appointments 
              WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              GROUP BY appointment_date
              ORDER BY appointment_date";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 7 days with zero counts
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_appointments[$date] = 0;
    }
    
    // Fill in actual data
    foreach ($daily_data as $data) {
        $daily_appointments[$data['appointment_date']] = (int)$data['count'];
    }

} catch (PDOException $e) {
    // Log error
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $error_message = "An error occurred while fetching dashboard data. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            margin: 20px 0;
            height: 300px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        @media (min-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .chart-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #333;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Patients</h3>
                <p><?php echo number_format($stats['total_patients']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-user-md"></i>
                <h3>Health Workers</h3>
                <p><?php echo number_format($stats['total_health_workers']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Total Appointments</h3>
                <p><?php echo number_format($stats['total_appointments']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>Pending Appointments</h3>
                <p><?php echo number_format($stats['pending_appointments']); ?></p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Monthly Appointments (<?php echo date('Y'); ?>)</h3>
                <canvas id="monthlyAppointmentsChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Appointment Status Distribution</h3>
                <canvas id="appointmentStatusChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <h3 class="chart-title">Daily Appointments (Last 7 Days)</h3>
            <canvas id="dailyAppointmentsChart"></canvas>
        </div>

        <div class="recent-activity">
            <h2>Recent Activity</h2>
            <div class="activity-list">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    Appointment: <?php echo htmlspecialchars($activity['patient_name']); ?> with 
                                    Dr. <?php echo htmlspecialchars($activity['health_worker_name']); ?>
                                </div>
                                <div class="activity-time">
                                    <?php 
                                        $date = new DateTime($activity['appointment_date'] . ' ' . $activity['appointment_time']);
                                        echo $date->format('F j, Y \a\t g:i A');
                                    ?>
                                    <span class="status-badge <?php echo strtolower($activity['status']); ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-activity">
                        <p>No recent activities to display</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        // Chart.js initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Appointments Chart
            const monthlyCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Number of Appointments',
                        data: [
                            <?php echo implode(',', $monthly_appointments); ?>
                        ],
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Appointment Status Chart
            const statusCtx = document.getElementById('appointmentStatusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                            $labels = array_map(function($key) { 
                                return "'$key'"; 
                            }, array_keys($appointment_status_counts));
                            echo implode(',', $labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Number of Appointments',
                        data: [
                            <?php echo implode(',', array_values($appointment_status_counts)); ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(153, 102, 255, 0.6)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Daily Appointments Chart
            const dailyCtx = document.getElementById('dailyAppointmentsChart').getContext('2d');
            const dailyChart = new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                            $dates = array_map(function($date) { 
                                return "'" . date('M d', strtotime($date)) . "'"; 
                            }, array_keys($daily_appointments));
                            echo implode(',', $dates);
                        ?>
                    ],
                    datasets: [{
                        label: 'Daily Appointments',
                        data: [
                            <?php echo implode(',', array_values($daily_appointments)); ?>
                        ],
                        fill: false,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        tension: 0.1,
                        pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 