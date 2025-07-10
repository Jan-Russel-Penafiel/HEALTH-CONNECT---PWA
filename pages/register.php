<?php
// Start session
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'health_worker') {
        header("Location: dashboard.php");
    } else {
        header("Location: patient_dashboard.php");
    }
    exit;
}

// Process the registration form
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Include database connection
    require_once '../includes/config/database.php';
    
    // Get the database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get user input
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $gender = trim($_POST['gender']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $mobile_number = trim($_POST['mobile_number']);
    $address = trim($_POST['address']);
    
    // Validate input
    if (empty($username) || empty($email) || empty($first_name) || empty($last_name) || 
        empty($gender) || empty($date_of_birth)) {
        $error = "Please fill in all required fields";
    } else {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Check if username already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(":username", $username);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Username already exists. Please choose another.";
            } else {
                // Check if email already exists
                $check_query = "SELECT COUNT(*) FROM users WHERE email = :email";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(":email", $email);
                $check_stmt->execute();
                
                if ($check_stmt->fetchColumn() > 0) {
                    $error = "Email already exists. Please use another or login.";
                } else {
                    // Get patient role ID
                    $role_query = "SELECT role_id FROM user_roles WHERE role_name = 'patient'";
                    $role_stmt = $conn->query($role_query);
                    $role_id = $role_stmt->fetchColumn();
                    
                    // Insert user into users table
                    $user_query = "INSERT INTO users (role_id, username, email, mobile_number, first_name, 
                                  middle_name, last_name, gender, date_of_birth, address) 
                                  VALUES (:role_id, :username, :email, :mobile_number, :first_name, 
                                  :middle_name, :last_name, :gender, :date_of_birth, :address)";
                    
                    $user_stmt = $conn->prepare($user_query);
                    $user_stmt->bindParam(":role_id", $role_id);
                    $user_stmt->bindParam(":username", $username);
                    $user_stmt->bindParam(":email", $email);
                    $user_stmt->bindParam(":mobile_number", $mobile_number);
                    $user_stmt->bindParam(":first_name", $first_name);
                    $user_stmt->bindParam(":middle_name", $middle_name);
                    $user_stmt->bindParam(":last_name", $last_name);
                    $user_stmt->bindParam(":gender", $gender);
                    $user_stmt->bindParam(":date_of_birth", $date_of_birth);
                    $user_stmt->bindParam(":address", $address);
                    
                    if ($user_stmt->execute()) {
                        // Get the inserted user ID
                        $user_id = $conn->lastInsertId();
                        
                        // Insert patient record
                        $patient_query = "INSERT INTO patients (user_id) VALUES (:user_id)";
                        $patient_stmt = $conn->prepare($patient_query);
                        $patient_stmt->bindParam(":user_id", $user_id);
                        
                        if ($patient_stmt->execute()) {
                            // Commit the transaction
                            $conn->commit();
                            $success = "Registration successful! Please check your email to login.";
                        } else {
                            // Rollback the transaction
                            $conn->rollBack();
                            $error = "An error occurred during registration. Please try again.";
                        }
                    } else {
                        // Rollback the transaction
                        $conn->rollBack();
                        $error = "An error occurred during registration. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            // Rollback the transaction
            $conn->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for HealthConnect - Brgy. Poblacion Health Center">
    <meta name="theme-color" content="#4CAF50">
    <title>Register - HealthConnect</title>
    
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
        /* Additional styles for enhanced registration UI */
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
            max-width: 600px !important;
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
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group label::after {
            content: ' *';
            color: var(--error);
        }
        
        .form-group label.optional::after {
            content: '';
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
        
        .form-section {
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 25px;
            padding-bottom: 10px;
        }
        
        .form-section-title {
            font-size: 1.1rem;
            color: var(--primary-dark);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-section-title i {
            margin-right: 8px;
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
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
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
    
    <!-- Registration Section -->
    <div class="auth-container">
        <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h2>Create an Account</h2>
            
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
            
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-user"></i> Account Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Your email address" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-id-card"></i> Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Your first name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name" class="optional">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" placeholder="Your middle name (optional)">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Your last name" required>
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
                        <label for="mobile_number">Mobile Number</label>
                        <input type="tel" id="mobile_number" name="mobile_number" class="form-control" placeholder="Your mobile number" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section" style="border-bottom: none; margin-bottom: 15px;">
                <h3 class="form-section-title"><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                <div class="form-group">
                    <label for="address">Complete Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3" placeholder="Your complete address" required></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </div>
            
            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </form>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> HealthConnect. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
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

            <a href="login.php" class="footer-nav-item">
                <i class="fas fa-user"></i>
                <span>Login</span>
            </a>

            <a href="register.php" class="footer-nav-item active">
                <i class="fas fa-user-plus"></i>
                <span>Register</span>
            </a>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="../assets/js/app.js"></script>
</body>
</html> 