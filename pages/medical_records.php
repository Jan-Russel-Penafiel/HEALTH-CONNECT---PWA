<?php
// Start session
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - HealthConnect</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php include '../includes/header_links.php'; ?>
    
    <style>
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
            border-bottom: 3px solid #2196F3;
            padding-bottom: 10px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .feature-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            border-left: 4px solid #2196F3;
            transition: transform 0.2s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card h3 {
            color: #2196F3;
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feature-card ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .feature-card li {
            margin-bottom: 8px;
            color: #555;
        }
        
        .benefits-section {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .benefits-section h3 {
            color: #1976D2;
            margin-bottom: 20px;
            font-size: 1.5rem;
            text-align: center;
        }
        
        .benefits-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .benefit-item i {
            color: #2196F3;
            font-size: 1.2rem;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #2196F3, #1976D2);
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
            color: #2196F3;
            border-color: white;
        }
        
        .btn-primary:hover {
            background: #2196F3;
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
            color: #2196F3;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .info-box h4 {
            color: #f57c00;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .info-box p {
            margin: 0;
            color: #555;
        }
        
        .security-section {
            background: #e8f5e8;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .security-section h4 {
            color: #4CAF50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .security-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .security-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2e7d32;
            font-weight: 500;
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
            <h1><i class="fas fa-file-medical"></i> Digital Medical Records</h1>
            <p>Secure, accessible, and comprehensive digital health records management system</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- About Medical Records -->
        <div class="content-section">
            <h2>Your Health Information, Digitized</h2>
            <p>HealthConnect's digital medical records system provides you with secure access to your complete health history. Our comprehensive system ensures that your medical information is always available when you need it, whether for consultations, referrals, or emergencies.</p>
            
            <div class="info-box">
                <h4><i class="fas fa-shield-alt"></i> Privacy & Security</h4>
                <p>Your medical records are protected with industry-standard encryption and security measures. Only authorized healthcare providers and you have access to your personal health information.</p>
            </div>
        </div>

        <!-- Features -->
        <div class="content-section">
            <h2>What's Included in Your Digital Records</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <h3><i class="fas fa-notes-medical"></i> Medical History</h3>
                    <ul>
                        <li>Past consultations and diagnoses</li>
                        <li>Treatment history</li>
                        <li>Surgical procedures</li>
                        <li>Chronic condition management</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-pills"></i> Medications</h3>
                    <ul>
                        <li>Current prescriptions</li>
                        <li>Medication history</li>
                        <li>Allergies and reactions</li>
                        <li>Dosage instructions</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-vial"></i> Laboratory Results</h3>
                    <ul>
                        <li>Blood test results</li>
                        <li>Diagnostic imaging</li>
                        <li>Pathology reports</li>
                        <li>Trend analysis</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-syringe"></i> Immunization Records</h3>
                    <ul>
                        <li>Vaccination history</li>
                        <li>Immunization schedules</li>
                        <li>Booster reminders</li>
                        <li>Travel vaccines</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
                    <ul>
                        <li>Blood pressure readings</li>
                        <li>Weight tracking</li>
                        <li>Temperature records</li>
                        <li>Health metrics</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h3><i class="fas fa-calendar-alt"></i> Appointment History</h3>
                    <ul>
                        <li>Past appointments</li>
                        <li>Follow-up schedules</li>
                        <li>Provider notes</li>
                        <li>Treatment plans</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Benefits -->
        <div class="benefits-section">
            <h3>Benefits of Digital Medical Records</h3>
            <div class="benefits-list">
                <div class="benefit-item">
                    <i class="fas fa-clock"></i>
                    <span>24/7 Access to Records</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Mobile-Friendly Interface</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-search"></i>
                    <span>Easy Search & Navigation</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-download"></i>
                    <span>Download & Print Records</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-share-alt"></i>
                    <span>Share with Healthcare Providers</span>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Health Progress Tracking</span>
                </div>
            </div>
        </div>

        <!-- Security Features -->
        <div class="content-section">
            <h2>Security & Privacy Features</h2>
            <div class="security-section">
                <h4><i class="fas fa-lock"></i> Your Data is Protected</h4>
                <p>We implement comprehensive security measures to ensure your medical information remains confidential and secure.</p>
                
                <div class="security-features">
                    <div class="security-item">
                        <i class="fas fa-key"></i>
                        <span>End-to-End Encryption</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-user-shield"></i>
                        <span>Access Control</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-history"></i>
                        <span>Audit Trails</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-server"></i>
                        <span>Secure Servers</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-certificate"></i>
                        <span>HIPAA Compliant</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-backup"></i>
                        <span>Regular Backups</span>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Access Control</h4>
                <p>You control who can access your medical records. Healthcare providers at Brgy. Poblacion Health Center have authorized access for treatment purposes, and you can share specific records with other healthcare providers as needed.</p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Access Your Medical Records Today</h2>
            <p>Join HealthConnect to start managing your digital health records securely and conveniently</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <a href="/connect/pages/patient/medical_history.php" class="btn btn-primary">
                            <i class="fas fa-file-medical"></i> View My Records
                        </a>
                        <a href="/connect/pages/patient/dashboard.php" class="btn btn-outline">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
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
                        <i class="fas fa-sign-in-alt"></i> Login to Access Records
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
</body>
</html>
