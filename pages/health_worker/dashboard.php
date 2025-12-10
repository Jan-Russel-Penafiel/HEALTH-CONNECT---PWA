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
$total_patients = 0;
$total_appointments = 0;
$stats = [
    'appointments_today' => 0,
    'appointments_this_week' => 0,
    'appointments_this_month' => 0,
    'patients_today' => 0,
    'patients_this_week' => 0,
    'patients_this_month' => 0
];

// Data for charts
$monthly_appointments = [];
$weekly_appointments = [];
$daily_appointments = [];
$monthly_patients = [];
$weekly_patients = [];
$daily_patients = [];

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
    $stats['appointments_today'] = $today_count;

    // Get appointments this week
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE health_worker_id = ?
              AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $stats['appointments_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get appointments this month
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE health_worker_id = ?
              AND YEAR(appointment_date) = YEAR(CURDATE()) 
              AND MONTH(appointment_date) = MONTH(CURDATE())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $stats['appointments_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients seen today
    $query = "SELECT COUNT(DISTINCT p.patient_id) as count 
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id 
              WHERE a.health_worker_id = ?
              AND DATE(a.appointment_date) = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id, $today]);
    $stats['patients_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients seen this week
    $query = "SELECT COUNT(DISTINCT p.patient_id) as count 
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id 
              WHERE a.health_worker_id = ?
              AND YEARWEEK(a.appointment_date, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $stats['patients_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get patients seen this month
    $query = "SELECT COUNT(DISTINCT p.patient_id) as count 
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.patient_id 
              WHERE a.health_worker_id = ?
              AND YEAR(a.appointment_date) = YEAR(CURDATE()) 
              AND MONTH(a.appointment_date) = MONTH(CURDATE())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
    $stats['patients_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get daily appointments for the last 7 days
    $query = "SELECT 
                DATE(appointment_date) as date, 
                COUNT(*) as count 
              FROM appointments 
              WHERE health_worker_id = ?
              AND appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              GROUP BY DATE(appointment_date)
              ORDER BY date";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
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
              WHERE health_worker_id = ?
              AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
              GROUP BY YEARWEEK(appointment_date, 1)
              ORDER BY week";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
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
              WHERE health_worker_id = ?
              AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
              ORDER BY month";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$health_worker_id]);
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
                DATE(u.created_at) as date, 
                COUNT(*) as count 
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE u.created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
              GROUP BY DATE(u.created_at)
              ORDER BY date";
    $stmt = $pdo->prepare($query);
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
                YEARWEEK(u.created_at, 1) as week, 
                COUNT(*) as count 
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE u.created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
              GROUP BY YEARWEEK(u.created_at, 1)
              ORDER BY week";
    $stmt = $pdo->prepare($query);
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
                DATE_FORMAT(u.created_at, '%Y-%m') as month, 
                COUNT(*) as count 
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE u.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              GROUP BY DATE_FORMAT(u.created_at, '%Y-%m')
              ORDER BY month";
    $stmt = $pdo->prepare($query);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
            color: #4CAF50;
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
        .recent-activity {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .recent-activity h2 {
            color: #4CAF50;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #C8E6C9;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #C8E6C9;
        }
        .dashboard-header h1 {
            color: #4CAF50;
            font-size: 2rem;
            margin: 0;
        }
        .date-display {
            color: #757575;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .chart-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .chart-title {
            color: #4CAF50;
            font-size: 1.2rem;
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #C8E6C9;
        }
        canvas {
            max-height: 300px;
        }
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <i class="fas fa-calendar-day"></i>
                <h3>Appointments Today</h3>
                <p class="number"><?php echo number_format($stats['appointments_today']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-week"></i>
                <h3>Appointments This Week</h3>
                <p class="number"><?php echo number_format($stats['appointments_this_week']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Appointments This Month</h3>
                <p class="number"><?php echo number_format($stats['appointments_this_month']); ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <h3>Patients Seen Today</h3>
                <p class="number"><?php echo number_format($stats['patients_today']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Patients This Week</h3>
                <p class="number"><?php echo number_format($stats['patients_this_week']); ?></p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-user-friends"></i>
                <h3>Patients This Month</h3>
                <p class="number"><?php echo number_format($stats['patients_this_month']); ?></p>
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

    // Initialize charts
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
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    tension: 0.1,
                    pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            stepSize: 1,
                            precision: 0 
                        }
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
                    backgroundColor: 'rgba(139, 195, 74, 0.6)',
                    borderColor: 'rgba(139, 195, 74, 1)',
                    tension: 0.1,
                    pointBackgroundColor: 'rgba(139, 195, 74, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            stepSize: 1,
                            precision: 0 
                        }
                    }
                }
            }
        });

        // Weekly Appointments Chart
        const weeklyCtx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
        const weeklyLabels = <?php echo json_encode(array_keys($weekly_appointments)); ?>;
        const weeklyData = <?php echo json_encode(array_values($weekly_appointments)); ?>;
        
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: weeklyLabels.map(week => {
                    const weekStr = String(week);
                    const year = weekStr.substring(0, 4);
                    const weekNum = weekStr.substring(4);
                    return `Week ${weekNum} (${year})`;
                }),
                datasets: [{
                    label: 'Appointments',
                    data: weeklyData,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });

        // Weekly Patients Chart
        const weeklyPatientsCtx = document.getElementById('weeklyPatientsChart').getContext('2d');
        const weeklyPatientsLabels = <?php echo json_encode(array_keys($weekly_patients)); ?>;
        const weeklyPatientsData = <?php echo json_encode(array_values($weekly_patients)); ?>;
        
        new Chart(weeklyPatientsCtx, {
            type: 'bar',
            data: {
                labels: weeklyPatientsLabels.map(week => {
                    const weekStr = String(week);
                    const year = weekStr.substring(0, 4);
                    const weekNum = weekStr.substring(4);
                    return `Week ${weekNum} (${year})`;
                }),
                datasets: [{
                    label: 'Patient Registrations',
                    data: weeklyPatientsData,
                    backgroundColor: 'rgba(139, 195, 74, 0.6)',
                    borderColor: 'rgba(139, 195, 74, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });

        // Monthly Appointments Chart
        const monthlyCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
        const monthlyLabels = <?php echo json_encode(array_keys($monthly_appointments)); ?>;
        const monthlyData = <?php echo json_encode(array_values($monthly_appointments)); ?>;
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels.map(month => {
                    const date = new Date(month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Appointments',
                    data: monthlyData,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });

        // Monthly Patients Chart
        const monthlyPatientsCtx = document.getElementById('monthlyPatientsChart').getContext('2d');
        const monthlyPatientsLabels = <?php echo json_encode(array_keys($monthly_patients)); ?>;
        const monthlyPatientsData = <?php echo json_encode(array_values($monthly_patients)); ?>;
        
        new Chart(monthlyPatientsCtx, {
            type: 'bar',
            data: {
                labels: monthlyPatientsLabels.map(month => {
                    const date = new Date(month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Patient Registrations',
                    data: monthlyPatientsData,
                    backgroundColor: 'rgba(139, 195, 74, 0.6)',
                    borderColor: 'rgba(139, 195, 74, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
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