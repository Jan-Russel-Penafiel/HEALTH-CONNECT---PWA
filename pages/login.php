<?php
// Start session
session_start();

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

// Process the login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (isset($_POST['identifier'])) {
        // Step 1: Email or Username submission
        $identifier = trim($_POST['identifier']);
        
        if (empty($identifier)) {
            $error = "Please enter your email address or username";
        } else {
            // Check if email or username exists
            $query = "SELECT u.user_id, u.email, u.username, r.role_name, u.first_name, u.last_name 
                      FROM users u
                      JOIN user_roles r ON u.role_id = r.role_id
                      WHERE (u.email = :identifier OR u.username = :identifier) AND u.is_active = 1";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":identifier", $identifier);
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
                    $show_otp_form = true;
                    $success = "OTP has been sent to your email address";
                } else {
                    $error = "Failed to send OTP. Please try again.";
                }
            } else {
                $error = "Email or username not found or account is inactive";
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
        
        @media (max-width: 576px) {
            .auth-form {
                padding: 30px 20px;
            }
            
            .auth-form h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content container">
            <div class="logo">
                <img src="../assets/images/health-center.jpg" alt="HealthConnect Logo">
                <h1>HealthConnect</h1>
            </div>
        </div>
    </header>
    
    <!-- Login Section -->
    <div class="auth-container">
        <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h2>Login to HealthConnect</h2>
            
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
            
            <?php if(!$show_otp_form): ?>
            <!-- Email/Username Form -->
            <div class="form-group">
                <label for="identifier"><i class="fas fa-user"></i> Email or Username</label>
                <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Enter your email or username" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Send OTP
                </button>
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
            </div>
        </form>
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
</body>
</html> 