<?php
// Start session
session_start();

// Get the selected role from URL parameter
$selected_role = isset($_GET['role']) ? $_GET['role'] : '';

// Validate role
$valid_roles = ['admin', 'health_worker', 'patient'];
if (!empty($selected_role) && !in_array($selected_role, $valid_roles)) {
    $selected_role = '';
}

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['role'] == 'health_worker') {
        header("Location: health_worker/dashboard.php");
    } else {
        header("Location: patient/dashboard.php");
    }
    exit;
}

// Include database connection and PHPMailer
require_once '../includes/config/database.php';
require_once '../vendor/autoload.php'; // Make sure PHPMailer is installed via composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to generate OTP
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to send OTP via email
function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'vmctaccollege@gmail.com'; // Replace with your Gmail
        $mail->Password = 'tqqs fkkh lbuz jbeg'; // Replace with your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('vmctaccollege@gmail.com', 'HealthConnect');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'HealthConnect Login OTP';
        $mail->Body = "Your OTP for HealthConnect login is: <b>$otp</b><br>This OTP will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$error = "";
$success = "";
$show_otp_form = false;
$show_forgot_password = isset($_GET['forgot']) && $_GET['forgot'] == '1';

// Process the login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Username and Password authentication (no OTP)
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role_filter = isset($_POST['role_filter']) ? $_POST['role_filter'] : '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password";
        } else {
            // Build query based on role filter
            $role_condition = '';
            if (!empty($role_filter)) {
                $role_condition = " AND r.role_name = :role_filter";
            }
            
            // Check if username and password match
            $query = "SELECT u.user_id, u.email, u.username, u.password, r.role_name, u.first_name, u.last_name, u.mobile_number,
                      CASE WHEN r.role_name = 'patient' THEN 
                        (SELECT is_approved FROM patients p WHERE p.user_id = u.user_id)
                      ELSE 1 END as is_approved
                      FROM users u
                      JOIN user_roles r ON u.role_id = r.role_id
                      WHERE u.username = :username AND u.password = :password AND u.is_active = 1" . $role_condition;
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $password);
            if (!empty($role_filter)) {
                $stmt->bindParam(":role_filter", $role_filter);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if patient is approved
                if ($row['role_name'] === 'patient' && !$row['is_approved']) {
                    $error = "Your account is pending approval. Please wait for admin approval.";
                } else {
                    // Update last login and set session
                    $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(":user_id", $row['user_id']);
                    $update_stmt->execute();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role_name'];
                    $_SESSION['name'] = $row['first_name'] . " " . $row['last_name'];
                    
                    // Redirect based on role
                    if ($row['role_name'] == 'admin') {
                        header("Location: admin/dashboard.php");
                    } elseif ($row['role_name'] == 'health_worker') {
                        header("Location: health_worker/dashboard.php");
                    } else {
                        header("Location: patient/dashboard.php");
                    }
                    exit;
                }
            } else {
                if (!empty($role_filter)) {
                    $role_name = ucwords(str_replace('_', ' ', $role_filter));
                    $error = "No {$role_name} account found with this username and password combination or account is inactive";
                } else {
                    $error = "Username and password combination not found or account is inactive";
                }
            }
        }
    } elseif (isset($_POST['identifier'])) {
        // Step 1: Email or Username submission
        $identifier = trim($_POST['identifier']);
        $role_filter = isset($_POST['role_filter']) ? $_POST['role_filter'] : '';
        
        if (empty($identifier)) {
            $error = "Please enter your email address or username";
        } else {
            // Build query based on role filter
            $role_condition = '';
            if (!empty($role_filter)) {
                $role_condition = " AND r.role_name = :role_filter";
            }
            
            // Check if email or username exists
            $query = "SELECT u.user_id, u.email, u.username, r.role_name, u.first_name, u.last_name 
                      FROM users u
                      JOIN user_roles r ON u.role_id = r.role_id
                      WHERE (u.email = :identifier OR u.username = :identifier) AND u.is_active = 1" . $role_condition;
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":identifier", $identifier);
            if (!empty($role_filter)) {
                $stmt->bindParam(":role_filter", $role_filter);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $email = $row['email']; // Get the email to send OTP
                
                // Generate and store OTP
                $otp = generateOTP();
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                $update_query = "UPDATE users SET otp = :otp, otp_expiry = :expiry WHERE email = :email";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(":otp", $otp);
                $update_stmt->bindParam(":expiry", $expiry);
                $update_stmt->bindParam(":email", $email);
                
                if ($update_stmt->execute() && sendOTP($email, $otp)) {
                    $_SESSION['temp_email'] = $email;
                    $_SESSION['temp_role'] = $row['role_name'];
                    $show_otp_form = true;
                    $success = "OTP has been sent to your email address";
                } else {
                    $error = "Failed to send OTP. Please try again.";
                }
            } else {
                if (!empty($role_filter)) {
                    $role_name = ucwords(str_replace('_', ' ', $role_filter));
                    $error = "No {$role_name} account found with this email/username or account is inactive";
                } else {
                    $error = "Email or username not found or account is inactive";
                }
            }
        }
    } elseif (isset($_POST['otp'])) {
        // Step 2: OTP verification
        $otp = trim($_POST['otp']);
        $email = $_SESSION['temp_email'] ?? '';
        
        if (empty($otp) || empty($email)) {
            $error = empty($otp) ? "Please enter the OTP" : "Session expired. Please try again.";
            $show_otp_form = true;
        } else {
            // Debug logging
            error_log("Verifying OTP: " . $otp . " for email: " . $email);
            
            // Verify OTP with proper date comparison and check patient approval status
            $query = "SELECT u.user_id, u.email, u.username, r.role_name, u.first_name, u.last_name, u.otp, u.otp_expiry,
                      CASE WHEN r.role_name = 'patient' THEN 
                        (SELECT is_approved FROM patients p WHERE p.user_id = u.user_id)
                      ELSE 1 END as is_approved
                      FROM users u
                      JOIN user_roles r ON u.role_id = r.role_id
                      WHERE u.email = :email AND u.is_active = 1";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stored_otp = $row['otp'];
                $otp_expiry = strtotime($row['otp_expiry']);
                
                error_log("Stored OTP: " . $stored_otp . ", Expiry: " . $row['otp_expiry']);
                
                if ($stored_otp === $otp && $otp_expiry > time()) {
                    // Check if patient is approved
                    if ($row['role_name'] === 'patient' && !$row['is_approved']) {
                        $error = "Your account is pending approval. Please wait for admin approval.";
                        $show_otp_form = true;
                    } else {
                        // Clear OTP and set session
                        $clear_query = "UPDATE users SET otp = NULL, otp_expiry = NULL, last_login = NOW() WHERE email = :email";
                        $clear_stmt = $conn->prepare($clear_query);
                        $clear_stmt->bindParam(":email", $email);
                        $clear_stmt->execute();
                        
                        // Set session variables
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role_name'];
                        $_SESSION['name'] = $row['first_name'] . " " . $row['last_name'];
                        
                        unset($_SESSION['temp_email']);
                        
                        // Redirect based on role
                        if ($row['role_name'] == 'admin') {
                            header("Location: admin/dashboard.php");
                        } elseif ($row['role_name'] == 'health_worker') {
                            header("Location: health_worker/dashboard.php");
                        } else {
                            header("Location: patient/dashboard.php");
                        }
                        exit;
                    }
                } else {
                    $error = $otp_expiry <= time() ? "OTP has expired" : "Invalid OTP";
                    $show_otp_form = true;
                }
            } else {
                $error = "Invalid session. Please try again.";
                $show_otp_form = false;
            }
        }
    } elseif (isset($_POST['forgot_email'])) {
        // Forgot Password: Send password via email
        $email = trim($_POST['forgot_email']);
        $role_filter = isset($_POST['role_filter']) ? $_POST['role_filter'] : '';
        
        if (empty($email)) {
            $error = "Please enter your email address";
            $show_forgot_password = true;
        } else {
            // Build query based on role filter
            $role_condition = '';
            if (!empty($role_filter)) {
                $role_condition = " AND r.role_name = :role_filter";
            }
            
            // Check if email exists and get user details
            $query = "SELECT u.user_id, u.email, u.username, u.password, r.role_name, u.first_name, u.last_name,
                      CASE WHEN r.role_name = 'patient' THEN 
                        (SELECT is_approved FROM patients p WHERE p.user_id = u.user_id)
                      ELSE 1 END as is_approved
                      FROM users u
                      JOIN user_roles r ON u.role_id = r.role_id
                      WHERE u.email = :email AND u.is_active = 1" . $role_condition;
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":email", $email);
            if (!empty($role_filter)) {
                $stmt->bindParam(":role_filter", $role_filter);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if patient is approved
                if ($row['role_name'] === 'patient' && !$row['is_approved']) {
                    $error = "Your account is pending approval. Please wait for admin approval.";
                    $show_forgot_password = true;
                } else {
                    // Send password via email
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'vmctaccollege@gmail.com';
                        $mail->Password = 'tqqs fkkh lbuz jbeg';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        // Recipients
                        $mail->setFrom('vmctaccollege@gmail.com', 'HealthConnect');
                        $mail->addAddress($email);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'HealthConnect - Password Recovery';
                        $mail->Body = "
                            <h2>Password Recovery - HealthConnect</h2>
                            <p>Hello {$row['first_name']} {$row['last_name']},</p>
                            <p>You requested to recover your password for HealthConnect.</p>
                            <p><strong>Your login credentials:</strong></p>
                            <ul>
                                <li><strong>Username:</strong> {$row['username']}</li>
                                <li><strong>Password:</strong> {$row['password']}</li>
                                <li><strong>Role:</strong> " . ucwords(str_replace('_', ' ', $row['role_name'])) . "</li>
                            </ul>
                            <p>Please keep this information secure and consider changing your password after logging in.</p>
                            <p>If you did not request this, please contact our support team immediately.</p>
                            <br>
                            <p>Best regards,<br>HealthConnect Team<br>Brgy. Poblacion Health Center</p>
                        ";

                        $mail->send();
                        $success = "Your login credentials have been sent to your email address.";
                        $show_forgot_password = false;
                    } catch (Exception $e) {
                        $error = "Failed to send email. Please try again later.";
                        $show_forgot_password = true;
                    }
                }
            } else {
                if (!empty($role_filter)) {
                    $role_name = ucwords(str_replace('_', ' ', $role_filter));
                    $error = "No {$role_name} account found with this email address or account is inactive";
                } else {
                    $error = "Email address not found or account is inactive";
                }
                $show_forgot_password = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to HealthConnect - Brgy. Poblacion Health Center">
    <meta name="theme-color" content="#4CAF50">
    <title>Login - HealthConnect</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="../manifest.json">
    <link rel="apple-touch-icon" href="../assets/images/icon-192x192.png">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        /* Additional styles for enhanced login UI */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding-bottom: 70px; /* Add space for footer nav */
            padding-top: 60px; /* Match header height */
        }
        
        /* Top Navbar Styles */
        .top-navbar {
            background: #fff;
            padding: 0.5rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 56px;
        }

        .top-navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 20px;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .navbar-brand img {
            height: 24px;
            margin-right: 8px;
        }

        /* Desktop Navigation */
        .desktop-nav {
            display: none;
            flex: 1;
            margin-left: 2rem;
        }

        .main-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .main-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .main-nav .nav-link i {
            font-size: 0.9rem;
        }

        .main-nav .nav-link:hover {
            color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }

        .main-nav .nav-link.active {
            color: #4CAF50;
            background: rgba(76, 175, 80, 0.15);
            font-weight: 600;
        }

        /* Auth Menu */
        .auth-menu {
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .auth-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .auth-link:hover {
            color: #4CAF50;
            background: rgba(76, 175, 80, 0.1);
        }

        .auth-link.active {
            color: #4CAF50;
            background: rgba(76, 175, 80, 0.15);
            font-weight: 600;
        }

        .auth-link.register-btn {
            background: #4CAF50;
            color: white;
        }

        .auth-link.register-btn:hover {
            background: #388E3C;
            color: white;
        }

        /* Desktop Layout */
        @media (min-width: 769px) {
            .desktop-nav, .auth-menu {
                display: flex;
            }
            
            .footer-nav {
                display: none;
            }
            
            body {
                padding-bottom: 0;
                padding-top: 56px;
            }
        }

        /* Mobile Layout */
        @media (max-width: 768px) {
            .desktop-nav, .auth-menu {
                display: none;
            }
            
            .top-navbar {
                height: 48px;
            }
            
            body {
                padding-top: 48px;
            }
        }
        
        .header-content {
            padding: 8px 20px; /* Reduced padding */
            height: 100%;
        }
        
        .logo img {
            height: 35px; /* Smaller logo */
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo h1 {
            font-size: 1.2rem; /* Smaller font */
        }
        
        .auth-container {
            min-height: calc(100vh - 150px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 20px 50px; /* Reduced top padding */
            background: linear-gradient(135deg, rgba(200, 230, 201, 0.2), rgba(76, 175, 80, 0.1));
        }
        
        .auth-form {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        
        .auth-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
        }
        
        .auth-form h2 {
            font-size: 2rem;
            color: var(--primary-dark);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }
        
        .help-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 8px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .auth-links {
            text-align: center;
            margin-top: 25px;
            font-size: 0.95rem;
        }
        
        .auth-links a {
            color: var(--primary-color);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .auth-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .alert::before {
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            border-left: 4px solid var(--success);
            color: #2e7d32;
        }
        
        .alert-success::before {
            content: "\f058"; /* check-circle icon */
        }
        
        .alert-error {
            background-color: rgba(244, 67, 54, 0.1);
            border-left: 4px solid var(--error);
            color: #d32f2f;
        }
        
        .alert-error::before {
            content: "\f057"; /* times-circle icon */
        }
        
        footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 20px 0;
            text-align: center;
        }
        
        /* Footer Navigation */
        .footer-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 10px 0;
        }
        
        .footer-nav-container {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .footer-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.8rem;
            padding: 5px 0;
            transition: all 0.3s ease;
        }
        
        .footer-nav-item i {
            font-size: 1.4rem;
            margin-bottom: 3px;
        }
        
        .footer-nav-item.active {
            color: var(--primary-color);
        }
        
        .footer-nav-item:hover {
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        /* Role-specific styling */
        .role-header {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
        }

        .role-header.admin {
            background: linear-gradient(135deg, rgba(255, 87, 34, 0.1), rgba(255, 87, 34, 0.05));
        }

        .role-header.health-worker {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(33, 150, 243, 0.05));
        }

        .role-header.patient {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
        }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #4CAF50;
        }

        .role-icon.admin {
            color: #FF5722;
        }

        .role-icon.health-worker {
            color: #2196F3;
        }

        .role-icon.patient {
            color: #4CAF50;
        }

        .role-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            color: #333;
        }

        .role-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin: 5px 0 0 0;
        }

        .role-selector {
            margin-bottom: 25px;
        }

        .role-selector label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .role-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }

        .change-role-link {
            text-align: center;
            margin-top: 15px;
        }

        .change-role-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .change-role-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .role-header {
                padding: 12px;
            }
            
            .role-icon {
                font-size: 2rem;
            }
            
            .role-title {
                font-size: 1.1rem;
            }
        }

        /* Authentication Method Styles */
        .auth-method-selector {
            margin-bottom: 25px;
        }

        .auth-method-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 20px;
        }

        .auth-method-tab {
            flex: 1;
            text-align: center;
            padding: 10px 15px;
            background: none;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #666;
        }

        .auth-method-tab.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .auth-method-tab:hover:not(.active) {
            color: var(--primary-color);
        }

        .auth-form-section {
            display: none;
        }

        .auth-form-section.active {
            display: block;
        }

        .auth-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
            color: #999;
            font-size: 0.9rem;
        }

        .auth-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
            z-index: 1;
        }

        .auth-divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            z-index: 2;
        }
        
        /* Disabled form fields styling */
        .form-control:disabled {
            background-color: #f8f9fa;
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .auth-form-section:not(.active) {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Role Selection Modal */
        .role-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        }

        .role-modal.show {
            display: flex;
        }

        .role-modal-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 350px;
            width: 100%;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .role-modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .role-modal-header h2 {
            color: #333;
            margin-bottom: 8px;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .role-modal-header p {
            color: #666;
            font-size: 0.85rem;
        }

        .role-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .role-close:hover {
            color: #333;
        }

        .role-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .role-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }

        .role-card:hover {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.05);
            transform: translateY(-2px);
            color: #333;
            text-decoration: none;
        }

        .role-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #4CAF50;
        }

        .role-card h3 {
            margin-bottom: 5px;
            font-size: 1rem;
            font-weight: 600;
        }

        .role-card p {
            font-size: 0.75rem;
            color: #666;
            margin: 0;
            line-height: 1.3;
        }

        .role-card.admin i {
            color: #FF5722;
        }

        .role-card.admin:hover {
            border-color: #FF5722;
            background: rgba(255, 87, 34, 0.05);
        }

        .role-card.health-worker i {
            color: #2196F3;
        }

        .role-card.health-worker:hover {
            border-color: #2196F3;
            background: rgba(33, 150, 243, 0.05);
        }

        @media (max-width: 768px) {
            .role-modal-content {
                padding: 20px 15px;
                margin: 0 15px;
            }

            .role-modal-header h2 {
                font-size: 1.2rem;
            }

            .role-modal-header p {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="top-navbar">
        <div class="container">
            <a href="/connect/" class="navbar-brand">
                <img src="/connect/assets/images/health-center.jpg" alt="HealthConnect">
                HealthConnect
            </a>
            
            <!-- Desktop Navigation -->
            <div class="desktop-nav">
                <nav class="main-nav">
                    <a href="/connect/index.php#home" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                    <a href="/connect/index.php#features" class="nav-link">
                        <i class="fas fa-list-ul"></i>
                        <span>Features</span>
                    </a>
                    <a href="/connect/index.php#about" class="nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span>About</span>
                    </a>
                    <a href="/connect/index.php#contact" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        <span>Contact</span>
                    </a>
                </nav>
            </div>
            
            <div class="auth-menu">
                <a href="#" class="auth-link active" onclick="showRoleSelection()">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a href="/connect/pages/register.php" class="auth-link register-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Login Section -->
    <div class="auth-container">
        <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . (!empty($selected_role) ? '?role=' . $selected_role : ''); ?>">
            <?php if (!empty($selected_role)): ?>
                <!-- Role Header -->
                <div class="role-header <?php echo $selected_role; ?>">
                    <div class="role-icon <?php echo $selected_role; ?>">
                        <?php 
                        switch($selected_role) {
                            case 'admin':
                                echo '<i class="fas fa-user-shield"></i>';
                                break;
                            case 'health_worker':
                                echo '<i class="fas fa-user-md"></i>';
                                break;
                            case 'patient':
                                echo '<i class="fas fa-user"></i>';
                                break;
                        }
                        ?>
                    </div>
                    <h3 class="role-title">
                        <?php 
                        switch($selected_role) {
                            case 'admin':
                                echo 'Administrator Login';
                                break;
                            case 'health_worker':
                                echo 'Health Worker Login';
                                break;
                            case 'patient':
                                echo 'Patient Login';
                                break;
                        }
                        ?>
                    </h3>
                    <p class="role-subtitle">
                        <?php 
                        switch($selected_role) {
                            case 'admin':
                                echo 'Access system administration features';
                                break;
                            case 'health_worker':
                                echo 'Manage appointments and patient records';
                                break;
                            case 'patient':
                                echo 'Access your health records and appointments';
                                break;
                        }
                        ?>
                    </p>
                </div>
                <input type="hidden" name="role_filter" value="<?php echo htmlspecialchars($selected_role); ?>">
            <?php else: ?>
                <h2>Login to HealthConnect</h2>
                
                <!-- Role Selector -->
                <div class="role-selector">
                    <label for="role_filter"><i class="fas fa-user-tag"></i> Login as</label>
                    <select name="role_filter" id="role_filter" class="role-select">
                        <option value="">Select Role</option>
                        <option value="admin">Administrator</option>
                        <option value="health_worker">Health Worker</option>
                        <option value="patient">Patient</option>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!$show_otp_form && !$show_forgot_password): ?>
            <!-- Authentication Method Selector -->
            <div class="auth-method-selector">
                <div class="auth-method-tabs">
                    <button type="button" class="auth-method-tab active" data-method="email" onclick="switchAuthMethod('email')">
                        <i class="fas fa-envelope"></i> Email/Username + OTP
                    </button>
                    <button type="button" class="auth-method-tab" data-method="password" onclick="switchAuthMethod('password')">
                        <i class="fas fa-key"></i> Username + Password
                    </button>
                </div>

                <!-- Email/Username + OTP Form -->
                <div id="email-auth" class="auth-form-section active">
                    <div class="form-group">
                        <label for="identifier"><i class="fas fa-user"></i> Email or Username</label>
                        <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Enter your email or username">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">
                            <i class="fas fa-paper-plane"></i> Send OTP
                        </button>
                    </div>
                </div>

                <!-- Username + Password Form -->
                <div id="password-auth" class="auth-form-section">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-key"></i> Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </div>
                </div>
            </div>
            <?php elseif($show_forgot_password): ?>
            <!-- Forgot Password Form -->
            <div class="form-group">
                <label for="forgot_email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="forgot_email" name="forgot_email" class="form-control" placeholder="Enter your email address" required>
                <p class="help-text"><i class="fas fa-info-circle"></i> We'll send your login credentials to this email</p>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Send Password
                </button>
            </div>
            
            <div class="auth-links">
                <p><a href="javascript:void(0)" onclick="showLoginForm()">Back to Login</a></p>
            </div>
            <?php else: ?>
            <!-- OTP Form -->
            <div class="form-group">
                <label for="otp"><i class="fas fa-key"></i> Enter OTP</label>
                <input type="text" id="otp" name="otp" class="form-control" maxlength="6" placeholder="6-digit code" required>
                <p class="help-text"><i class="fas fa-info-circle"></i> Please check your email for the OTP</p>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-check-circle"></i> Verify OTP
                </button>
            </div>
            <?php endif; ?>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Register</a></p>
                <p><a href="javascript:void(0)" onclick="showForgotPassword()">Forgot Password?</a></p>
                <?php if (!empty($selected_role)): ?>
                <div class="change-role-link">
                    <a href="login.php">
                        <i class="fas fa-exchange-alt"></i> Change Role
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Role Selection Modal -->
    <div class="role-modal" id="roleModal">
        <div class="role-modal-content">
            <span class="role-close" onclick="hideRoleSelection()">&times;</span>
            <div class="role-modal-header">
                <h2>Select Your Role</h2>
                <p>Choose how you want to access HealthConnect</p>
            </div>
            <div class="role-grid">
                <a href="login.php?role=admin" class="role-card admin">
                    <i class="fas fa-user-shield"></i>
                    <h3>Administrator</h3>
                    <p>Manage the health center system, users, and reports</p>
                </a>
                <a href="login.php?role=health_worker" class="role-card health-worker">
                    <i class="fas fa-user-md"></i>
                    <h3>Health Worker</h3>
                    <p>Manage appointments, patient records, and immunizations</p>
                </a>
                <a href="login.php?role=patient" class="role-card patient">
                    <i class="fas fa-user"></i>
                    <h3>Patient</h3>
                    <p>Book appointments, view medical records, and track health</p>
                </a>
            </div>
        </div>
    </div>
  
    
    <!-- Footer Navigation -->
    <div class="footer-nav">
        <div class="footer-nav-container">
            <a href="../index.php" class="footer-nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="../index.php#features" class="footer-nav-item">
                <i class="fas fa-list-ul"></i>
                <span>Features</span>
            </a>
            <a href="../index.php#about" class="footer-nav-item">
                <i class="fas fa-info-circle"></i>
                <span>About</span>
            </a>
            <a href="../index.php#contact" class="footer-nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="login.php" class="footer-nav-item active">
                <i class="fas fa-user"></i>
                <span>Login</span>
            </a>
            <a href="register.php" class="footer-nav-item">
                <i class="fas fa-user-plus"></i>
                <span>Register</span>
            </a>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="../assets/js/app.js"></script>
    
    <script>
        function switchAuthMethod(method) {
            // Check if auth method elements exist (they won't exist in forgot password mode)
            const methodTab = document.querySelector(`[data-method="${method}"]`);
            const authSection = document.getElementById(`${method}-auth`);
            
            if (!methodTab || !authSection) {
                return; // Exit if elements don't exist
            }
            
            // Remove active class from all tabs and sections
            document.querySelectorAll('.auth-method-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.auth-form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Add active class to selected tab and section
            methodTab.classList.add('active');
            authSection.classList.add('active');
            
            // Clear and manage form fields when switching
            if (method === 'email') {
                // Clear password auth fields and disable them
                if (document.getElementById('username')) {
                    document.getElementById('username').value = '';
                    document.getElementById('username').removeAttribute('required');
                    document.getElementById('username').disabled = true;
                }
                if (document.getElementById('password')) {
                    document.getElementById('password').value = '';
                    document.getElementById('password').removeAttribute('required');
                    document.getElementById('password').disabled = true;
                }
                // Enable and require email auth field
                if (document.getElementById('identifier')) {
                    document.getElementById('identifier').setAttribute('required', 'required');
                    document.getElementById('identifier').disabled = false;
                }
            } else {
                // Clear email auth field and disable it
                if (document.getElementById('identifier')) {
                    document.getElementById('identifier').value = '';
                    document.getElementById('identifier').removeAttribute('required');
                    document.getElementById('identifier').disabled = true;
                }
                // Enable and require password auth fields
                if (document.getElementById('username')) {
                    document.getElementById('username').setAttribute('required', 'required');
                    document.getElementById('username').disabled = false;
                }
                if (document.getElementById('password')) {
                    document.getElementById('password').setAttribute('required', 'required');
                    document.getElementById('password').disabled = false;
                }
            }
        }
        
        // Initialize form validation on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Only initialize auth method if not in forgot password mode
            const authMethodTabs = document.querySelectorAll('.auth-method-tab');
            if (authMethodTabs.length > 0) {
                // Set initial required attributes
                switchAuthMethod('email');
            }
        });
        
        // Handle form submission to ensure only active fields are processed
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            // Check if we're in forgot password mode
            if (document.getElementById('forgot_email')) {
                // In forgot password mode, just validate email
                const email = document.getElementById('forgot_email').value.trim();
                if (!email) {
                    e.preventDefault();
                    alert('Please enter your email address');
                    return false;
                }
                return true;
            }
            
            // Check if we're in OTP mode
            if (document.getElementById('otp')) {
                // In OTP mode, just validate OTP
                const otp = document.getElementById('otp').value.trim();
                if (!otp) {
                    e.preventDefault();
                    alert('Please enter the OTP');
                    return false;
                }
                return true;
            }
            
            // Normal login mode - check active method
            const activeMethodTab = document.querySelector('.auth-method-tab.active');
            if (!activeMethodTab) {
                return true; // No active method tabs, let form submit normally
            }
            
            const activeMethod = activeMethodTab.getAttribute('data-method');
            
            if (activeMethod === 'password') {
                // For password authentication, ensure username and password are filled
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password');
                    return false;
                }
            } else {
                // For email authentication, ensure identifier is filled
                const identifier = document.getElementById('identifier').value.trim();
                
                if (!identifier) {
                    e.preventDefault();
                    alert('Please enter your email or username');
                    return false;
                }
            }
        });
        
        function showForgotPassword() {
            // Hide current form and show forgot password form
            window.location.href = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'forgot=1';
        }
        
        function showLoginForm() {
            // Remove forgot parameter and reload
            let url = window.location.href;
            url = url.replace(/[?&]forgot=1/, '');
            window.location.href = url;
        }
        
        // Role Selection Modal Functions
        function showRoleSelection() {
            document.getElementById('roleModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function hideRoleSelection() {
            document.getElementById('roleModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRoleSelection();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideRoleSelection();
            }
        });
    </script>
</body>
</html> 