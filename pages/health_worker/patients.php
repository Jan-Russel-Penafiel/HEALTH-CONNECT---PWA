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
                    $stmt = $conn->prepare("INSERT INTO users (role_id, username, email, mobile_number, first_name, middle_name, 
                                        last_name, gender, date_of_birth, address) 
                                        VALUES (:role_id, :username, :email, :mobile_number, :first_name, :middle_name, 
                                        :last_name, :gender, :date_of_birth, :address)");
                    
                    $stmt->execute([
                        ':role_id' => $role_id,
                        ':username' => $_POST['email'], // Using email as username
                        ':email' => $_POST['email'],
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
$query = "SELECT p.*, u.*, 
          COALESCE(mr.visit_date, 'No visits') as last_visit,
          COALESCE(mr.diagnosis, 'No diagnosis') as last_diagnosis
          FROM patients p
          JOIN users u ON p.user_id = u.user_id
          LEFT JOIN (
              SELECT patient_id, visit_date, diagnosis,
                     ROW_NUMBER() OVER (PARTITION BY patient_id ORDER BY visit_date DESC) as rn
              FROM medical_records
          ) mr ON mr.patient_id = p.patient_id AND mr.rn = 1
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
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
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
                <button class="btn btn-primary" onclick="showAddRecordModal()">
                    <i class="fas fa-plus"></i> Add Medical Record
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
        
        <div class="patients-grid">
            <?php if (empty($patients)): ?>
                <div class="no-results">
                    <i class="fas fa-user-injured"></i>
                    <h3>No Patients Found</h3>
                    <p>Add new patients using the button above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($patients as $patient): ?>
                    <div class="patient-card">
                        <div class="header">
                            <h3 class="name">
                                <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                <?php if ($patient['middle_name']): ?>
                                    <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                                <?php endif; ?>
                            </h3>
                           
                            <div class="demographics">
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
                            <div class="info-item" style="margin-bottom: -8px;">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($patient['mobile_number']); ?></span>
                            </div>
                        </div>

                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-id-card"></i> Contact Information
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($patient['email']); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ($patient['height'] || $patient['weight']): ?>
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-weight"></i> Physical Data
                            </div>
                            <div class="info-grid">
                                <?php if ($patient['height']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-ruler-vertical"></i>
                                        <span><?php echo htmlspecialchars($patient['height']); ?> cm</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($patient['weight']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-weight"></i>
                                        <span><?php echo htmlspecialchars($patient['weight']); ?> kg</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="visit-info">
                            <div class="title">
                                <i class="fas fa-calendar-check"></i> Last Visit
                            </div>
                            <div class="detail">
                                <?php 
                                    if ($patient['last_visit'] !== 'No visits') {
                                        echo date('M d, Y', strtotime($patient['last_visit']));
                                    } else {
                                        echo 'No previous visits';
                                    }
                                ?>
                            </div>
                            <?php if ($patient['last_diagnosis'] !== 'No diagnosis'): ?>
                                <div class="title" style="margin-top: 8px;">
                                    <i class="fas fa-stethoscope"></i> Last Diagnosis
                                </div>
                                <div class="detail">
                                    <?php echo htmlspecialchars($patient['last_diagnosis']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="actions">
                            <button class="btn-action btn-view" onclick="viewMedicalHistory(<?php echo $patient['patient_id']; ?>)">
                                <i class="fas fa-history"></i> History
                            </button>
                            <button class="btn-action btn-edit" onclick="showAddRecordModal(<?php echo $patient['patient_id']; ?>)">
                                <i class="fas fa-plus"></i> Add Record
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Medical Record Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Medical Record</h3>
                <span class="modal-close">&times;</span>
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
                    <button type="submit" class="btn btn-primary">Save Medical Record</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('recordModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Medical History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Medical History</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="medicalHistory"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('historyModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Patient</h3>
                <span class="modal-close">&times;</span>
            </div>
            <form method="POST" id="addPatientForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_patient">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="mobile_number">Mobile Number</label>
                            <input type="tel" id="mobile_number" name="mobile_number" class="form-control" required>
                        </div>

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

                        <div class="form-group">
                            <label for="height">Height (cm)</label>
                            <input type="number" id="height" name="height" class="form-control" step="0.01">
                        </div>

                        <div class="form-group">
                            <label for="weight">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" class="form-control" step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="2" required></textarea>
                    </div>

                    <h4>Emergency Contact</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emergency_contact_name">Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_number">Contact Number</label>
                            <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="emergency_contact_relationship">Relationship</label>
                            <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Patient</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button>
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
        
        async function viewMedicalHistory(patientId) {
            try {
                const response = await fetch(`medical_history.php?patient_id=${patientId}`);
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                let historyHtml = '<div class="medical-timeline">';
                
                if (data.length === 0) {
                    historyHtml += `
                        <div class="timeline-item" style="text-align: center;">
                            <i class="fas fa-file-medical" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
                            <p style="color: #666;">No medical history records found for this patient.</p>
                        </div>`;
                } else {
                    data.forEach(record => {
                        historyHtml += `
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <i class="fas fa-calendar-alt"></i> 
                                    ${new Date(record.visit_date).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })}
                                </div>
                                <div class="timeline-content">
                                    <h4><i class="fas fa-comment-medical"></i> Chief Complaint</h4>
                                    <p>${record.chief_complaint}</p>

                                    <h4><i class="fas fa-stethoscope"></i> Diagnosis</h4>
                                    <p>${record.diagnosis}</p>

                                    <h4><i class="fas fa-procedures"></i> Treatment</h4>
                                    <p>${record.treatment}</p>

                                    ${record.prescription ? `
                                        <h4><i class="fas fa-prescription"></i> Prescription</h4>
                                        <p>${record.prescription}</p>
                                    ` : ''}

                                    ${record.notes ? `
                                        <h4><i class="fas fa-notes-medical"></i> Notes</h4>
                                        <p>${record.notes}</p>
                                    ` : ''}

                                    <div class="metadata">
                                        ${record.follow_up_date ? `
                                            <div class="metadata-item">
                                                <i class="fas fa-calendar-check"></i>
                                                Follow-up: ${new Date(record.follow_up_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric'
                                                })}
                                            </div>
                                        ` : ''}
                                        <div class="metadata-item">
                                            <i class="fas fa-clock"></i>
                                            ${new Date(record.visit_date).toLocaleTimeString('en-US', {
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                historyHtml += '</div>';
                
                document.getElementById('medicalHistory').innerHTML = historyHtml;
                document.getElementById('historyModal').style.display = 'block';
            } catch (error) {
                console.error('Error fetching medical history:', error);
                alert('Error loading medical history. Please try again.');
            }
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

        // Close modals when clicking on X
        const closeButtons = document.getElementsByClassName('modal-close');
        for (let button of closeButtons) {
            button.onclick = function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        }

        // Form validation
        document.getElementById('addPatientForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const mobile = document.getElementById('mobile_number').value;
            const emergencyMobile = document.getElementById('emergency_contact_number').value;

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