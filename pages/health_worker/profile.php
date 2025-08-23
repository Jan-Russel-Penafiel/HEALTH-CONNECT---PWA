<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is health worker
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'health_worker') {
    header('Location: ../../index.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$success_message = '';
$error_message = '';

try {
    // Get user details
    $user_query = "SELECT u.* FROM users u WHERE u.user_id = :user_id";
    $stmt = $conn->prepare($user_query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get health worker specific details
    $role_query = "SELECT * FROM health_workers WHERE user_id = :user_id";
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

            // Update health worker details
            $update_role = "UPDATE health_workers SET 
                          position = :position,
                          specialty = :specialty,
                          license_number = :license_number
                          WHERE user_id = :user_id";
            
            $stmt = $conn->prepare($update_role);
            $stmt->execute([
                ':position' => $_POST['position'],
                ':specialty' => $_POST['specialty'],
                ':license_number' => $_POST['license_number'],
                ':user_id' => $_SESSION['user_id']
            ]);

            // Commit transaction
            $conn->commit();
            $success_message = "Profile updated successfully!";

            // Refresh user data
            $stmt = $conn->prepare($user_query);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare($role_query);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $role_details = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Health Worker Profile - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 2em;
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

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h2>My Profile</h2>
            <div>
                <a href="change_password.php" class="btn btn-secondary">Change Password</a>
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
                    <p class="text-muted">Health Worker</p>
                    <?php if (!empty($role_details['position'])): ?>
                        <p class="badge badge-primary"><?php echo htmlspecialchars($role_details['position']); ?></p>
                    <?php endif; ?>
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
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $user['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
                           value="<?php echo htmlspecialchars($user['date_of_birth']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>

            <!-- Professional Information -->
            <h4>Professional Information</h4>
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

            <div class="form-group text-right">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html> 