<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is health worker or admin
if (!in_array($_SESSION['role'], ['health_worker', 'admin'])) {
    header("Location: ../dashboard.php");
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get health worker ID if not already in session
if ($_SESSION['role'] === 'health_worker' && !isset($_SESSION['health_worker_id'])) {
    try {
        $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['health_worker_id'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching health worker ID: " . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_record':
                // Add new medical record
                try {
                    $query = "INSERT INTO medical_records (patient_id, health_worker_id, visit_date, chief_complaint, 
                             diagnosis, treatment, prescription, notes, follow_up_date) 
                             VALUES (:patient_id, :health_worker_id, :visit_date, :chief_complaint, 
                             :diagnosis, :treatment, :prescription, :notes, :follow_up_date)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        ':patient_id' => $_POST['patient_id'],
                        ':health_worker_id' => $_SESSION['health_worker_id'],
                        ':visit_date' => $_POST['visit_date'],
                        ':chief_complaint' => $_POST['chief_complaint'],
                        ':diagnosis' => $_POST['diagnosis'],
                        ':treatment' => $_POST['treatment'],
                        ':prescription' => $_POST['prescription'],
                        ':notes' => $_POST['notes'],
                        ':follow_up_date' => $_POST['follow_up_date']
                    ]);
                    $_SESSION['success'] = "Medical record added successfully.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (PDOException $e) {
                    error_log("Error adding medical record: " . $e->getMessage());
                    $_SESSION['error'] = "Error adding medical record: " . $e->getMessage();
                }
                break;

            case 'add_patient':
                try {
                    $conn->beginTransaction();

                    // Get role_id for patient
                    $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE role_name = 'patient'");
                    $stmt->execute();
                    $role_id = $stmt->fetchColumn();

                    // Insert into users table
                    $stmt = $conn->prepare("INSERT INTO users (role_id, username, email, password, mobile_number, first_name, middle_name, 
                                        last_name, gender, date_of_birth, address) 
                                        VALUES (:role_id, :username, :email, :password, :mobile_number, :first_name, :middle_name, 
                                        :last_name, :gender, :date_of_birth, :address)");
                    
                    $stmt->execute([
                        ':role_id' => $role_id,
                        ':username' => $_POST['username'],
                        ':email' => $_POST['email'],
                        ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        ':mobile_number' => $_POST['mobile_number'],
                        ':first_name' => $_POST['first_name'],
                        ':middle_name' => $_POST['middle_name'],
                        ':last_name' => $_POST['last_name'],
                        ':gender' => $_POST['gender'],
                        ':date_of_birth' => $_POST['date_of_birth'],
                        ':address' => $_POST['address']
                    ]);

                    $user_id = $conn->lastInsertId();

                    // Insert into patients table
                    $stmt = $conn->prepare("INSERT INTO patients (user_id, blood_type, height, weight, 
                                        emergency_contact_name, emergency_contact_number, emergency_contact_relationship) 
                                        VALUES (:user_id, :blood_type, :height, :weight, 
                                        :emergency_contact_name, :emergency_contact_number, :emergency_contact_relationship)");
                    
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':blood_type' => $_POST['blood_type'],
                        ':height' => $_POST['height'],
                        ':weight' => $_POST['weight'],
                        ':emergency_contact_name' => $_POST['emergency_contact_name'],
                        ':emergency_contact_number' => $_POST['emergency_contact_number'],
                        ':emergency_contact_relationship' => $_POST['emergency_contact_relationship']
                    ]);

                    $conn->commit();
                    $_SESSION['success'] = "Patient added successfully.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (PDOException $e) {
                    $conn->rollBack();
                    error_log("Error adding patient: " . $e->getMessage());
                    $_SESSION['error'] = "Error adding patient. Please try again.";
                }
                break;
        }
    }
}

// Fetch all patients with their latest medical record
$query = "SELECT p.*, u.*, p.is_approved,
          COALESCE(mr.visit_date, 'No visits') as last_visit,
          COALESCE(mr.diagnosis, 'No diagnosis') as last_diagnosis
          FROM patients p
          JOIN users u ON p.user_id = u.user_id
          LEFT JOIN (
              SELECT patient_id, visit_date, diagnosis,
                     ROW_NUMBER() OVER (PARTITION BY patient_id ORDER BY visit_date DESC) as rn
              FROM medical_records
          ) mr ON mr.patient_id = p.patient_id AND mr.rn = 1
          WHERE p.is_approved = 1
          ORDER BY u.last_name, u.first_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
    <style>
        /* Desktop Table Layout */
        @media (min-width: 992px) {
            .patients-grid {
                display: none;
            }
            
            .patients-table-container {
                display: block;
            }
            
            .patients-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .patients-table th,
            .patients-table td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            .patients-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #333;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .patients-table tbody tr {
                transition: all 0.2s ease;
            }
            
            .patients-table tbody tr:hover {
                background-color: #f8f9fa;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .table-patient-name {
                font-weight: 600;
                color: #333;
                margin-bottom: 4px;
            }
            
            .table-demographics {
                font-size: 0.85rem;
                color: #666;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .table-contact-info {
                font-size: 0.9rem;
                color: #666;
            }
            
            .table-contact-info div {
                margin-bottom: 3px;
            }
            
            .table-physical-data {
                font-size: 0.9rem;
                color: #666;
            }
            
            .table-last-visit {
                font-size: 0.9rem;
                color: #666;
            }
            
            .table-last-diagnosis {
                font-size: 0.85rem;
                color: #555;
                margin-top: 5px;
                font-style: italic;
            }
            
            .table-approval-status {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 500;
            }
            
            .table-approval-status.status-approved {
                background-color: #e8f5e9;
                color: #2e7d32;
            }
            
            .table-approval-status.status-pending {
                background-color: #fff3cd;
                color: #856404;
            }
            
            .table-actions {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            
            .table-actions .btn-action {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
                display: flex;
                align-items: center;
                gap: 0.3rem;
                white-space: nowrap;
            }
        }

        /* Mobile Card Layout */
        @media (max-width: 991px) {
            .patients-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            
            .patients-table-container {
                display: none;
            }
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

        .search-form {
            position: relative;
            margin-right: 10px;
        }

        .search-form input {
            padding: 10px 15px;
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .search-form input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }

        .search-form button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }

        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .patient-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .patient-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .patient-card .name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0 0 5px 0;
        }

        .patient-card .demographics {
            color: #666;
            font-size: 0.9em;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        
        .patient-card .demographics span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .patient-card .info-section {
            margin-bottom: 15px;
        }

        .patient-card .info-section-title {
            font-size: 0.9em;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .patient-card .info-section-title i {
            color: #4a90e2;
        }

        .patient-card .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .patient-card .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            font-size: 0.9em;
        }

        .patient-card .info-item i {
            color: #4a90e2;
            width: 16px;
            text-align: center;
        }

        .patient-card .visit-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .patient-card .visit-info .title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .patient-card .visit-info .detail {
            color: #666;
            font-size: 0.85em;
        }

        .patient-card .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }

        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-edit {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .btn-action:hover {
            opacity: 0.9;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }

        .no-results i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }

        .medical-timeline {
            padding: 20px;
        }

        .timeline-item {
            position: relative;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 3% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            max-width: 800px;
            width: 90%;
            position: relative;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #28a745;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .modal-header .modal-title {
            margin: 0;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            font-weight: bold;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            transition: color 0.2s;
            padding: 0;
            line-height: 1;
        }

        .modal-close:hover {
            color: #fff;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: calc(90vh - 130px);
        }

        .modal-footer {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 15px;
            border-top: 1px solid #eee;
            background-color: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95em;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }
        
        .section-divider {
            margin: 20px 0 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .section-divider h4 {
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1em;
        }
        
        .section-divider h4 i {
            color: #4a90e2;
        }
        
        .emergency-contact-grid {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .address-group {
            grid-column: span 3;
        }
        
        /* Modal Button Styles */
        .modal-footer .btn {
            padding: 6px 12px;
            font-weight: 500;
            min-width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .modal-footer .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .modal-footer .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .modal-footer .btn-secondary {
            color: #6c757d;
            border-color: #6c757d;
            background-color: #f8f9fa;
        }
        
        .modal-footer .btn-secondary:hover {
            color: #5a6268;
            background-color: #e2e6ea;
            border-color: #6c757d;
        }
        
        .modal-footer .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            background-color: transparent;
        }
        
        .modal-footer .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
            }

            .search-form {
                width: 100%;
                margin-right: 0;
            }

            .search-form input {
                width: 100%;
            }

            .patients-grid {
                grid-template-columns: 1fr;
            }
            
            /* Improved Mobile Modal Styles */
            .modal-content {
                margin: 5% auto;
                width: 95%;
                max-height: 85vh;
            }
            
            .modal-body {
                max-height: calc(85vh - 130px);
                padding: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .address-group {
                grid-column: span 1;
            }
            
            .section-divider {
                margin: 15px 0 10px;
            }
            
            .modal-footer {
                flex-direction: row;
                justify-content: space-between;
                padding: 12px 15px;
            }
            
            .modal-footer .btn {
                flex: 1;
                min-width: 0;
                padding: 6px 10px;
                font-size: 0.8em;
            }
        }
        
        /* Additional Media Query for Medium Screens */
        @media (min-width: 769px) and (max-width: 1024px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .address-group {
                grid-column: span 2;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/today_appointments_banner.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Patient Records</h1>
            <div class="header-actions">
                <form class="search-form">
                    <input type="text" id="searchPatient" placeholder="Search patients...">
                    <button type="button"><i class="fas fa-search"></i></button>
                </form>
                <button class="btn btn-primary" onclick="showAddPatientModal()">
                    <i class="fas fa-user-plus"></i> Add Patient
                </button>
                
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Desktop Table Layout -->
        <div class="patients-table-container">
            <?php if (empty($patients)): ?>
                <div class="no-results">
                    <i class="fas fa-user-injured"></i>
                    <h3>No Patients Found</h3>
                    <p>Add new patients using the button above.</p>
                </div>
            <?php else: ?>
            <table class="patients-table">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Contact Information</th>
                        <th>Physical Data</th>
                        <th>Last Visit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td>
                            <div class="table-patient-name">
                                <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                <?php if ($patient['middle_name']): ?>
                                    <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                                <?php endif; ?>
                            </div>
                            <div class="table-demographics">
                                <span>
                                    <i class="fas fa-birthday-cake"></i>
                                    <?php 
                                        $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                        echo $age . ' years';
                                    ?>
                                </span>
                                <span>
                                    <i class="fas fa-venus-mars"></i>
                                    <?php echo htmlspecialchars($patient['gender']); ?>
                                </span>
                                <?php if ($patient['blood_type']): ?>
                                    <span>
                                        <i class="fas fa-tint"></i>
                                        <?php echo htmlspecialchars($patient['blood_type']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-contact-info">
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['mobile_number']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="table-physical-data">
                                <?php if ($patient['height']): ?>
                                    <div><i class="fas fa-ruler-vertical"></i> <?php echo htmlspecialchars($patient['height']); ?> cm</div>
                                <?php endif; ?>
                                <?php if ($patient['weight']): ?>
                                    <div><i class="fas fa-weight"></i> <?php echo htmlspecialchars($patient['weight']); ?> kg</div>
                                <?php endif; ?>
                                <?php if (!$patient['height'] && !$patient['weight']): ?>
                                    <span class="text-muted">No data</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-last-visit">
                                <?php 
                                    if ($patient['last_visit'] !== 'No visits') {
                                        echo date('M d, Y', strtotime($patient['last_visit']));
                                    } else {
                                        echo 'No previous visits';
                                    }
                                ?>
                            </div>
                            <?php if ($patient['last_diagnosis'] !== 'No diagnosis'): ?>
                                <div class="table-last-diagnosis">
                                    <?php echo htmlspecialchars($patient['last_diagnosis']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="table-approval-status <?php echo $patient['is_approved'] ? 'status-approved' : 'status-pending'; ?>">
                                <i class="fas <?php echo $patient['is_approved'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                <?php echo $patient['is_approved'] ? 'Approved' : 'Pending'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="view_medical_history.php?patient_id=<?php echo $patient['patient_id']; ?>" class="btn-action btn-view">
                                    <i class="fas fa-history"></i> History
                                </a>
                                <button class="btn-action btn-edit" onclick="showAddRecordModal(<?php echo $patient['patient_id']; ?>)">
                                    <i class="fas fa-plus"></i> Add Record
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Medical Record Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-notes-medical"></i> Add Medical Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('recordModal')">
                    <span>&times;</span>
                </button>
            </div>
            <form id="recordForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_record">
                    <input type="hidden" name="patient_id" id="patientId">
                    
                    <div class="form-group">
                        <label for="visit_date">Visit Date</label>
                        <input type="datetime-local" id="visit_date" name="visit_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="chief_complaint">Chief Complaint</label>
                        <textarea id="chief_complaint" name="chief_complaint" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis</label>
                        <textarea id="diagnosis" name="diagnosis" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="treatment">Treatment</label>
                        <textarea id="treatment" name="treatment" class="form-control" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="prescription">Prescription</label>
                        <textarea id="prescription" name="prescription" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('recordModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save fa-sm"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
    


    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal" tabindex="-1" role="dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> Add New Patient</h3>
                <button type="button" class="modal-close" onclick="closeModal('addPatientModal')">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="addPatientForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_patient">
                    
                    <div class="section-divider">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required placeholder="First name">
                        </div>

                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control" placeholder="Middle name (optional)">
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required placeholder="Last name">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="blood_type">Blood Type</label>
                            <select id="blood_type" name="blood_type" class="form-control">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="section-divider">
                        <h4><i class="fas fa-id-card"></i> Contact Information</h4>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="Email address">
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required placeholder="Username">
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required placeholder="Password">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="mobile_number">Mobile Number</label>
                            <input type="tel" id="mobile_number" name="mobile_number" class="form-control" required placeholder="Phone number">
                        </div>
                        
                        <div class="form-group"></div>
                        <div class="form-group"></div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group address-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" required placeholder="Full address"></textarea>
                        </div>
                    </div>

                    <div class="section-divider">
                        <h4><i class="fas fa-weight"></i> Physical Details</h4>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="height">Height (cm)</label>
                            <input type="number" id="height" name="height" class="form-control" step="0.01" placeholder="Height in cm">
                        </div>

                        <div class="form-group">
                            <label for="weight">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" class="form-control" step="0.01" placeholder="Weight in kg">
                        </div>
                        
                        <div class="form-group"></div>
                    </div>

                    <div class="section-divider">
                        <h4><i class="fas fa-ambulance"></i> Emergency Contact</h4>
                    </div>
                    <div class="form-grid emergency-contact-grid">
                        <div class="form-group">
                            <label for="emergency_contact_name">Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required placeholder="Full name">
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_number">Contact Number</label>
                            <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" required placeholder="Phone number">
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_relationship">Relationship</label>
                            <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required placeholder="e.g. Spouse, Parent">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Patient</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Modal functionality
        function showAddRecordModal(patientId = '') {
            document.getElementById('patientId').value = patientId;
            document.getElementById('recordForm').reset();
            document.getElementById('recordModal').style.display = 'block';
        }
        
        function showAddPatientModal() {
            document.getElementById('addPatientModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        

        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }

        // We're now using onclick="closeModal()" directly on the buttons

        // Form validation
        document.getElementById('addPatientForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const username = document.getElementById('username').value;
            const mobile = document.getElementById('mobile_number').value;
            const emergencyMobile = document.getElementById('emergency_contact_number').value;

            // Username validation
            if (username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long');
                return;
            }

            // Basic email validation
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }

            // Basic mobile number validation
            if (!mobile.match(/^\+?[\d\s-]{10,}$/)) {
                e.preventDefault();
                alert('Please enter a valid mobile number');
                return;
            }

            // Emergency contact number validation
            if (!emergencyMobile.match(/^\+?[\d\s-]{10,}$/)) {
                e.preventDefault();
                alert('Please enter a valid emergency contact number');
                return;
            }
        });
        
        // Search functionality
        document.getElementById('searchPatient').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const cards = document.querySelectorAll('.patient-card');
            let hasResults = false;
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    card.style.display = '';
                    hasResults = true;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const noResults = document.querySelector('.no-results');
            if (noResults) {
                if (!hasResults && searchText) {
                    if (!document.querySelector('.search-no-results')) {
                        const searchNoResults = document.createElement('div');
                        searchNoResults.className = 'no-results search-no-results';
                        searchNoResults.innerHTML = `
                            <i class="fas fa-search"></i>
                            <h3>No Matching Patients</h3>
                            <p>Try adjusting your search terms</p>
                        `;
                        document.querySelector('.patients-grid').appendChild(searchNoResults);
                    }
                } else {
                    const searchNoResults = document.querySelector('.search-no-results');
                    if (searchNoResults) {
                        searchNoResults.remove();
                    }
                }
            }
        });
    </script>
</body>
</html> 