<?php
// Start session
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Services - HealthConnect</title>
    
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
            border-bottom: 3px solid #FF5722;
            padding-bottom: 10px;
        }
        
        .vaccine-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .vaccine-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            border-left: 4px solid #FF5722;
            transition: transform 0.2s ease;
        }
        
        .vaccine-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .vaccine-card h3 {
            color: #FF5722;
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vaccine-list {
            margin: 15px 0;
        }
        
        .vaccine-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .vaccine-item:last-child {
            border-bottom: none;
        }
        
        .vaccine-name {
            font-weight: 500;
            color: #333;
        }
        
        .vaccine-age {
            font-size: 0.9rem;
            color: #666;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
        }
        
        .schedule-section {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .schedule-section h3 {
            color: #f57c00;
            margin-bottom: 20px;
            font-size: 1.5rem;
            text-align: center;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .schedule-table th {
            background: #FF5722;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .schedule-table tr:hover {
            background: #f8f9fa;
        }
        
        .schedule-table td {
            font-size: 0.85rem;
        }
        
        .age-column {
            font-weight: 600;
            color: #FF5722;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #FF5722, #D84315);
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
            color: #FF5722;
            border-color: white;
        }
        
        .btn-primary:hover {
            background: #FF5722;
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
            color: #FF5722;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #e8f5e8;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .info-box h4 {
            color: #4CAF50;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .info-box p {
            margin: 0;
            color: #555;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .warning-box h4 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .warning-box p {
            margin: 0;
            color: #856404;
        }
        
        .travel-vaccines {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .travel-vaccines h4 {
            color: #1976D2;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .travel-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .travel-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1976D2;
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
            
            .vaccine-categories {
                grid-template-columns: 1fr;
            }
            
            .schedule-table {
                font-size: 0.8rem;
            }
            
            .schedule-table th,
            .schedule-table td {
                padding: 8px 10px;
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
            <h1><i class="fas fa-syringe"></i> Immunization Services</h1>
            <p>Protecting your health through comprehensive vaccination programs for all ages</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- About Immunization -->
        <div class="content-section">
            <h2>Comprehensive Immunization Services</h2>
            <p>Brgy. Poblacion Health Center provides complete immunization services for infants, children, adolescents, and adults. Our vaccination programs follow the Department of Health guidelines to ensure optimal protection against preventable diseases.</p>
            
            <div class="info-box">
                <h4><i class="fas fa-shield-alt"></i> Why Vaccination Matters</h4>
                <p>Vaccines are one of the most effective ways to prevent serious diseases and protect not just individuals, but entire communities through herd immunity. Our immunization program helps protect you and your loved ones from vaccine-preventable diseases.</p>
            </div>
        </div>

        <!-- Vaccine Categories -->
        <div class="content-section">
            <h2>Available Vaccines by Category</h2>
            <div class="vaccine-categories">
                <div class="vaccine-card">
                    <h3><i class="fas fa-baby"></i> Infant Vaccines (0-12 months)</h3>
                    <div class="vaccine-list">
                        <div class="vaccine-item">
                            <span class="vaccine-name">BCG</span>
                            <span class="vaccine-age">Birth</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Hepatitis B</span>
                            <span class="vaccine-age">Birth, 6 weeks, 14 weeks</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Pentavalent (DPT-HepB-Hib)</span>
                            <span class="vaccine-age">6, 10, 14 weeks</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Oral Polio Vaccine (OPV)</span>
                            <span class="vaccine-age">6, 10, 14 weeks</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Pneumococcal (PCV)</span>
                            <span class="vaccine-age">6, 10, 14 weeks</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Measles-Rubella (MR)</span>
                            <span class="vaccine-age">9 months</span>
                        </div>
                    </div>
                </div>
                
                <div class="vaccine-card">
                    <h3><i class="fas fa-child"></i> Childhood Vaccines (1-18 years)</h3>
                    <div class="vaccine-list">
                        <div class="vaccine-item">
                            <span class="vaccine-name">MMR (Measles-Mumps-Rubella)</span>
                            <span class="vaccine-age">12-15 months</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">DPT Booster</span>
                            <span class="vaccine-age">18 months</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">OPV Booster</span>
                            <span class="vaccine-age">18 months</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Tetanus Toxoid (TT)</span>
                            <span class="vaccine-age">School age</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Human Papillomavirus (HPV)</span>
                            <span class="vaccine-age">9-14 years (girls)</span>
                        </div>
                    </div>
                </div>
                
                <div class="vaccine-card">
                    <h3><i class="fas fa-user"></i> Adult Vaccines (18+ years)</h3>
                    <div class="vaccine-list">
                        <div class="vaccine-item">
                            <span class="vaccine-name">Tetanus-Diphtheria (Td)</span>
                            <span class="vaccine-age">Every 10 years</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Influenza</span>
                            <span class="vaccine-age">Annual</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Pneumococcal</span>
                            <span class="vaccine-age">65+ years</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Hepatitis B</span>
                            <span class="vaccine-age">High-risk adults</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">COVID-19</span>
                            <span class="vaccine-age">All adults</span>
                        </div>
                    </div>
                </div>
                
                <div class="vaccine-card">
                    <h3><i class="fas fa-female"></i> Maternal Vaccines</h3>
                    <div class="vaccine-list">
                        <div class="vaccine-item">
                            <span class="vaccine-name">Tetanus Toxoid (TT1)</span>
                            <span class="vaccine-age">Early pregnancy</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Tetanus Toxoid (TT2)</span>
                            <span class="vaccine-age">4 weeks after TT1</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Tetanus Toxoid (TT3-5)</span>
                            <span class="vaccine-age">Subsequent pregnancies</span>
                        </div>
                        <div class="vaccine-item">
                            <span class="vaccine-name">Influenza</span>
                            <span class="vaccine-age">During flu season</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Immunization Schedule -->
        <div class="schedule-section">
            <h3><i class="fas fa-calendar-alt"></i> Routine Immunization Schedule</h3>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Age</th>
                        <th>Vaccine</th>
                        <th>Protects Against</th>
                        <th>Doses</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="age-column">Birth</td>
                        <td>BCG, Hepatitis B</td>
                        <td>Tuberculosis, Hepatitis B</td>
                        <td>1 each</td>
                    </tr>
                    <tr>
                        <td class="age-column">6 weeks</td>
                        <td>Pentavalent, OPV, PCV</td>
                        <td>Diphtheria, Pertussis, Tetanus, Hepatitis B, Hib, Polio, Pneumonia</td>
                        <td>1st dose each</td>
                    </tr>
                    <tr>
                        <td class="age-column">10 weeks</td>
                        <td>Pentavalent, OPV, PCV</td>
                        <td>Same as above</td>
                        <td>2nd dose each</td>
                    </tr>
                    <tr>
                        <td class="age-column">14 weeks</td>
                        <td>Pentavalent, OPV, PCV</td>
                        <td>Same as above</td>
                        <td>3rd dose each</td>
                    </tr>
                    <tr>
                        <td class="age-column">9 months</td>
                        <td>Measles-Rubella (MR)</td>
                        <td>Measles, Rubella</td>
                        <td>1st dose</td>
                    </tr>
                    <tr>
                        <td class="age-column">12 months</td>
                        <td>MMR</td>
                        <td>Measles, Mumps, Rubella</td>
                        <td>1st dose</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Travel Vaccines -->
        <div class="content-section">
            <h2>Travel & Special Vaccines</h2>
            <div class="travel-vaccines">
                <h4><i class="fas fa-plane"></i> Travel Vaccination Services</h4>
                <p>Planning to travel? We provide travel health consultations and specialized vaccines for international destinations.</p>
                
                <div class="travel-list">
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Yellow Fever</span>
                    </div>
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Japanese Encephalitis</span>
                    </div>
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Typhoid</span>
                    </div>
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Meningococcal</span>
                    </div>
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Hepatitis A</span>
                    </div>
                    <div class="travel-item">
                        <i class="fas fa-syringe"></i>
                        <span>Rabies (Pre-exposure)</span>
                    </div>
                </div>
            </div>
            
            <div class="warning-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Important Travel Advisory</h4>
                <p>Travel vaccines should be administered at least 2-4 weeks before departure. Schedule a travel health consultation to determine which vaccines you need based on your destination and travel plans.</p>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="cta-section">
            <h2>Stay Protected with Immunizations</h2>
            <p>Schedule your vaccination appointment today and protect yourself and your community</p>
            <div class="cta-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <a href="/connect/pages/patient/immunization.php" class="btn btn-primary">
                            <i class="fas fa-syringe"></i> View My Immunizations
                        </a>
                        <a href="/connect/pages/patient/schedule_appointment.php" class="btn btn-outline">
                            <i class="fas fa-calendar-plus"></i> Schedule Vaccination
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
