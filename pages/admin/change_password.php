<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Verify current password
        $query = "SELECT password FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($current_password, $user['password'])) {
            // Validate new password
            if (strlen($new_password) < 8) {
                $error_message = "New password must be at least 8 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = :password WHERE user_id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':password' => $hashed_password,
                    ':user_id' => $_SESSION['user_id']
                ]);

                $success_message = "Password changed successfully!";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    } catch (Exception $e) {
        error_log("Error changing password: " . $e->getMessage());
        $error_message = "An error occurred while changing your password. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - HealthConnect</title>
    <?php include '../../includes/header_links.php'; ?>
    <style>
        .password-form {
            max-width: 500px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .password-requirements {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }

        .password-requirements li {
            margin: 5px 0;
            color: #6c757d;
        }

        .password-requirements li.valid {
            color: #28a745;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h2>Change Password</h2>
            <a href="profile.php" class="btn">Back to Profile</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" class="password-form" id="passwordForm">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" 
                       class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" 
                       class="form-control" required>
                <div class="password-strength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="form-control" required>
            </div>

            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul id="requirements">
                    <li id="length">At least 8 characters long</li>
                    <li id="uppercase">Contains uppercase letter</li>
                    <li id="lowercase">Contains lowercase letter</li>
                    <li id="number">Contains number</li>
                    <li id="special">Contains special character</li>
                </ul>
            </div>

            <button type="submit" class="btn" id="submitBtn" disabled>Change Password</button>
        </form>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        const form = document.getElementById('passwordForm');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const strengthBar = document.querySelector('.password-strength');

        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };

            // Update requirement list styling
            Object.keys(requirements).forEach(req => {
                const li = document.getElementById(req);
                if (requirements[req]) {
                    li.classList.add('valid');
                } else {
                    li.classList.remove('valid');
                }
            });

            // Calculate password strength
            const strength = Object.values(requirements).filter(Boolean).length;
            strengthBar.className = 'password-strength';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }

            // Enable submit button if all requirements are met
            submitBtn.disabled = !Object.values(requirements).every(Boolean) || 
                               newPassword.value !== confirmPassword.value;
        }

        newPassword.addEventListener('input', () => {
            validatePassword(newPassword.value);
        });

        confirmPassword.addEventListener('input', () => {
            validatePassword(newPassword.value);
        });

        form.addEventListener('submit', function(e) {
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
