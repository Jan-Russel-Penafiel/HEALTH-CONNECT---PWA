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

// Handle report generation
$report_type = isset($_GET['type']) ? $_GET['type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';

$report_data = [];
$report_summary = [];

// Get list of patients for filter
$patients_list = [];
try {
    $query = "SELECT p.patient_id, CONCAT(u.last_name, ', ', u.first_name) as patient_name 
              FROM patients p 
              JOIN users u ON p.user_id = u.user_id 
              ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients list: " . $e->getMessage());
}

if ($report_type) {
    try {
        switch ($report_type) {
            case 'appointments_list':
                // Get appointments list
                $query = "SELECT 
                            a.appointment_id,
                            a.appointment_date,
                            a.appointment_time,
                            a.reason,
                            CONCAT(p_user.last_name, ', ', p_user.first_name) as patient_name,
                            p_user.mobile_number as patient_contact,
                            CONCAT(hw_user.last_name, ', ', hw_user.first_name) as health_worker_name,
                            ast.status_name as status
                         FROM appointments a 
                         JOIN patients p ON a.patient_id = p.patient_id
                         JOIN users p_user ON p.user_id = p_user.user_id
                         JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
                         JOIN users hw_user ON hw.user_id = hw_user.user_id
                         JOIN appointment_status ast ON a.status_id = ast.status_id
                         WHERE a.appointment_date BETWEEN :start_date AND :end_date
                         ORDER BY a.appointment_date DESC, a.appointment_time ASC";
                
                $stmt = $conn->prepare($query);
                $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get summary
                $summary_query = "SELECT 
                                COUNT(*) as total_appointments,
                                SUM(CASE WHEN ast.status_name = 'Completed' THEN 1 ELSE 0 END) as completed,
                                SUM(CASE WHEN ast.status_name = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                                SUM(CASE WHEN ast.status_name IN ('Scheduled', 'Confirmed') THEN 1 ELSE 0 END) as pending,
                                COUNT(DISTINCT a.patient_id) as unique_patients
                                FROM appointments a
                                JOIN appointment_status ast ON a.status_id = ast.status_id
                                WHERE a.appointment_date BETWEEN :start_date AND :end_date";
                $stmt = $conn->prepare($summary_query);
                $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
                $report_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                break;

            case 'patients_list':
                // Get patients list with their basic info and statistics
                $query = "SELECT 
                            p.patient_id,
                            u.first_name,
                            u.middle_name,
                            u.last_name,
                            u.email,
                            u.mobile_number,
                            u.gender,
                            u.date_of_birth,
                            p.blood_type,
                            p.height,
                            p.weight,
                            p.emergency_contact_name,
                            p.emergency_contact_number,
                            (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) as appointment_count,
                            (SELECT COUNT(*) FROM immunization_records ir WHERE ir.patient_id = p.patient_id) as immunization_count,
                            (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.patient_id) as medical_record_count
                         FROM patients p
                         JOIN users u ON p.user_id = u.user_id
                         ORDER BY u.last_name, u.first_name";
                
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get summary
                $summary_query = "SELECT 
                                COUNT(*) as total_patients,
                                COUNT(CASE WHEN p.blood_type IS NOT NULL THEN 1 END) as with_blood_type,
                                AVG(TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE())) as average_age
                                FROM patients p
                                JOIN users u ON p.user_id = u.user_id";
                $stmt = $conn->prepare($summary_query);
                $stmt->execute();
                $report_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                break;

            case 'medical_records':
                if (empty($patient_id)) {
                    throw new Exception("Please select a patient to view medical records.");
                }

                // Get patient's medical records
                $query = "SELECT 
                            mr.*,
                            CONCAT(p_user.last_name, ', ', p_user.first_name) as patient_name,
                            CONCAT(hw_user.last_name, ', ', hw_user.first_name) as health_worker_name
                         FROM medical_records mr
                         JOIN patients p ON mr.patient_id = p.patient_id
                         JOIN users p_user ON p.user_id = p_user.user_id
                         JOIN health_workers hw ON mr.health_worker_id = hw.health_worker_id
                         JOIN users hw_user ON hw.user_id = hw_user.user_id
                         WHERE mr.patient_id = :patient_id
                         ORDER BY mr.visit_date DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->execute([':patient_id' => $patient_id]);
                $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get patient info and summary
                $summary_query = "SELECT 
                                CONCAT(u.last_name, ', ', u.first_name) as patient_name,
                                u.gender,
                                u.date_of_birth,
                                p.blood_type,
                                p.height,
                                p.weight,
                                COUNT(mr.record_id) as total_visits,
                                MAX(mr.visit_date) as last_visit
                                FROM patients p
                                JOIN users u ON p.user_id = u.user_id
                                LEFT JOIN medical_records mr ON p.patient_id = mr.patient_id
                                WHERE p.patient_id = :patient_id
                                GROUP BY p.patient_id";
                $stmt = $conn->prepare($summary_query);
                $stmt->execute([':patient_id' => $patient_id]);
                $report_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                break;

            case 'appointment_slip':
                // Remove the validation check and just proceed with empty data if no patient selected
                $report_data = [];
                $report_summary = [];
                
                if (!empty($patient_id)) {
                    // Get patient's upcoming appointments
                    $query = "SELECT 
                                a.appointment_id,
                                a.appointment_date,
                                a.appointment_time,
                                a.reason,
                                CONCAT(p_user.last_name, ', ', p_user.first_name) as patient_name,
                                p_user.mobile_number as patient_contact,
                                CONCAT(hw_user.last_name, ', ', hw_user.first_name) as health_worker_name,
                                hw_user.mobile_number as health_worker_contact,
                                ast.status_name as status
                             FROM appointments a 
                             JOIN patients p ON a.patient_id = p.patient_id
                             JOIN users p_user ON p.user_id = p_user.user_id
                             JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
                             JOIN users hw_user ON hw.user_id = hw_user.user_id
                             JOIN appointment_status ast ON a.status_id = ast.status_id
                             WHERE a.patient_id = :patient_id
                             AND a.appointment_date >= CURDATE()
                             ORDER BY a.appointment_date ASC, a.appointment_time ASC";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute([':patient_id' => $patient_id]);
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Get patient info but don't display as cards
                    $summary_query = "SELECT 
                                    CONCAT(u.last_name, ', ', u.first_name) as patient_name,
                                    u.gender,
                                    u.date_of_birth,
                                    u.mobile_number,
                                    p.blood_type,
                                    p.emergency_contact_name,
                                    p.emergency_contact_number
                                    FROM patients p
                                    JOIN users u ON p.user_id = u.user_id
                                    WHERE p.patient_id = :patient_id";
                    $stmt = $conn->prepare($summary_query);
                    $stmt->execute([':patient_id' => $patient_id]);
                    $report_summary = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Error generating report: " . $e->getMessage());
        $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <!-- Add Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #666;
            font-size: 1em;
            margin: 0 0 10px 0;
            text-transform: uppercase;
        }

        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #4a90e2;
            margin: 0;
        }

        .stat-card .icon {
            font-size: 2em;
            color: #4a90e2;
            margin-bottom: 10px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .report-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .report-card .title {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .report-card .info-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            color: #555;
        }

        .report-card .info-item i {
            width: 20px;
            margin-right: 10px;
            color: #4a90e2;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-badge.completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-badge.cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .btn-primary {
            background: #4a90e2;
            color: white;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Additional styles for new features */
        .report-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .medical-record-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .medical-record-card .diagnosis {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .appointment-slip {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .appointment-slip .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4a90e2;
        }

        .appointment-slip .header h2 {
            margin-bottom: 5px;
            color: #333;
        }

        .appointment-slip .header p {
            color: #666;
            margin: 0;
        }

        .appointment-slip .patient-info,
        .appointment-slip .appointment-details {
            margin-bottom: 30px;
        }

        .appointment-slip .appointment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
        }

        .appointment-slip h4 {
            color: #333;
            margin-bottom: 20px;
        }

        .appointment-slip .table {
            margin-bottom: 0;
        }

        .appointment-slip .table td {
            padding: 8px 0;
            border: none;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .appointment-slip, .appointment-slip * {
                visibility: visible;
            }
            .appointment-slip {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                padding: 15px;
            }
            .no-print {
                display: none;
            }
        }

        /* Search bar styles */
        .search-container {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-container .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .search-container .search-input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }

        .search-container .search-icon {
            color: #666;
            margin-right: 10px;
        }

        /* Highlight search matches */
        .highlight {
            background-color: #fff3cd;
            padding: 2px;
            border-radius: 2px;
        }

        /* No results message */
        .no-results {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            margin: 20px 0;
            color: #666;
        }

        /* Select2 Custom Styles */
        .select2-container--bootstrap-5 {
            width: 100% !important;
        }

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border: 1px solid #ced4da;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 0.375rem 0.75rem;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered {
            color: #212529;
            line-height: 1.5;
        }

        .select2-container--bootstrap-5 .select2-search__field {
            padding: 0.375rem 0.75rem;
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #4a90e2;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Reports</h1>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="report-filter">
            <form action="" method="GET" class="filter-grid">
                <div class="form-group">
                    <label for="type">Report Type</label>
                    <select name="type" id="type" class="form-control" required onchange="toggleFilters()">
                        <option value="">Select Report Type</option>
                        <option value="appointments_list" <?php echo $report_type === 'appointments_list' ? 'selected' : ''; ?>>List of Appointments</option>
                        <option value="patients_list" <?php echo $report_type === 'patients_list' ? 'selected' : ''; ?>>List of Patients</option>
                        <option value="medical_records" <?php echo $report_type === 'medical_records' ? 'selected' : ''; ?>>Patient Medical Records</option>
                        <option value="appointment_slip" <?php echo $report_type === 'appointment_slip' ? 'selected' : ''; ?>>Appointment Slip</option>
                    </select>
                </div>

                <div class="form-group date-filter" id="dateFilter">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>

                <div class="form-group date-filter" id="dateFilterEnd">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>

                <div class="form-group patient-filter" id="patientFilter" style="display: none;">
                    <label for="patient_id">Search Patient</label>
                    <select name="patient_id" id="patient_id" class="form-control select2-search" style="width: 100%;">
                        <option value="">Search by name...</option>
                        <?php foreach ($patients_list as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>" <?php echo $patient_id == $patient['patient_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['patient_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <?php if (!empty($report_data)): ?>
                        <button type="button" class="btn btn-secondary" onclick="exportToCSV()">Export to CSV</button>
                        <?php if (in_array($report_type, ['medical_records', 'appointment_slip'])): ?>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($report_type && empty($report_data)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Data Available</h3>
                <p>No records found for the selected criteria.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($report_data)): ?>
            <?php if ($report_type !== 'appointment_slip'): ?>
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search in results..." onkeyup="filterResults()">
            </div>
            <?php endif; ?>

            <?php if (!empty($report_summary) && $report_type !== 'appointment_slip'): ?>
                <div class="stats-grid">
                    <?php foreach ($report_summary as $key => $value): ?>
                        <div class="stat-card">
                            <?php
                            $icon = 'chart-bar';
                            switch ($key) {
                                case 'total_appointments':
                                    $icon = 'calendar-check';
                                    break;
                                case 'completed':
                                    $icon = 'check-circle';
                                    break;
                                case 'cancelled':
                                    $icon = 'times-circle';
                                    break;
                                case 'pending':
                                    $icon = 'clock';
                                    break;
                                case 'unique_patients':
                                    $icon = 'users';
                                    break;
                                case 'total_patients':
                                    $icon = 'user-injured';
                                    break;
                                case 'with_blood_type':
                                    $icon = 'tint';
                                    break;
                                case 'average_age':
                                    $icon = 'birthday-cake';
                                    $value = round($value, 1);
                                    break;
                                case 'total_visits':
                                    $icon = 'clinic-medical';
                                    break;
                                case 'last_visit':
                                    $icon = 'calendar-alt';
                                    $value = date('M d, Y', strtotime($value));
                                    break;
                            }
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> icon"></i>
                            <h3><?php echo ucwords(str_replace('_', ' ', $key)); ?></h3>
                            <p class="number"><?php echo $value; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($report_type === 'appointments_list' || $report_type === 'patients_list'): ?>
                <div class="reports-grid">
                    <?php foreach ($report_data as $row): ?>
                        <div class="report-card">
                            <?php if ($report_type === 'appointments_list'): ?>
                                <div class="header">
                                    <h3 class="title"><?php echo htmlspecialchars($row['patient_name']); ?></h3>
                                    <div class="info-item">
                                        <i class="fas fa-user-md"></i>
                                        <span><?php echo htmlspecialchars($row['health_worker_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($row['patient_contact']); ?></span>
                                    </div>
                                    <?php if (!empty($row['reason'])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo htmlspecialchars($row['reason']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="patient-card">
                                    <div class="patient-header">
                                        <h3 class="patient-name">
                                            <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?>
                                            <?php if ($row['middle_name']): ?>
                                                <?php echo ' ' . htmlspecialchars($row['middle_name'][0]) . '.'; ?>
                                            <?php endif; ?>
                                        </h3>
                                    </div>
                                    
                                    <div class="patient-details">
                                        <div class="detail-item">
                                            <i class="fas fa-venus-mars"></i>
                                            <span><?php echo htmlspecialchars($row['gender']); ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-birthday-cake"></i>
                                            <span>
                                                <?php 
                                                    echo date('M d, Y', strtotime($row['date_of_birth']));
                                                    $age = date_diff(date_create($row['date_of_birth']), date_create('today'))->y;
                                                    echo " ($age years old)";
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($row['email']); ?></span>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($row['mobile_number']); ?></span>
                                        </div>
                                        
                                        <?php if ($row['blood_type']): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-tint"></i>
                                            <span>Blood Type: <?php echo htmlspecialchars($row['blood_type']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-weight"></i>
                                            <span>
                                                <?php 
                                                    if ($row['height']) echo htmlspecialchars($row['height']) . ' cm';
                                                    if ($row['height'] && $row['weight']) echo ' / ';
                                                    if ($row['weight']) echo htmlspecialchars($row['weight']) . ' kg';
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="patient-stats">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $row['appointment_count']; ?></div>
                                            <div class="stat-label">
                                                <i class="fas fa-calendar-check"></i> Appointments
                                            </div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $row['immunization_count']; ?></div>
                                            <div class="stat-label">
                                                <i class="fas fa-syringe"></i> Immunizations
                                            </div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $row['medical_record_count']; ?></div>
                                            <div class="stat-label">
                                                <i class="fas fa-notes-medical"></i> Records
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <style>
                    /* Patient card specific styles */
                    .patient-card {
                        display: flex;
                        flex-direction: column;
                        height: 100%;
                    }
                    
                    .patient-header {
                        padding-bottom: 10px;
                        margin-bottom: 15px;
                        border-bottom: 2px solid #4a90e2;
                    }
                    
                    .patient-name {
                        font-size: 1.2em;
                        font-weight: bold;
                        color: #2c3e50;
                        margin: 0;
                    }
                    
                    .patient-details {
                        flex-grow: 1;
                        margin-bottom: 15px;
                    }
                    
                    .detail-item {
                        display: flex;
                        align-items: center;
                        margin-bottom: 8px;
                        color: #555;
                    }
                    
                    .detail-item i {
                        width: 20px;
                        margin-right: 10px;
                        color: #4a90e2;
                        text-align: center;
                    }
                    
                    .patient-stats {
                        display: flex;
                        justify-content: space-between;
                        background-color: #f8f9fa;
                        padding: 10px;
                        border-radius: 5px;
                        margin-bottom: 0;
                        text-align: center;
                    }
                    
                    .stat-item {
                        flex: 1;
                    }
                    
                    .stat-number {
                        font-size: 1.5em;
                        font-weight: bold;
                        color: #4a90e2;
                    }
                    
                    .stat-label {
                        font-size: 0.8em;
                        color: #666;
                    }
                </style>
            <?php elseif ($report_type === 'medical_records'): ?>
                <div class="medical-records-container">
                    <?php foreach ($report_data as $record): ?>
                        <div class="medical-record-card">
                            <div class="record-header">
                                <div class="record-title">
                                    <h3>Visit Date: <?php echo date('M d, Y', strtotime($record['visit_date'])); ?></h3>
                                    <span class="record-subtitle">
                                        <i class="fas fa-user-md"></i>
                                        Attended by: <?php echo htmlspecialchars($record['health_worker_name']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($record['follow_up_date'])): ?>
                                <div class="follow-up-badge">
                                    <i class="fas fa-calendar-plus"></i>
                                    Follow-up: <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="record-content">
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-comment-medical"></i>
                                        <strong>Chief Complaint</strong>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['chief_complaint'])); ?>
                                    </div>
                                </div>
                                
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-stethoscope"></i>
                                        <strong>Diagnosis</strong>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?>
                                    </div>
                                </div>
                                
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-pills"></i>
                                        <strong>Treatment</strong>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['treatment'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($record['prescription'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-prescription"></i>
                                        <strong>Prescription</strong>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['prescription'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['notes'])): ?>
                                <div class="record-section">
                                    <div class="section-title">
                                        <i class="fas fa-sticky-note"></i>
                                        <strong>Notes</strong>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <style>
                    .medical-records-container {
                        max-width: 900px;
                        margin: 0 auto;
                    }
                    
                    .medical-record-card {
                        background: white;
                        border-radius: 10px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        padding: 0;
                        margin-bottom: 30px;
                        overflow: hidden;
                        border-left: 4px solid #4a90e2;
                    }
                    
                    .record-header {
                        background: #f8f9fa;
                        padding: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-bottom: 1px solid #eee;
                    }
                    
                    .record-title h3 {
                        margin: 0 0 5px 0;
                        color: #2c3e50;
                        font-size: 1.2rem;
                    }
                    
                    .record-subtitle {
                        color: #666;
                        font-size: 0.9rem;
                        display: flex;
                        align-items: center;
                    }
                    
                    .record-subtitle i {
                        margin-right: 5px;
                        color: #4a90e2;
                    }
                    
                    .follow-up-badge {
                        background: #e3f2fd;
                        color: #1565c0;
                        padding: 8px 12px;
                        border-radius: 20px;
                        font-size: 0.85rem;
                        display: flex;
                        align-items: center;
                    }
                    
                    .follow-up-badge i {
                        margin-right: 5px;
                    }
                    
                    .record-content {
                        padding: 20px;
                    }
                    
                    .record-section {
                        margin-bottom: 20px;
                        padding-bottom: 20px;
                        border-bottom: 1px solid #eee;
                    }
                    
                    .record-section:last-child {
                        margin-bottom: 0;
                        padding-bottom: 0;
                        border-bottom: none;
                    }
                    
                    .section-title {
                        display: flex;
                        align-items: center;
                        margin-bottom: 10px;
                        color: #2c3e50;
                    }
                    
                    .section-title i {
                        margin-right: 8px;
                        color: #4a90e2;
                        width: 20px;
                        text-align: center;
                    }
                    
                    .section-content {
                        padding-left: 28px;
                        color: #333;
                        line-height: 1.5;
                    }
                    
                    @media print {
                        .medical-record-card {
                            break-inside: avoid;
                            box-shadow: none;
                            border: 1px solid #ddd;
                        }
                    }
                </style>
            <?php elseif ($report_type === 'appointment_slip'): ?>
                <?php foreach ($report_data as $appointment): ?>
                    <div class="appointment-slip">
                        <div class="header">
                            <h1>Brgy. Poblacion Health Center</h1>
                            <p>Appointment Confirmation Slip</p>
                            <p>Contact: (123) 456-7890</p>
                        </div>

                        <div class="appointment-details">
                            <div class="detail-row">
                                <span class="label">Appointment ID:</span>
                                <span class="value"><?php echo $appointment['appointment_id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Patient Name:</span>
                                <span class="value"><?php echo htmlspecialchars($report_summary['patient_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Contact Number:</span>
                                <span class="value"><?php echo htmlspecialchars($report_summary['mobile_number']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date:</span>
                                <span class="value"><?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Time:</span>
                                <span class="value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Health Worker:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['health_worker_name']); ?></span>
                            </div>
                            <?php if (!empty($appointment['reason'])): ?>
                            <div class="detail-row">
                                <span class="label">Reason for Visit:</span>
                                <span class="value"><?php echo htmlspecialchars($appointment['reason']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="qr-section">
                            <div class="important-notes">
                                <h4>Important Notes:</h4>
                                <ul>
                                    <li>Please arrive 15 minutes before your scheduled appointment time</li>
                                    <li>Bring this slip and a valid ID</li>
                                    <li>If you need to cancel or reschedule, please do so at least 24 hours in advance</li>
                                    <li>Follow health protocols (wear mask if required)</li>
                                    <li>For any questions or concerns, contact the health center</li>
                                </ul>
                            </div>
                            <div class="qr-code">
                                <?php
                                // Generate QR code data
                                $qr_data = json_encode([
                                    'appointment_id' => $appointment['appointment_id'],
                                    'patient' => $report_summary['patient_name'],
                                    'date' => $appointment['appointment_date'],
                                    'time' => $appointment['appointment_time']
                                ]);
                                ?>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($qr_data); ?>" alt="QR Code">
                                <p>Scan for quick check-in</p>
                            </div>
                        </div>

                        <div class="footer">
                            <p>This is an automatically generated appointment slip. For verification, please contact the health center.</p>
                        </div>

                        <div class="text-end mt-4 no-print">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print Slip
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <style>
                    .appointment-slip {
                        font-family: Arial, sans-serif;
                        line-height: 1.4;
                        color: #333;
                        margin: 0 auto;
                        padding: 30px;
                        max-width: 800px;
                        background: white;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        border-radius: 10px;
                    }

                    .appointment-slip .header {
                        text-align: center;
                        margin-bottom: 20px;
                        border-bottom: 1px solid #333;
                        padding-bottom: 10px;
                    }

                    .appointment-slip .header h1 {
                        margin: 0;
                        color: #2c3e50;
                        font-size: 24px;
                    }

                    .appointment-slip .header p {
                        margin: 5px 0;
                        color: #666;
                    }

                    .appointment-details {
                        margin-bottom: 25px;
                    }

                    .detail-row {
                        margin-bottom: 10px;
                        display: flex;
                        align-items: flex-start;
                    }

                    .detail-row .label {
                        font-weight: bold;
                        color: #2c3e50;
                        width: 30%;
                        min-width: 150px;
                    }

                    .detail-row .value {
                        width: 70%;
                    }

                    .qr-section {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin: 25px 0;
                        padding: 20px;
                        background-color: #f8f9fa;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                    }

                    .qr-code {
                        text-align: center;
                        width: 30%;
                    }

                    .qr-code img {
                        width: 100px;
                        height: 100px;
                        margin-bottom: 10px;
                    }

                    .qr-code p {
                        margin: 5px 0;
                        font-size: 12px;
                        color: #666;
                    }

                    .important-notes {
                        width: 65%;
                    }

                    .important-notes h4 {
                        margin: 0 0 10px 0;
                        color: #2c3e50;
                    }

                    .important-notes ul {
                        margin: 0;
                        padding-left: 20px;
                    }

                    .important-notes li {
                        margin-bottom: 5px;
                        font-size: 14px;
                    }

                    .footer {
                        margin-top: 25px;
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                        border-top: 1px solid #ddd;
                        padding-top: 15px;
                    }

                    @media print {
                        body * {
                            visibility: hidden;
                        }
                        .appointment-slip, .appointment-slip * {
                            visibility: visible;
                        }
                        .appointment-slip {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 100%;
                            box-shadow: none;
                            padding: 15px;
                        }
                        .no-print {
                            display: none !important;
                        }
                        .container {
                            padding: 0;
                            margin: 0;
                        }
                    }
                </style>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Add Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    function toggleFilters() {
        const reportType = document.getElementById('type').value;
        const dateFilter = document.getElementById('dateFilter');
        const dateFilterEnd = document.getElementById('dateFilterEnd');
        const patientFilter = document.getElementById('patientFilter');

        if (reportType === 'medical_records' || reportType === 'appointment_slip') {
            dateFilter.style.display = 'none';
            dateFilterEnd.style.display = 'none';
            patientFilter.style.display = 'block';
            document.getElementById('patient_id').required = true;
            document.getElementById('start_date').required = false;
            document.getElementById('end_date').required = false;
        } else {
            dateFilter.style.display = 'block';
            dateFilterEnd.style.display = 'block';
            patientFilter.style.display = 'none';
            document.getElementById('patient_id').required = false;
            document.getElementById('start_date').required = true;
            document.getElementById('end_date').required = true;
        }
    }

    // Initialize Select2
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Select2 with search
        $('.select2-search').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search by name...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 1,
            language: {
                inputTooShort: function() {
                    return "Please enter 1 or more characters";
                },
                noResults: function() {
                    return "No patients found";
                },
                searching: function() {
                    return "Searching...";
                }
            }
        });

        // Initialize filter visibility
        toggleFilters();
    });

    function exportToCSV() {
        const reportData = <?php echo json_encode($report_data); ?>;
        if (!reportData.length) return;
        
        // Get headers
        const headers = Object.keys(reportData[0]);
        
        // Create CSV content
        let csv = [headers.join(',')];
        
        // Add data rows
        reportData.forEach(row => {
            const values = headers.map(header => {
                const value = row[header] || '';
                return `"${value.toString().replace(/"/g, '""')}"`;
            });
            csv.push(values.join(','));
        });
        
        // Download CSV
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `report_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function filterResults() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const reportType = document.getElementById('type').value;
        const cards = document.querySelectorAll('.report-card, .medical-record-card, .appointment-slip');
        let hasVisibleCards = false;

        cards.forEach(card => {
            const textContent = card.textContent.toLowerCase();
            const shouldShow = textContent.includes(searchTerm);
            
            // Remove existing highlights
            card.innerHTML = card.innerHTML.replace(/<mark class="highlight">(.*?)<\/mark>/g, '$1');
            
            if (shouldShow) {
                hasVisibleCards = true;
                card.style.display = '';
                
                // Highlight matching text if there's a search term
                if (searchTerm) {
                    highlightText(card, searchTerm);
                }
            } else {
                card.style.display = 'none';
            }
        });

        // Show/hide no results message
        const noResultsDiv = document.getElementById('noResults');
        noResultsDiv.style.display = hasVisibleCards ? 'none' : 'block';

        // Update summary stats if they exist
        updateSummaryStats();
    }

    function highlightText(element, searchTerm) {
        const nodes = element.childNodes;
        
        nodes.forEach(node => {
            if (node.nodeType === 3) { // Text node
                const text = node.textContent;
                const lowerText = text.toLowerCase();
                let index = lowerText.indexOf(searchTerm);
                
                if (index >= 0) {
                    const span = document.createElement('span');
                    let lastIndex = 0;
                    const fragments = [];

                    while (index >= 0) {
                        // Add text before match
                        fragments.push(document.createTextNode(text.substring(lastIndex, index)));
                        
                        // Add highlighted match
                        const mark = document.createElement('mark');
                        mark.className = 'highlight';
                        mark.textContent = text.substring(index, index + searchTerm.length);
                        fragments.push(mark);

                        lastIndex = index + searchTerm.length;
                        index = lowerText.indexOf(searchTerm, lastIndex);
                    }

                    // Add remaining text
                    fragments.push(document.createTextNode(text.substring(lastIndex)));
                    
                    fragments.forEach(fragment => span.appendChild(fragment));
                    node.parentNode.replaceChild(span, node);
                }
            } else if (node.nodeType === 1) { // Element node
                highlightText(node, searchTerm);
            }
        });
    }

    function updateSummaryStats() {
        const reportType = document.getElementById('type').value;
        const visibleCards = document.querySelectorAll('.report-card:not([style*="display: none"]), .medical-record-card:not([style*="display: none"]), .appointment-slip:not([style*="display: none"])');
        
        // Update summary statistics based on visible cards
        if (reportType === 'appointments_list') {
            let completed = 0, cancelled = 0, pending = 0;
            visibleCards.forEach(card => {
                const status = card.querySelector('.status-badge').textContent.trim().toLowerCase();
                if (status === 'completed') completed++;
                else if (status === 'cancelled') cancelled++;
                else if (status === 'scheduled' || status === 'confirmed') pending++;
            });

            updateStatCard('total_appointments', visibleCards.length);
            updateStatCard('completed', completed);
            updateStatCard('cancelled', cancelled);
            updateStatCard('pending', pending);
        } else if (reportType === 'patients_list') {
            updateStatCard('total_patients', visibleCards.length);
        }
    }

    function updateStatCard(key, value) {
        const statCard = document.querySelector(`.stat-card:has(h3:contains("${key}"))`);
        if (statCard) {
            const numberElement = statCard.querySelector('.number');
            if (numberElement) {
                numberElement.textContent = value;
            }
        }
    }

    // Initialize search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            // Clear search on page load
            searchInput.value = '';
            
            // Add clear button functionality
            searchInput.addEventListener('input', function() {
                if (this.value) {
                    this.style.paddingRight = '30px';
                } else {
                    this.style.paddingRight = '15px';
                }
            });
        }
    });
    </script>
</body>
</html> 