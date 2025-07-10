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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.mobile_number LIKE :search)";
    $params[':search'] = "%$search%";
}

// Get patients list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $count_query = "SELECT COUNT(*) FROM users u 
                    JOIN user_roles r ON u.role_id = r.role_id 
                    JOIN patients p ON u.user_id = p.user_id
                    WHERE r.role_name = 'patient' " . 
                    ($where_clause ? str_replace('WHERE', 'AND', $where_clause) : '');
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total_patients = $stmt->fetchColumn();
    $total_pages = ceil($total_patients / $limit);

    // Get patients for current page
    $query = "SELECT u.*, p.patient_id, p.blood_type,
              (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) as appointment_count,
              (SELECT COUNT(*) FROM immunization_records ir WHERE ir.patient_id = p.patient_id) as immunization_count
              FROM users u 
              JOIN user_roles r ON u.role_id = r.role_id 
              JOIN patients p ON u.user_id = p.user_id
              WHERE r.role_name = 'patient' " .
              ($where_clause ? str_replace('WHERE', 'AND', $where_clause) : '') . 
              " ORDER BY u.last_name, u.first_name 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
    $_SESSION['error'] = "Error fetching patients. Please try again.";
}

// Handle form submission for adding new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
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
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        
        .patient-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .patient-card .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .patient-card .name {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .patient-card .info-section {
            margin: 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .patient-card .info-item {
            display: flex;
            align-items: center;
            margin: 8px 0;
            color: #555;
        }
        
        .patient-card .info-item i {
            width: 20px;
            margin-right: 10px;
            color: #4a90e2;
        }
        
        .patient-card .stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        
        .patient-card .stat-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .patient-card .stat-number {
            font-size: 1.2em;
            font-weight: bold;
            color: #4a90e2;
            margin-bottom: 5px;
        }
        
        .patient-card .stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .patient-card .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .patient-card .btn-action {
            padding: 8px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .patient-card .btn-view {
            background: #4a90e2;
        }
        
        .patient-card .btn-edit {
            background: #28a745;
        }
        
        .patient-card .btn-delete {
            background: #dc3545;
        }
        
        .patient-card .btn-action:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        /* Keep existing modal styles */
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Manage Patients</h1>
            <div class="header-actions">
                <form class="search-form" action="" method="GET">
                    <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <button class="btn" onclick="showAddPatientModal()">
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

        <div class="cards-grid">
            <?php if (!empty($patients)): ?>
                <?php foreach ($patients as $patient): ?>
                    <div class="patient-card">
                        <div class="header">
                            <h3 class="name">
                                <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                <?php if ($patient['middle_name']): ?>
                                    <?php echo ' ' . htmlspecialchars($patient['middle_name'][0]) . '.'; ?>
                                <?php endif; ?>
                            </h3>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($patient['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($patient['mobile_number']); ?></span>
                            </div>
                            <?php if (isset($patient['blood_type']) && $patient['blood_type']): ?>
                            <div class="info-item">
                                <i class="fas fa-tint"></i>
                                <span>Blood Type: <?php echo htmlspecialchars($patient['blood_type']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $patient['appointment_count']; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-calendar-check"></i> Appointments
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $patient['immunization_count']; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-syringe"></i> Immunizations
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <span>Registered: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></span>
                        </div>
                        
                        <div class="actions">
                            <a href="view_patient.php?id=<?php echo $patient['user_id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_patient.php?id=<?php echo $patient['user_id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn-action btn-delete" onclick="confirmDelete(<?php echo $patient['user_id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-injured"></i>
                    <h3>No Patients Found</h3>
                    <p>Click the "Add Patient" button to add a new patient.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="btn-page active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn-page">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal" id="addPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Health Worker</h3>
                <span class="close">&times;</span>
            </div>
            <form id="addPatientForm" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
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
                </div>

                <div class="form-row">
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

                <div class="form-row">
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="form-control" step="0.01">
                    </div>

                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" class="form-control" step="0.01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_number">Emergency Contact Number</label>
                        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_relationship">Emergency Contact Relationship</label>
                        <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Patient</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }

    .modal-header h3 {
        margin: 0;
        color: #333;
    }

    .close {
        font-size: 24px;
        font-weight: bold;
        color: #666;
        cursor: pointer;
    }

    .close:hover {
        color: #333;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 15px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #666;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .form-control:focus {
        border-color: #4CAF50;
        outline: none;
        box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 16px;
        padding-right: 32px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background-color 0.2s;
    }

    .btn-primary {
        background-color: #4CAF50;
        color: white;
    }

    .btn-primary:hover {
        background-color: #45a049;
    }

    .btn-secondary {
        background-color: #f0f0f0;
        color: #333;
    }

    .btn-secondary:hover {
        background-color: #e4e4e4;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 2% auto;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    function confirmDelete(userId) {
        if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
            fetch(`/connect/api/patients/delete.php?id=${userId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting patient: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the patient');
            });
        }
    }

    // Add Patient Modal functionality
    const addPatientModal = document.getElementById('addPatientModal');
    const addPatientSpan = addPatientModal.getElementsByClassName('close')[0];

    function showAddPatientModal() {
        addPatientModal.style.display = 'block';
    }

    addPatientSpan.onclick = function() {
        addPatientModal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == addPatientModal) {
            addPatientModal.style.display = 'none';
        }
    }

    // Form validation
    document.getElementById('addPatientForm').addEventListener('submit', function(e) {
        const mobileNumber = document.getElementById('mobile_number');
        const emergencyNumber = document.getElementById('emergency_contact_number');
        const email = document.getElementById('email');
        
        // Validate mobile number format
        const mobilePattern = /^\+?[\d\s-]{10,}$/;
        if (!mobilePattern.test(mobileNumber.value)) {
            e.preventDefault();
            alert('Please enter a valid mobile number');
            return;
        }

        // Validate emergency contact number
        if (!mobilePattern.test(emergencyNumber.value)) {
            e.preventDefault();
            alert('Please enter a valid emergency contact number');
            return;
        }

        // Validate email format
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email.value)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return;
        }
    });
    </script>
</body>
</html> 