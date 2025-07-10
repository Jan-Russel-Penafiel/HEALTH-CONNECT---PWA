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

// Get patient ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Update users table
        $stmt = $conn->prepare("UPDATE users SET 
                              email = :email,
                              mobile_number = :mobile_number,
                              first_name = :first_name,
                              middle_name = :middle_name,
                              last_name = :last_name,
                              gender = :gender,
                              date_of_birth = :date_of_birth,
                              address = :address
                              WHERE user_id = :user_id");
        
        $stmt->execute([
            ':email' => $_POST['email'],
            ':mobile_number' => $_POST['mobile_number'],
            ':first_name' => $_POST['first_name'],
            ':middle_name' => $_POST['middle_name'],
            ':last_name' => $_POST['last_name'],
            ':gender' => $_POST['gender'],
            ':date_of_birth' => $_POST['date_of_birth'],
            ':address' => $_POST['address'],
            ':user_id' => $user_id
        ]);

        // Update patients table
        $stmt = $conn->prepare("UPDATE patients SET 
                              blood_type = :blood_type,
                              height = :height,
                              weight = :weight,
                              emergency_contact_name = :emergency_contact_name,
                              emergency_contact_number = :emergency_contact_number,
                              emergency_contact_relationship = :emergency_contact_relationship
                              WHERE user_id = :user_id");
        
        $stmt->execute([
            ':blood_type' => $_POST['blood_type'],
            ':height' => $_POST['height'] ?: null,
            ':weight' => $_POST['weight'] ?: null,
            ':emergency_contact_name' => $_POST['emergency_contact_name'],
            ':emergency_contact_number' => $_POST['emergency_contact_number'],
            ':emergency_contact_relationship' => $_POST['emergency_contact_relationship'],
            ':user_id' => $user_id
        ]);

        $conn->commit();
        $_SESSION['success'] = "Patient information updated successfully.";
        header('Location: view_patient.php?id=' . $user_id);
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error updating patient: " . $e->getMessage());
        $_SESSION['error'] = "Error updating patient information. Please try again.";
    }
}

try {
    // Get patient details
    $query = "SELECT u.*, p.*
              FROM users u 
              JOIN patients p ON u.user_id = p.user_id
              WHERE u.user_id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $_SESSION['error'] = "Patient not found.";
        header('Location: patients.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching patient details: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching patient details. Please try again.";
    header('Location: patients.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        .edit-form {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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
            font-weight: 500;
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

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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

        .btn-back {
            padding: 8px 16px;
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #e4e4e4;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="header-actions">
                <a href="view_patient.php?id=<?php echo $user_id; ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Patient Details
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="edit-form" id="editPatientForm">
            <!-- Personal Information -->
            <div class="form-section">
                <h3 class="section-title">Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['middle_name']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $patient['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                               value="<?php echo $patient['date_of_birth']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="blood_type">Blood Type</label>
                        <select id="blood_type" name="blood_type" class="form-control">
                            <option value="">Select Blood Type</option>
                            <?php
                            $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_types as $type) {
                                echo '<option value="' . $type . '"' . 
                                     ($patient['blood_type'] === $type ? ' selected' : '') . 
                                     '>' . $type . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="form-control" step="0.01" 
                               value="<?php echo htmlspecialchars($patient['height']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" class="form-control" step="0.01" 
                               value="<?php echo htmlspecialchars($patient['weight']); ?>">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <h3 class="section-title">Contact Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="mobile_number">Mobile Number</label>
                        <input type="tel" id="mobile_number" name="mobile_number" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['mobile_number']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" required><?php echo htmlspecialchars($patient['address']); ?></textarea>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="form-section">
                <h3 class="section-title">Emergency Contact</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergency_contact_name">Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_number">Contact Number</label>
                        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['emergency_contact_number']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="emergency_contact_relationship">Relationship</label>
                    <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" 
                           value="<?php echo htmlspecialchars($patient['emergency_contact_relationship']); ?>" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="view_patient.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('editPatientForm').addEventListener('submit', function(e) {
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