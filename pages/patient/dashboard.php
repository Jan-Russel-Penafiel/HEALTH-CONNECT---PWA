<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is patient
if ($_SESSION['role'] !== 'patient') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize variables
$upcoming_appointments = [];
$recent_records = [];
$due_vaccines = [];
$error_message = null;

// Data for charts
$monthly_appointments = [];
$monthly_records = [];

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Get patient details
    $query = "SELECT p.*, u.* 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE p.user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception("Patient record not found");
    }

    // Get counts for dashboard stats
    $counts = [];
    
    // Count total appointments
    $query = "SELECT COUNT(*) as total FROM appointments WHERE patient_id = :patient_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $counts['appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count medical records (count whole records)
    $query = "SELECT COUNT(DISTINCT record_id) as total FROM medical_records WHERE patient_id = :patient_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $counts['medical_records'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count completed immunizations
    $query = "SELECT COUNT(DISTINCT immunization_record_id) as total FROM immunization_records WHERE patient_id = :patient_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $counts['immunizations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get monthly appointments for the last 6 months
    $query = "SELECT 
                DATE_FORMAT(appointment_date, '%Y-%m') as month, 
                COUNT(*) as count 
              FROM appointments 
              WHERE patient_id = :patient_id
              AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
              ORDER BY month";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 6 months with zero counts
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthly_appointments[$month] = 0;
    }
    
    // Fill in actual data
    foreach ($monthly_data as $data) {
        $monthly_appointments[$data['month']] = (int)$data['count'];
    }

    // Get monthly medical records for the last 6 months
    $query = "SELECT 
                DATE_FORMAT(visit_date, '%Y-%m') as month, 
                COUNT(*) as count 
              FROM medical_records 
              WHERE patient_id = :patient_id
              AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
              ORDER BY month";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $monthly_record_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize last 6 months with zero counts
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthly_records[$month] = 0;
    }
    
    // Fill in actual data
    foreach ($monthly_record_data as $data) {
        $monthly_records[$data['month']] = (int)$data['count'];
    }

    // Get upcoming appointments
    $query = "SELECT a.*, 
              CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
              hw.position as health_worker_position,
              s.status_name
              FROM appointments a
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users h_u ON hw.user_id = h_u.user_id
              JOIN appointment_status s ON a.status_id = s.status_id
              WHERE a.patient_id = :patient_id 
              AND a.appointment_date >= CURDATE()
              AND a.status_id IN (1, 2)
              ORDER BY a.appointment_date ASC, a.appointment_time ASC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent medical records
    $query = "SELECT m.*, 
              CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
              hw.position as health_worker_position
              FROM medical_records m
              JOIN health_workers hw ON m.health_worker_id = hw.health_worker_id
              JOIN users h_u ON hw.user_id = h_u.user_id
              WHERE m.patient_id = :patient_id
              ORDER BY m.visit_date DESC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $recent_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get due vaccines
    $query = "SELECT it.*, 
              (SELECT COUNT(*) FROM immunization_records ir 
               WHERE ir.immunization_type_id = it.immunization_type_id 
               AND ir.patient_id = :patient_id) as doses_received,
              it.dose_count as doses_required,
              (SELECT MAX(date_administered) FROM immunization_records ir 
               WHERE ir.immunization_type_id = it.immunization_type_id 
               AND ir.patient_id = :patient_id) as last_dose_date
              FROM immunization_types it 
              WHERE it.dose_count > (
                SELECT COUNT(*) FROM immunization_records ir 
                WHERE ir.immunization_type_id = it.immunization_type_id 
                AND ir.patient_id = :patient_id
              )
              ORDER BY it.recommended_age ASC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([':patient_id' => $patient['patient_id']]);
    $due_vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in patient dashboard: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Error in patient dashboard: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - HealthConnect</title>
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
            display: flex;
            flex-direction: column;
        }

        .activity-list-container {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4CAF50 #f0f0f0;
        }

        .activity-list-container::-webkit-scrollbar {
            width: 6px;
        }

        .activity-list-container::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 3px;
        }

        .activity-list-container::-webkit-scrollbar-thumb {
            background-color: #4CAF50;
            border-radius: 3px;
        }

        .activity-list {
            padding-right: 10px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .view-all-btn {
            padding: 6px 12px;
            background: #f8f9fa;
            color: #4CAF50;
            border-radius: 5px;
            font-size: 0.9em;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .view-all-btn:hover {
            background: #C8E6C9;
            color: #388E3C;
            text-decoration: none;
        }

        .view-all-btn i {
            font-size: 0.8em;
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
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            text-decoration: none;
            cursor: pointer;
        }

        .stat-card i {
            font-size: 2em;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1em;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card p {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .activity-list {
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: #C8E6C9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #388E3C;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.9em;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-badge.scheduled {
            background: #C8E6C9;
            color: #388E3C;
        }

        .status-badge.confirmed {
            background: #4CAF50;
            color: white;
        }

        .status-badge.completed {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .status-badge.cancelled {
            background: #ffebee;
            color: #c62828;
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
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <h1>Patient Dashboard</h1>
            <div class="date-display">
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="stats-grid">
            <a href="appointments.php" class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Appointments</h3>
                <p><?php echo number_format($counts['appointments']); ?></p>
            </a>
            
            <a href="medical_history.php" class="stat-card">
                <i class="fas fa-notes-medical"></i>
                <h3>Medical Records</h3>
                <p><?php echo number_format($counts['medical_records']); ?></p>
            </a>
            
            <a href="immunization.php" class="stat-card">
                <i class="fas fa-syringe"></i>
                <h3>Immunizations</h3>
                <p><?php echo number_format($counts['immunizations']); ?></p>
            </a>
        </div>

            <!-- Upcoming Appointments -->
        <div class="recent-activity">
            <h2>Upcoming Appointments</h2>
            <div class="activity-list">
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    Appointment with <?php echo htmlspecialchars($appointment['health_worker_name']); ?>
                                </div>
                                <div class="activity-time">
                                    <?php 
                                        $date = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                        echo $date->format('F j, Y \a\t g:i A');
                                    ?>
                                    <span class="status-badge <?php echo strtolower($appointment['status_name']); ?>">
                                        <?php echo ucfirst($appointment['status_name']); ?>
                                </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-activity">
                        <p>No upcoming appointments</p>
                        <a href="schedule_appointment.php" class="btn">Schedule Now</a>
                    </div>
                <?php endif; ?>
            </div>
            </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Monthly Appointments Chart -->
            <div class="chart-container">
                <h3 class="chart-title">Appointments (Last 6 Months)</h3>
                <canvas id="monthlyAppointmentsChart"></canvas>
            </div>
            
            <!-- Monthly Medical Records Chart -->
            <div class="chart-container">
                <h3 class="chart-title">Medical Records (Last 6 Months)</h3>
                <canvas id="monthlyRecordsChart"></canvas>
            </div>
        </div>

        <div class="charts-grid">
            <!-- Recent Medical Records -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Recent Medical Records</h3>
                    <a href="medical_history.php" class="view-all-btn">
                        View All <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if (empty($recent_records)): ?>
                    <p class="text-muted">No recent medical records</p>
                <?php else: ?>
                    <div class="activity-list-container">
                        <div class="activity-list">
                        <?php foreach ($recent_records as $record): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-notes-medical"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($record['diagnosis']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('F j, Y', strtotime($record['visit_date'])); ?>
                                        <span>By <?php echo htmlspecialchars($record['health_worker_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Due Vaccines -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Due Vaccines</h3>
                    <a href="immunization.php" class="view-all-btn">
                        View All <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if (empty($due_vaccines)): ?>
                    <p class="text-muted">All vaccines are up to date!</p>
                <?php else: ?>
                    <div class="activity-list-container">
                        <div class="activity-list">
                        <?php foreach ($due_vaccines as $vaccine): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($vaccine['name']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php
                                        $doses_received = (int)($vaccine['doses_received'] ?? 0);
                                        $doses_required = (int)($vaccine['doses_required'] ?? 0);
                                        ?>
                                        Progress: <?php echo $doses_received; ?>/<?php echo $doses_required; ?> doses
                                        <?php if ($vaccine['last_dose_date']): ?>
                                            <span>Last dose: <?php echo date('M j, Y', strtotime($vaccine['last_dose_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
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
            const monthlyAppointmentsCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
            const monthlyAppointmentsChart = new Chart(monthlyAppointmentsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($month) {
                        return date('M Y', strtotime($month . '-01'));
                    }, array_keys($monthly_appointments))); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode(array_values($monthly_appointments)); ?>,
                        backgroundColor: 'rgba(76, 175, 80, 0.6)',
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Monthly Medical Records Chart
            const monthlyRecordsCtx = document.getElementById('monthlyRecordsChart').getContext('2d');
            const monthlyRecordsChart = new Chart(monthlyRecordsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($month) {
                        return date('M Y', strtotime($month . '-01'));
                    }, array_keys($monthly_records))); ?>,
                    datasets: [{
                        label: 'Medical Records',
                        data: <?php echo json_encode(array_values($monthly_records)); ?>,
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        borderColor: 'rgba(33, 150, 243, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
        });
    </script>
</body>
</html> 