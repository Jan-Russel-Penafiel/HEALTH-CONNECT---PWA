<?php
session_start();
require_once '../includes/config/database.php';
require_once '../includes/auth_check.php';

$database = new Database();
$conn = $database->getConnection();

$success_message = '';
$error_message = '';

try {
    // Get user details based on role
    $user_query = "SELECT u.* FROM users u WHERE u.user_id = :user_id";
    $stmt = $conn->prepare($user_query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get role-specific details
    switch ($_SESSION['role']) {
        case 'patient':
            $role_query = "SELECT * FROM patients WHERE user_id = :user_id";
            break;
        case 'health_worker':
            $role_query = "SELECT * FROM health_workers WHERE user_id = :user_id";
            break;
        case 'admin':
            // Admin details are in users table with role_id for admin
            $role_query = "SELECT u.* FROM users u 
                          JOIN user_roles r ON u.role_id = r.role_id 
                          WHERE u.user_id = :user_id AND r.role_name = 'admin'";
            break;
    }

    $stmt = $conn->prepare($role_query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $role_details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $conn->beginTransaction();

        try {
            // Update users table
            $update_user = "UPDATE users SET 
                          first_name = :first_name,
                          middle_name = :middle_name,
                          last_name = :last_name,
                          email = :email,
                          mobile_number = :mobile_number,
                          gender = :gender,
                          date_of_birth = :date_of_birth,
                          address = :address
                          WHERE user_id = :user_id";
            
            $stmt = $conn->prepare($update_user);
            $stmt->execute([
                ':first_name' => $_POST['first_name'],
                ':middle_name' => $_POST['middle_name'],
                ':last_name' => $_POST['last_name'],
                ':email' => $_POST['email'],
                ':mobile_number' => $_POST['mobile_number'],
                ':gender' => $_POST['gender'],
                ':date_of_birth' => $_POST['date_of_birth'],
                ':address' => $_POST['address'],
                ':user_id' => $_SESSION['user_id']
            ]);

            // Update role-specific details
            switch ($_SESSION['role']) {
                case 'patient':
                    $update_role = "UPDATE patients SET 
                                  blood_type = :blood_type,
                                  height = :height,
                                  weight = :weight,
                                  emergency_contact_name = :emergency_contact_name,
                                  emergency_contact_number = :emergency_contact_number,
                                  emergency_contact_relationship = :emergency_contact_relationship
                                  WHERE user_id = :user_id";
                    $params = [
                        ':blood_type' => $_POST['blood_type'],
                        ':height' => $_POST['height'],
                        ':weight' => $_POST['weight'],
                        ':emergency_contact_name' => $_POST['emergency_contact_name'],
                        ':emergency_contact_number' => $_POST['emergency_contact_number'],
                        ':emergency_contact_relationship' => $_POST['emergency_contact_relationship'],
                        ':user_id' => $_SESSION['user_id']
                    ];
                    break;

                case 'health_worker':
                    $update_role = "UPDATE health_workers SET 
                                  position = :position,
                                  specialty = :specialty,
                                  license_number = :license_number
                                  WHERE user_id = :user_id";
                    $params = [
                        ':position' => $_POST['position'],
                        ':specialty' => $_POST['specialty'],
                        ':license_number' => $_POST['license_number'],
                        ':user_id' => $_SESSION['user_id']
                    ];
                    break;
            }

            if (isset($update_role) && isset($params)) {
                $stmt = $conn->prepare($update_role);
                $stmt->execute($params);
            }

            // Commit transaction
            $conn->commit();
            $success_message = "Profile updated successfully!";

            // Refresh user data
            $stmt = $conn->prepare($user_query);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (isset($role_query)) {
                $stmt = $conn->prepare($role_query);
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $role_details = $stmt->fetch(PDO::FETCH_ASSOC);
            }

        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }

} catch (Exception $e) {
    $error_message = "Error loading profile: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HealthConnect</title>
    <?php include '../includes/header_links.php'; ?>
    <style>
        .profile-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 2.5em;
            color: #6c757d;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h2>My Profile</h2>
            <div>
                <a href="change_password.php" class="btn">Change Password</a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></p>
                </div>
            </div>

            <!-- Basic Information -->
            <h4>Basic Information</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="required" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control"
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control"
                           value="<?php echo htmlspecialchars($user['middle_name']); ?>">
                </div>

                <div class="form-group">
                    <label class="required" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control"
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="required" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="required" for="mobile_number">Mobile Number</label>
                    <input type="tel" id="mobile_number" name="mobile_number" class="form-control"
                           value="<?php echo htmlspecialchars($user['mobile_number']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="required" for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $user['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required" for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                           value="<?php echo htmlspecialchars($user['date_of_birth']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="required" for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" required><?php 
                        echo htmlspecialchars($user['address']); 
                    ?></textarea>
                </div>
            </div>

            <!-- Role Specific Information -->
            <?php if ($_SESSION['role'] === 'patient'): ?>
                <h4>Patient Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="blood_type">Blood Type</label>
                        <select id="blood_type" name="blood_type" class="form-control">
                            <option value="">Select Blood Type</option>
                            <option value="A+" <?php echo $role_details['blood_type'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo $role_details['blood_type'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo $role_details['blood_type'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo $role_details['blood_type'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo $role_details['blood_type'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo $role_details['blood_type'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                            <option value="O+" <?php echo $role_details['blood_type'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo $role_details['blood_type'] === 'O-' ? 'selected' : ''; ?>>O-</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="form-control" step="0.01"
                               value="<?php echo htmlspecialchars($role_details['height']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" class="form-control" step="0.01"
                               value="<?php echo htmlspecialchars($role_details['weight']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="required" for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['emergency_contact_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="required" for="emergency_contact_number">Emergency Contact Number</label>
                        <input type="tel" id="emergency_contact_number" name="emergency_contact_number" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['emergency_contact_number']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="required" for="emergency_contact_relationship">Emergency Contact Relationship</label>
                        <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['emergency_contact_relationship']); ?>" required>
                    </div>
                </div>

            <?php elseif ($_SESSION['role'] === 'health_worker'): ?>
                <h4>Health Worker Information</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required" for="position">Position</label>
                        <input type="text" id="position" name="position" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['position']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <input type="text" id="specialty" name="specialty" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['specialty']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="required" for="license_number">License Number</label>
                        <input type="text" id="license_number" name="license_number" class="form-control"
                               value="<?php echo htmlspecialchars($role_details['license_number']); ?>" required>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const mobileNumber = document.getElementById('mobile_number');
            const email = document.getElementById('email');
            const emergencyNumber = document.getElementById('emergency_contact_number');
            
            // Validate mobile number format
            const mobilePattern = /^\+?[\d\s-]{10,}$/;
            if (!mobilePattern.test(mobileNumber.value)) {
                e.preventDefault();
                alert('Please enter a valid mobile number');
                return;
            }

            // Validate emergency contact number if it exists
            if (emergencyNumber && !mobilePattern.test(emergencyNumber.value)) {
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