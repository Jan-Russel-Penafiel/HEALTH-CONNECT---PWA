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
    'appointments_today' => 0,
    'appointments_this_week' => 0,
    'appointments_this_month' => 0,
    'patients_today' => 0,
    'patients_this_week' => 0,
    'patients_this_month' => 0
];
$recent_activities = [];
$error_message = '';

// Data for charts
$monthly_appointments = [];
$weekly_appointments = [];
$daily_appointments = [];
$monthly_patients = [];
$weekly_patients = [];
$daily_patients = [];

try {
    // Get appointments today
    $query = "SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()";
    $stmt = $conn->query($query);
    $stats['appointments_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get appointments this week (Monday to Sunday)
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt = $conn->query($query);
    $stats['appointments_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get appointments this month
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE YEAR(appointment_date) = YEAR(CURDATE()) 
              AND MONTH(appointment_date) = MONTH(CURDATE())";
    $stmt = $conn->query($query);
    $stats['appointments_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients registered today
    $query = "SELECT COUNT(*) as count FROM users u 
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient') 
              AND DATE(u.created_at) = CURDATE()";
    $stmt = $conn->query($query);
    $stats['patients_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients registered this week
    $query = "SELECT COUNT(*) as count FROM users u 
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient') 
              AND YEARWEEK(u.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt = $conn->query($query);
    $stats['patients_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients registered this month
    $query = "SELECT COUNT(*) as count FROM users u 
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient') 
              AND YEAR(u.created_at) = YEAR(CURDATE()) 
              AND MONTH(u.created_at) = MONTH(CURDATE())";
    $stmt = $conn->query($query);
    $stats['patients_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

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

    // Get daily appointments for the last 7 days
    $query = "SELECT 
                DATE(appointment_date) as date, 
                COUNT(*) as count 
              FROM appointments 
              WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              GROUP BY DATE(appointment_date)
              ORDER BY date";
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
        $daily_appointments[$data['date']] = (int)$data['count'];
    }

    // Get weekly appointments for the last 8 weeks
    $query = "SELECT 
                YEARWEEK(appointment_date, 1) as week, 
                COUNT(*) as count 
              FROM appointments 
              WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
              GROUP BY YEARWEEK(appointment_date, 1)
              ORDER BY week";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 8 weeks with zero counts
    for ($i = 7; $i >= 0; $i--) {
        $week = date('oW', strtotime("-$i weeks"));
        $weekly_appointments[$week] = 0;
    }
    
    // Fill in actual data
    foreach ($weekly_data as $data) {
        $weekly_appointments[$data['week']] = (int)$data['count'];
    }

    // Get monthly appointments for the last 12 months
    $query = "SELECT 
                DATE_FORMAT(appointment_date, '%Y-%m') as month, 
                COUNT(*) as count 
              FROM appointments 
              WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
              ORDER BY month";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 12 months with zero counts
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthly_appointments[$month] = 0;
    }
    
    // Fill in actual data
    foreach ($monthly_data as $data) {
        $monthly_appointments[$data['month']] = (int)$data['count'];
    }

    // Get daily patient registrations for the last 7 days
    $query = "SELECT 
                DATE(created_at) as date, 
                COUNT(*) as count 
              FROM users u
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient')
              AND created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              GROUP BY DATE(created_at)
              ORDER BY date";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $daily_patient_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 7 days with zero counts
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_patients[$date] = 0;
    }
    
    // Fill in actual data
    foreach ($daily_patient_data as $data) {
        $daily_patients[$data['date']] = (int)$data['count'];
    }

    // Get weekly patient registrations for the last 8 weeks
    $query = "SELECT 
                YEARWEEK(created_at, 1) as week, 
                COUNT(*) as count 
              FROM users u
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient')
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
              GROUP BY YEARWEEK(created_at, 1)
              ORDER BY week";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $weekly_patient_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 8 weeks with zero counts
    for ($i = 7; $i >= 0; $i--) {
        $week = date('oW', strtotime("-$i weeks"));
        $weekly_patients[$week] = 0;
    }
    
    // Fill in actual data
    foreach ($weekly_patient_data as $data) {
        $weekly_patients[$data['week']] = (int)$data['count'];
    }

    // Get monthly patient registrations for the last 12 months
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month, 
                COUNT(*) as count 
              FROM users u
              WHERE u.role_id = (SELECT role_id FROM user_roles WHERE role_name = 'patient')
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $monthly_patient_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 12 months with zero counts
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthly_patients[$month] = 0;
    }
    
    // Fill in actual data
    foreach ($monthly_patient_data as $data) {
        $monthly_patients[$data['month']] = (int)$data['count'];
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card i {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        .stat-card h3 {
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }
        .stat-card p {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
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
                <i class="fas fa-calendar-day"></i>
                <h3>Appointments Today</h3>
                <p><?php echo number_format($stats['appointments_today']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-week"></i>
                <h3>Appointments This Week</h3>
                <p><?php echo number_format($stats['appointments_this_week']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Appointments This Month</h3>
                <p><?php echo number_format($stats['appointments_this_month']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-user-plus"></i>
                <h3>Patients Today</h3>
                <p><?php echo number_format($stats['patients_today']); ?></p>
            </div>

            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Patients This Week</h3>
                <p><?php echo number_format($stats['patients_this_week']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-user-friends"></i>
                <h3>Patients This Month</h3>
                <p><?php echo number_format($stats['patients_this_month']); ?></p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Daily Appointments (Last 7 Days)</h3>
                <canvas id="dailyAppointmentsChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Daily Patient Registrations (Last 7 Days)</h3>
                <canvas id="dailyPatientsChart"></canvas>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Weekly Appointments (Last 8 Weeks)</h3>
                <canvas id="weeklyAppointmentsChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Weekly Patient Registrations (Last 8 Weeks)</h3>
                <canvas id="weeklyPatientsChart"></canvas>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3 class="chart-title">Monthly Appointments (Last 12 Months)</h3>
                <canvas id="monthlyAppointmentsChart"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">Monthly Patient Registrations (Last 12 Months)</h3>
                <canvas id="monthlyPatientsChart"></canvas>
            </div>
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
            // Daily Appointments Chart
            const dailyAppointmentsCtx = document.getElementById('dailyAppointmentsChart').getContext('2d');
            const dailyAppointmentsChart = new Chart(dailyAppointmentsCtx, {
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
                        data: [<?php echo implode(',', array_values($daily_appointments)); ?>],
                        fill: false,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        tension: 0.1,
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
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
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Daily Patients Chart
            const dailyPatientsCtx = document.getElementById('dailyPatientsChart').getContext('2d');
            const dailyPatientsChart = new Chart(dailyPatientsCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                            $dates = array_map(function($date) { 
                                return "'" . date('M d', strtotime($date)) . "'"; 
                            }, array_keys($daily_patients));
                            echo implode(',', $dates);
                        ?>
                    ],
                    datasets: [{
                        label: 'Daily Patient Registrations',
                        data: [<?php echo implode(',', array_values($daily_patients)); ?>],
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
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Weekly Appointments Chart
            const weeklyAppointmentsCtx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
            const weeklyAppointmentsChart = new Chart(weeklyAppointmentsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                            $weeks = array_map(function($week) { 
                                $year = substr($week, 0, 4);
                                $weekNum = substr($week, 4, 2);
                                return "'Week $weekNum ($year)'"; 
                            }, array_keys($weekly_appointments));
                            echo implode(',', $weeks);
                        ?>
                    ],
                    datasets: [{
                        label: 'Weekly Appointments',
                        data: [<?php echo implode(',', array_values($weekly_appointments)); ?>],
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Weekly Patients Chart
            const weeklyPatientsCtx = document.getElementById('weeklyPatientsChart').getContext('2d');
            const weeklyPatientsChart = new Chart(weeklyPatientsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                            $weeks = array_map(function($week) { 
                                $year = substr($week, 0, 4);
                                $weekNum = substr($week, 4, 2);
                                return "'Week $weekNum ($year)'"; 
                            }, array_keys($weekly_patients));
                            echo implode(',', $weeks);
                        ?>
                    ],
                    datasets: [{
                        label: 'Weekly Patient Registrations',
                        data: [<?php echo implode(',', array_values($weekly_patients)); ?>],
                        backgroundColor: 'rgba(153, 102, 255, 0.6)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Monthly Appointments Chart
            const monthlyAppointmentsCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
            const monthlyAppointmentsChart = new Chart(monthlyAppointmentsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                            $months = array_map(function($month) { 
                                return "'" . date('M Y', strtotime($month . '-01')) . "'"; 
                            }, array_keys($monthly_appointments));
                            echo implode(',', $months);
                        ?>
                    ],
                    datasets: [{
                        label: 'Monthly Appointments',
                        data: [<?php echo implode(',', array_values($monthly_appointments)); ?>],
                        backgroundColor: 'rgba(255, 206, 86, 0.6)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // Monthly Patients Chart
            const monthlyPatientsCtx = document.getElementById('monthlyPatientsChart').getContext('2d');
            const monthlyPatientsChart = new Chart(monthlyPatientsCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                            $months = array_map(function($month) { 
                                return "'" . date('M Y', strtotime($month . '-01')) . "'"; 
                            }, array_keys($monthly_patients));
                            echo implode(',', $months);
                        ?>
                    ],
                    datasets: [{
                        label: 'Monthly Patient Registrations',
                        data: [<?php echo implode(',', array_values($monthly_patients)); ?>],
                        backgroundColor: 'rgba(99, 255, 132, 0.6)',
                        borderColor: 'rgba(99, 255, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 