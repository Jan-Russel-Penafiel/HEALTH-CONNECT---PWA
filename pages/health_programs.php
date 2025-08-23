<?php
// Start session
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Programs - HealthConnect</title>
    
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
            border-bottom: 3px solid #9C27B0;
            padding-bottom: 10px;
        }
        
        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .program-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border-left: 4px solid #9C27B0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .program-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .program-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #9C27B0, #7B1FA2);
            opacity: 0.1;
            border-radius: 0 12px 0 100%;
        }
        
        .program-card h3 {
            color: #9C27B0;
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .program-card .program-icon {
            font-size: 1.8rem;
            color: #9C27B0;
        }
        
        .program-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .program-features {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .program-features li {
            padding: 5px 0;
            position: relative;
            padding-left: 20px;
            color: #333;
        }
        
        .program-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
        }
        
        .program-schedule {
            background: #e8f5e8;
            border-radius: 6px;
            padding: 10px 15px;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #2e7d32;
        }
        
        .program-schedule strong {
            color: #1b5e20;
        }
        
        .highlight-section {
            background: linear-gradient(135deg, #e1bee7, #ce93d8);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .highlight-section h3 {
            color: #4A148C;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .highlight-section p {
            color: #6A1B9A;
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-top: 4px solid #9C27B0;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #9C27B0;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
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
            color: #9C27B0;
            border-color: white;
        }
        
        .btn-primary:hover {
            background: #9C27B0;
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
            color: #9C27B0;
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
        
        .upcoming-events {
            background: #fff3e0;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .upcoming-events h4 {
            color: #f57c00;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ffe0b2;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-name {
            font-weight: 500;
            color: #333;
        }
        
        .event-date {
            font-size: 0.9rem;
            color: #f57c00;
            background: #fff3e0;
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid #ffe0b2;
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
            
            .programs-grid {
                grid-template-columns: 1fr;
            }
            
            .event-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
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
            <h1><i class="fas fa-heart"></i> Health Programs</h1>
            <p>Comprehensive community health initiatives promoting wellness and preventive care for all residents</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- About Health Programs -->
        <div class="content-section">
            <h2>Community Health Initiatives</h2>
            <p>Brgy. Poblacion Health Center is committed to promoting community wellness through various health programs and initiatives. Our programs focus on prevention, education, and early intervention to ensure the health and well-being of all community members.</p>
            
            <div class="highlight-section">
                <h3>Our Mission</h3>
                <p>To provide accessible, quality healthcare services and promote healthy lifestyles through community-based programs that address the unique health needs of Brgy. Poblacion residents.</p>
            </div>
        </div>

        <!-- Program Statistics -->
        <div class="content-section">
            <h2>Program Impact</h2>
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-number">1,500+</div>
                    <div class="stat-label">Program Participants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">12</div>
                    <div class="stat-label">Active Programs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Emergency Services</div>
                </div>
            </div>
        </div>

        <!-- Health Programs -->
        <div class="content-section">
            <h2>Current Health Programs</h2>
            <div class="programs-grid">
                <div class="program-card">
                    <h3>
                        <i class="fas fa-baby program-icon"></i>
                        Maternal & Child Health
                    </h3>
                    <p class="program-description">Comprehensive prenatal and postnatal care services to ensure healthy mothers and babies.</p>
                    <ul class="program-features">
                        <li>Prenatal consultations</li>
                        <li>Birthing assistance</li>
                        <li>Postnatal check-ups</li>
                        <li>Child growth monitoring</li>
                        <li>Breastfeeding support</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Monday to Friday, 8:00 AM - 5:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-syringe program-icon"></i>
                        Immunization Program
                    </h3>
                    <p class="program-description">Complete vaccination services for all age groups following DOH guidelines.</p>
                    <ul class="program-features">
                        <li>Routine childhood vaccines</li>
                        <li>Adult immunizations</li>
                        <li>Travel vaccines</li>
                        <li>Seasonal flu shots</li>
                        <li>COVID-19 vaccinations</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Tuesday & Thursday, 9:00 AM - 4:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-heartbeat program-icon"></i>
                        Hypertension Control
                    </h3>
                    <p class="program-description">Blood pressure monitoring and management program for cardiovascular health.</p>
                    <ul class="program-features">
                        <li>Regular BP monitoring</li>
                        <li>Medication management</li>
                        <li>Dietary counseling</li>
                        <li>Exercise guidance</li>
                        <li>Lifestyle modification</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Monday, Wednesday, Friday - 8:00 AM - 12:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-tint program-icon"></i>
                        Diabetes Management
                    </h3>
                    <p class="program-description">Comprehensive diabetes care and education program for better glucose control.</p>
                    <ul class="program-features">
                        <li>Blood sugar monitoring</li>
                        <li>Diabetes education</li>
                        <li>Nutritional counseling</li>
                        <li>Foot care screening</li>
                        <li>Medication support</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Tuesday & Friday, 1:00 PM - 5:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-smoking-ban program-icon"></i>
                        Tobacco Cessation
                    </h3>
                    <p class="program-description">Support program to help community members quit smoking and tobacco use.</p>
                    <ul class="program-features">
                        <li>Counseling sessions</li>
                        <li>Nicotine replacement therapy</li>
                        <li>Support groups</li>
                        <li>Relapse prevention</li>
                        <li>Family support</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Wednesday, 2:00 PM - 4:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-brain program-icon"></i>
                        Mental Health Services
                    </h3>
                    <p class="program-description">Mental health awareness and basic counseling services for psychological well-being.</p>
                    <ul class="program-features">
                        <li>Mental health screening</li>
                        <li>Basic counseling</li>
                        <li>Stress management</li>
                        <li>Depression awareness</li>
                        <li>Referral services</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Thursday, 9:00 AM - 12:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-apple-alt program-icon"></i>
                        Nutrition Program
                    </h3>
                    <p class="program-description">Community nutrition education and malnutrition prevention program.</p>
                    <ul class="program-features">
                        <li>Nutrition education</li>
                        <li>Malnutrition screening</li>
                        <li>Growth monitoring</li>
                        <li>Feeding programs</li>
                        <li>Healthy cooking demos</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Saturday, 8:00 AM - 12:00 PM
                    </div>
                </div>
                
                <div class="program-card">
                    <h3>
                        <i class="fas fa-users program-icon"></i>
                        Family Planning
                    </h3>
                    <p class="program-description">Reproductive health services and family planning education for responsible parenthood.</p>
                    <ul class="program-features">
                        <li>Contraceptive counseling</li>
                        <li>Family planning methods</li>
                        <li>Reproductive health education</li>
                        <li>Counseling services</li>
                        <li>STI prevention</li>
                    </ul>
                    <div class="program-schedule">
                        <strong>Schedule:</strong> Monday to Friday, 1:00 PM - 5:00 PM
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="content-section">
            <h2>Upcoming Health Events</h2>
            <div class="upcoming-events">
                <h4><i class="fas fa-calendar-alt"></i> Health Calendar</h4>
                <div class="event-item">
                    <span class="event-name">Free Blood Pressure Screening</span>
                    <span class="event-date">Every Monday</span>
                </div>
                <div class="event-item">
                    <span class="event-name">Diabetes Awareness Seminar</span>
                    <span class="event-date">1st Friday of the Month</span>
                </div>
                <div class="event-item">
                    <span class="event-name">Nutrition Education Workshop</span>
                    <span class="event-date">2nd Saturday of the Month</span>
                </div>
                <div class="event-item">
                    <span class="event-name">Family Planning Counseling</span>
                    <span class="event-date">Every Wednesday</span>
                </div>
                <div class="event-item">
                    <span class="event-name">Mental Health Awareness Day</span>
                    <span class="event-date">Last Thursday of the Month</span>
                </div>
            </div>
            
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> How to Participate</h4>
                <p>Most of our health programs are free for Brgy. Poblacion residents. Simply visit the health center during program hours or schedule an appointment through HealthConnect. Some programs may require pre-registration.</p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Join Our Health Programs Today</h2>
            <p>Take the first step towards better health by participating in our community programs</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <a href="/connect/pages/patient/schedule_appointment.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
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
