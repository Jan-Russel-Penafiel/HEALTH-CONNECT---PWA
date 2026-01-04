<?php
// Start session
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="HealthConnect - Smart Mobile Application For Brgy. Health Center of Brgy. Poblacion President Quirino Sultan Kudarat">
    <meta name="theme-color" content="#4CAF50">
    <title>HealthConnect - Brgy. Poblacion Health Center</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HealthConnect">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
    <link rel="mask-icon" href="assets/images/safari-pinned-tab.svg" color="#4CAF50">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Service Worker Update Check -->
    <script>
        // Check if service worker is registered when page loads
        window.addEventListener('load', function() {
            if ('serviceWorker' in navigator) {
                // Force check for updates on page load
                navigator.serviceWorker.ready.then(registration => {
                    registration.update();
                    console.log('[PWA] Checking for service worker updates on page load');
                });
            }
        });
    </script>
    
    <style>
        /* Hide any update/install prompts or banners */
        .update-notification,
        .update-banner,
        .version-banner,
        .install-prompt,
        .pwa-install,
        .install-banner,
        [class*="update-"],
        [class*="install-"],
        [class*="version-"],
        [id*="update"],
        [id*="install"],
        [id*="version"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        
        /* Additional styles for enhanced UI */
        body {
            font-family: 'Poppins', sans-serif;
            padding-bottom: 70px; /* Add space for footer nav */
            padding-top: 60px; /* Match header height */
        }
        
        /* Alert animations */
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-20px);
                opacity: 0;
            }
        }
        
        .alert-container {
            position: relative;
            margin-bottom: 20px;
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
        
        .nav-menu a {
            font-weight: 500;
            position: relative;
        }
        
        .nav-menu a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .nav-menu a:hover::after, .nav-menu a.active::after {
            width: 80%;
            left: 10%;
        }
        
        .hero {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.5)), url('assets/images/health-center.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
            padding: 40px 0 60px 0;
            min-height: 70vh;
            display: flex;
            align-items: center;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to top, rgba(245, 245, 245, 1), rgba(245, 245, 245, 0));
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 0 20px;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 35px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .btn {
            padding: 14px 28px;
            font-weight: 600;
            letter-spacing: 1.2px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .feature-card {
            border-radius: 12px;
            padding: 35px 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            background-color: white;
            transition: all 0.4s ease;
            border-bottom: 4px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-15px);
            border-bottom: 4px solid var(--primary-color);
        }
        
        .feature-icon {
            font-size: 3.5rem;
            margin-bottom: 25px;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        .feature-card h3 {
            font-size: 1.6rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .about-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .about-image {
            flex: 1;
            min-height: 400px;
        }
        
        .about-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .about-text {
            flex: 1;
            padding: 40px;
        }
        
        .about-text h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--primary-dark);
            position: relative;
            padding-bottom: 15px;
        }
        
        .about-text h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background-color: var(--primary-color);
        }
        
        .contact-form {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .footer-content {
            padding-top: 60px;
            padding-bottom: 30px;
        }
        
        .footer-social a {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .footer-social a:hover {
            background-color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .pwa-install {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 30px;
            padding: 12px 24px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            cursor: pointer;
            transition: all 0.3s ease;
            transform: translateX(-100px);
            opacity: 0;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .pwa-install.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .pwa-install {
                bottom: 80px;
                left: 15px;
                padding: 10px 20px;
                font-size: 14px;
            }
        }
        
        .pwa-install i {
            margin-right: 8px;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 15px;
            display: flex;
            align-items: center;
            min-width: 300px;
            max-width: 400px;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast-success {
            border-left: 4px solid #4CAF50;
        }
        
        .toast-error {
            border-left: 4px solid #F44336;
        }
        
        .toast-info {
            border-left: 4px solid #2196F3;
        }
        
        .toast-warning {
            border-left: 4px solid #FF9800;
        }
        
        .toast-icon {
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .toast-success .toast-icon {
            color: #4CAF50;
        }
        
        .toast-error .toast-icon {
            color: #F44336;
        }
        
        .toast-info .toast-icon {
            color: #2196F3;
        }
        
        .toast-warning .toast-icon {
            color: #FF9800;
        }
        
        .toast-content {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .toast-close {
            margin-left: 10px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .toast-close:hover {
            opacity: 1;
        }
        
        .toast-action {
            margin-left: 10px;
            margin-right: 10px;
        }
        
        .btn-refresh {
            background-color: #fff;
            color: #4CAF50;
            border: 1px solid #4CAF50;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-refresh:hover {
            background-color: #4CAF50;
            color: #fff;
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
                    <a href="/connect/index.php#home" class="nav-link active">
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
                <a href="#" class="auth-link" onclick="showRoleSelection()">
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
    
    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Your Health, Our Priority</h1>
            <p>HealthConnect connects you to Brgy. Poblacion Health Center services, making healthcare accessible right from your mobile device. Schedule appointments, track immunizations, and access your medical records with ease.</p>
            <div class="hero-buttons">
                <a href="pages/register.php" class="btn">Register Now</a>
                <a href="#" class="btn btn-outline" onclick="showRoleSelection()">Login</a>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title">
                <h2>Our Features</h2>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Online Appointments</h3>
                    <p>Schedule your health center visits online and receive SMS confirmations and reminders.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3>Digital Medical Records</h3>
                    <p>Access your medical history, prescriptions, and treatment information securely from anywhere.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <h3>Immunization Tracking</h3>
                    <p>Keep track of your family's immunization schedules and receive timely notifications.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>SMS Notifications</h3>
                    <p>Get timely reminders about your appointments, medication schedule, and health center programs.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Health Dashboard</h3>
                    <p>View insights about your health data through an easy-to-understand visual dashboard.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Friendly</h3>
                    <p>Access all features from your smartphone with our Progressive Web App that works offline.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-image">
                    <img src="assets/images/health-center.jpg" alt="Barangay Health Center">
                </div>
                <div class="about-text">
                    <h2>About HealthConnect</h2>
                    <p>HealthConnect is a smart mobile application designed specifically for the Barangay Health Center of Brgy. Poblacion, President Quirino, Sultan Kudarat. Our mission is to bring healthcare services closer to the community through digital innovation.</p>
                    <p>With HealthConnect, both healthcare workers and patients benefit from streamlined processes, better record management, and improved communication. Our application helps reduce wait times, enhances record accuracy, and ensures timely healthcare interventions.</p>
                    <a href="#contact" class="btn">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="section-title">
                <h2>Get In Touch</h2>
            </div>
            
            <div class="alert-container"></div>
            
            <form class="contact-form" id="contactForm">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>HealthConnect</h3>
                    <p>Smart Mobile Application For Brgy. Health Center of Brgy. Poblacion President Quirino Sultan Kudarat</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Services</h3>
                    <ul class="footer-links">
                        <li><a href="pages/appointments.php">Appointments</a></li>
                        <li><a href="pages/medical_records.php">Medical Records</a></li>
                        <li><a href="pages/immunization.php">Immunization</a></li>
                        <li><a href="pages/health_programs.php">Health Programs</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> Brgy. Poblacion, President Quirino, Sultan Kudarat</li>
                        <li><i class="fas fa-phone"></i> +63 999 123 4567</li>
                        <li><i class="fas fa-envelope"></i> info@healthconnect.ph</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> HealthConnect. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Footer Navigation -->
    <div class="footer-nav">
        <div class="footer-nav-container">
            <a href="index.php#home" class="footer-nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="index.php#features" class="footer-nav-item">
                <i class="fas fa-list-ul"></i>
                <span>Features</span>
            </a>
            <a href="index.php#about" class="footer-nav-item">
                <i class="fas fa-info-circle"></i>
                <span>About</span>
            </a>
            <a href="index.php#contact" class="footer-nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="pages/login.php" class="footer-nav-item">
                <i class="fas fa-user"></i>
                <span>Login</span>
            </a>
            <a href="pages/register.php" class="footer-nav-item">
                <i class="fas fa-user-plus"></i>
                <span>Register</span>
            </a>
        </div>
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
                <a href="pages/login.php?role=admin" class="role-card admin">
                    <i class="fas fa-user-shield"></i>
                    <h3>Administrator</h3>
                    <p>Manage the health center system, users, and reports</p>
                </a>
                <a href="pages/login.php?role=health_worker" class="role-card health-worker">
                    <i class="fas fa-user-md"></i>
                    <h3>Health Worker</h3>
                    <p>Manage appointments, patient records, and immunizations</p>
                </a>
                <a href="pages/login.php?role=patient" class="role-card patient">
                    <i class="fas fa-user"></i>
                    <h3>Patient</h3>
                    <p>Book appointments, view medical records, and track health</p>
                </a>
            </div>
        </div>
    </div>

    <!-- PWA Install Button -->
    <div class="pwa-install">
        <i class="fas fa-download"></i> Install App
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    
    <script>
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

        // Contact form submission handling with AJAX
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disable submit button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Get form data
            const formData = new FormData(form);
            
            // Send AJAX request
            fetch('send_contact_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Get response text first for debugging
                return response.text().then(text => {
                    console.log('Raw response:', text); // Debug log
                    
                    // Check if it's JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response text:', text);
                        throw new Error('Server response is not valid JSON');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    form.reset();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred while sending your message. Please try again.', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
        
        // Function to show alerts
        function showAlert(message, type) {
            const alertContainer = document.querySelector('.alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.style.padding = '15px';
            alert.style.marginBottom = '20px';
            alert.style.borderRadius = '8px';
            alert.style.fontWeight = '500';
            alert.style.animation = 'slideIn 0.3s ease';
            
            if (type === 'success') {
                alert.style.backgroundColor = '#d4edda';
                alert.style.color = '#155724';
                alert.style.borderLeft = '4px solid #28a745';
            } else if (type === 'error') {
                alert.style.backgroundColor = '#f8d7da';
                alert.style.color = '#721c24';
                alert.style.borderLeft = '4px solid #dc3545';
            }
            
            alert.textContent = message;
            
            alertContainer.appendChild(alert);
            
            // Remove alert after 5 seconds
            setTimeout(function() {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
        
        // Smooth scrolling for footer navigation
        document.querySelectorAll('.footer-nav-item[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // Smooth scroll to target
                    window.scrollTo({
                        top: targetElement.offsetTop - 60, // Adjust for header height
                        behavior: 'smooth'
                    });
                    
                    // Update active state
                    document.querySelectorAll('.footer-nav-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });
        
        // Highlight active footer nav item based on scroll position
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section');
            const footerNavItems = document.querySelectorAll('.footer-nav-item');
            
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.pageYOffset >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });
            
            footerNavItems.forEach(item => {
                item.classList.remove('active');
                const href = item.getAttribute('href');
                if (href === '#home' && current === '') {
                    item.classList.add('active');
                } else if (href === `#${current}`) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html> 