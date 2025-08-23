<?php
// Start session
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - HealthConnect</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php include '../includes/header_links.php'; ?>
    
    <style>
        /* Additional styles for enhanced UI */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding-top: 60px;
            padding-bottom: 70px;
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

        .auth-link.register-btn {
            background: #4CAF50;
            color: white;
        }

        .auth-link.register-btn:hover {
            background: #388E3C;
            color: white;
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
            color: #666;
            font-size: 0.8rem;
            padding: 5px 0;
            transition: all 0.3s ease;
        }
        
        .footer-nav-item i {
            font-size: 1.4rem;
            margin-bottom: 3px;
        }
        
        .footer-nav-item.active {
            color: #4CAF50;
        }
        
        .footer-nav-item:hover {
            color: #4CAF50;
            transform: translateY(-3px);
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: #fff;
            color: #333;
            padding: 60px 0;
            margin-bottom: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
        }
        
        .page-header p {
            margin: 10px auto 0;
            font-size: 1.2rem;
            text-align: center;
            max-width: 600px;
            opacity: 0.9;
        }
        
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .content-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        
        .appointment-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .appointment-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            border-left: 4px solid #4CAF50;
            transition: transform 0.2s ease;
        }
        
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .appointment-card h3 {
            color: #4CAF50;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .appointment-card ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .appointment-card li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin-top: 40px;
        }
        
        .cta-section h2 {
            margin-bottom: 20px;
            font-size: 2rem;
            border: none;
            padding: 0;
        }
        
        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .btn-primary {
            background: white;
            color: #4CAF50;
            border-color: white;
        }
        
        .btn-primary:hover {
            background: #4CAF50;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border-color: white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #4CAF50;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .info-box h4 {
            color: #1976D2;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .info-box p {
            margin: 0;
            color: #555;
        }
        
        .schedule-info {
            background: #f1f8e9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .schedule-info h4 {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .schedule-table th {
            background: #4CAF50;
            color: white;
            font-weight: 600;
        }
        
        .schedule-table tr:hover {
            background: #f5f5f5;
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .content-section {
                padding: 20px;
            }
            
            .cta-section {
                padding: 30px 20px;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php include '../includes/navbar.php'; ?>
    <?php else: ?>
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
                    <a href="/connect/pages/login.php" class="auth-link">
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
        
        <!-- Footer Navigation for Mobile -->
        <div class="footer-nav">
            <div class="footer-nav-container">
                <a href="/connect/index.php#home" class="footer-nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="/connect/index.php#features" class="footer-nav-item">
                    <i class="fas fa-list-ul"></i>
                    <span>Features</span>
                </a>
                <a href="/connect/index.php#about" class="footer-nav-item">
                    <i class="fas fa-info-circle"></i>
                    <span>About</span>
                </a>
                <a href="/connect/index.php#contact" class="footer-nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Contact</span>
                </a>
                <a href="/connect/pages/login.php" class="footer-nav-item">
                    <i class="fas fa-user"></i>
                    <span>Login</span>
                </a>
                <a href="/connect/pages/register.php" class="footer-nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-calendar-check"></i> Appointment Services</h1>
            <p>Schedule your health center visits conveniently online with our appointment booking system</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- About Appointments -->
        <div class="content-section">
            <h2>Online Appointment Booking</h2>
            <p>HealthConnect makes it easy to schedule your visits to Brgy. Poblacion Health Center. Our online appointment system allows you to book consultations, follow-ups, and health services at your convenience.</p>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> How It Works</h4>
                <p>Simply register as a patient, log in to your account, and select your preferred date and time for your health center visit. You'll receive SMS confirmations and reminders about your appointment.</p>
            </div>
        </div>

        <!-- Appointment Types -->
        <div class="content-section">
            <h2>Available Services</h2>
            <div class="appointment-types">
                <div class="appointment-card">
                    <h3><i class="fas fa-stethoscope"></i> General Consultation</h3>
                    <ul>
                        <li>Health check-ups</li>
                        <li>Medical consultation</li>
                        <li>Prescription renewal</li>
                        <li>Health advice and counseling</li>
                    </ul>
                </div>
                
                <div class="appointment-card">
                    <h3><i class="fas fa-baby"></i> Maternal & Child Health</h3>
                    <ul>
                        <li>Prenatal care</li>
                        <li>Postnatal check-ups</li>
                        <li>Child growth monitoring</li>
                        <li>Family planning services</li>
                    </ul>
                </div>
                
                <div class="appointment-card">
                    <h3><i class="fas fa-syringe"></i> Immunization</h3>
                    <ul>
                        <li>Childhood vaccines</li>
                        <li>Adult immunizations</li>
                        <li>Travel vaccines</li>
                        <li>Flu shots</li>
                    </ul>
                </div>
                
                <div class="appointment-card">
                    <h3><i class="fas fa-heartbeat"></i> Specialized Services</h3>
                    <ul>
                        <li>Blood pressure monitoring</li>
                        <li>Diabetes screening</li>
                        <li>Health education programs</li>
                        <li>Community health services</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Schedule Information -->
        <div class="content-section">
            <h2>Health Center Schedule</h2>
            <div class="schedule-info">
                <h4><i class="fas fa-clock"></i> Operating Hours</h4>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Morning</th>
                            <th>Afternoon</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Monday - Friday</td>
                            <td>8:00 AM - 12:00 PM</td>
                            <td>1:00 PM - 5:00 PM</td>
                        </tr>
                        <tr>
                            <td>Saturday</td>
                            <td>8:00 AM - 12:00 PM</td>
                            <td>Closed</td>
                        </tr>
                        <tr>
                            <td>Sunday</td>
                            <td colspan="2">Closed (Emergency services available)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                <p>Please arrive 15 minutes before your scheduled appointment time. Bring a valid ID and any relevant medical documents. Emergency services are available 24/7 for urgent medical needs.</p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Ready to Book Your Appointment?</h2>
            <p>Join HealthConnect today and experience convenient healthcare scheduling</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <a href="/connect/pages/patient/schedule_appointment.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </a>
                        <a href="/connect/pages/patient/appointments.php" class="btn btn-outline">
                            <i class="fas fa-list"></i> View My Appointments
                        </a>
                    <?php else: ?>
                        <a href="/connect/pages/<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/connect/pages/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                    <a href="/connect/pages/login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
</body>
</html>
