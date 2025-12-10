<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_health_worker = isset($_SESSION['role']) && $_SESSION['role'] === 'health_worker';
?>

<nav class="top-navbar">
    <div class="container">
        <a href="/connect/" class="navbar-brand">
            <img src="/connect/assets/images/health-center.jpg" alt="HealthConnect">
            HealthConnect
        </a>
        
        <!-- Desktop Navigation -->
        <div class="desktop-nav">
            <?php if ($is_admin): ?>
            <nav class="main-nav">
                <a href="/connect/pages/admin/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/connect/pages/admin/health_workers.php" class="nav-link <?php echo $current_page === 'health_workers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-md"></i>
                    <span>Workers</span>
                </a>
                <a href="/connect/pages/admin/patients.php" class="nav-link <?php echo $current_page === 'patients.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="/connect/pages/admin/medical_records.php" class="nav-link <?php echo $current_page === 'medical_records.php' ? 'active' : ''; ?>">
                    <i class="fas fa-notes-medical"></i>
                    <span>Records</span>
                </a>
                <a href="/connect/pages/admin/reports.php" class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
                <a href="/connect/pages/admin/settings.php" class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <?php endif; ?>

            <?php if ($is_health_worker): ?>
            <nav class="main-nav">
                <a href="/connect/pages/health_worker/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/connect/pages/health_worker/patients.php" class="nav-link <?php echo $current_page === 'patients.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="/connect/pages/health_worker/appointments.php" class="nav-link <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="/connect/pages/health_worker/immunization.php" class="nav-link <?php echo $current_page === 'immunization.php' ? 'active' : ''; ?>">
                    <i class="fas fa-syringe"></i>
                    <span>Immunization</span>
                </a>
            </nav>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'): ?>
            <nav class="main-nav">
                <a href="/connect/pages/patient/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/connect/pages/patient/appointments.php" class="nav-link <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
                <a href="/connect/pages/patient/medical_history.php" class="nav-link <?php echo $current_page === 'medical_history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-notes-medical"></i>
                    <span>Records</span>
                </a>
                <a href="/connect/pages/patient/immunization.php" class="nav-link <?php echo $current_page === 'immunization.php' ? 'active' : ''; ?>">
                    <i class="fas fa-syringe"></i>
                    <span>Immunization</span>
                </a>
            </nav>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <button class="user-menu-toggle">
                <i class="fas fa-user-circle"></i>
                <?php 
                if ($is_admin) {
                    echo 'System Admin';
                } elseif ($is_health_worker) {
                    echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Health Worker';
                } else {
                    echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Patient';
                }
                ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-menu-dropdown">
                <?php if ($is_admin): ?>
                <a href="/connect/pages/admin/profile.php" class="user-menu-item">
                    <i class="fas fa-user-circle"></i>
                    Profile
                </a>
                <?php elseif ($is_health_worker): ?>
                <a href="/connect/pages/health_worker/profile.php" class="user-menu-item">
                    <i class="fas fa-user-circle"></i>
                    Profile
                </a>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'): ?>
                <a href="/connect/pages/patient/profile.php" class="user-menu-item">
                    <i class="fas fa-user-circle"></i>
                    Profile
                </a>
                <?php endif; ?>
                <a href="/connect/pages/logout.php" class="user-menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<?php if ($is_admin): ?>
<nav class="footer-nav">
    <a href="/connect/pages/admin/dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
    </a>
    <a href="/connect/pages/admin/health_workers.php" class="nav-item <?php echo $current_page === 'health_workers.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-md"></i>
        <span>Workers</span>
    </a>
    <a href="/connect/pages/admin/patients.php" class="nav-item <?php echo $current_page === 'patients.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Patients</span>
    </a>
    <a href="/connect/pages/admin/medical_records.php" class="nav-item <?php echo $current_page === 'medical_records.php' ? 'active' : ''; ?>">
        <i class="fas fa-notes-medical"></i>
        <span>Records</span>
    </a>
    <a href="/connect/pages/admin/settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
    </a>
</nav>
<?php endif; ?>

<?php if ($is_health_worker): ?>
<nav class="footer-nav">
    <a href="/connect/pages/health_worker/dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
    </a>
    <a href="/connect/pages/health_worker/patients.php" class="nav-item <?php echo $current_page === 'patients.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Patients</span>
    </a>
    <a href="/connect/pages/health_worker/appointments.php" class="nav-item <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i>
        <span>Appointments</span>
    </a>
    <a href="/connect/pages/health_worker/immunization.php" class="nav-item <?php echo $current_page === 'immunization.php' ? 'active' : ''; ?>">
        <i class="fas fa-syringe"></i>
        <span>Immunization</span>
    </a>
</nav>
<?php endif; ?>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient'): ?>
<nav class="footer-nav">
    <a href="/connect/pages/patient/dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
    </a>
    <a href="/connect/pages/patient/appointments.php" class="nav-item <?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i>
        <span>Appointments</span>
    </a>
    <a href="/connect/pages/patient/medical_history.php" class="nav-item <?php echo $current_page === 'medical_history.php' ? 'active' : ''; ?>">
        <i class="fas fa-notes-medical"></i>
        <span>Records</span>
    </a>
    <a href="/connect/pages/patient/immunization.php" class="nav-item <?php echo $current_page === 'immunization.php' ? 'active' : ''; ?>">
        <i class="fas fa-syringe"></i>
        <span>Immunization</span>
    </a>
</nav>
<?php endif; ?>

<style>
.top-navbar {
    background: #fff;
    padding: 0.5rem 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    height: 48px;
    width: 100vw;
}

.top-navbar .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 100%;
    max-width: 100vw;
    width: 100vw;
    padding: 0 20px;
    margin: 0;
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

/* Mobile Footer Navigation */
.footer-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    display: flex;
    justify-content: space-around;
    padding: 0.4rem 0.25rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    height: 56px;
}

.footer-nav .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #666;
    font-size: 0.7rem;
    padding: 0.35rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.footer-nav .nav-item i {
    font-size: 1.1rem;
    margin-bottom: 2px;
}

.footer-nav .nav-item span {
    font-size: 0.65rem;
    font-weight: 500;
}

.footer-nav .nav-item.active {
    color: #4CAF50;
    background: rgba(76, 175, 80, 0.1);
}

.footer-nav .nav-item:hover {
    color: #4CAF50;
    background: rgba(76, 175, 80, 0.05);
}

.user-menu {
    position: relative;
}

.user-menu-toggle {
    background: none;
    border: none;
    padding: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: #333;
    cursor: pointer;
    font-size: 0.85rem;
}

.user-menu-toggle i {
    font-size: 1rem;
}

.user-menu-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 5px);
    right: 0;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    padding: 0.5rem;
    min-width: 200px;
    z-index: 1001;
    border: 1px solid #e0e0e0;
}

.user-menu.active .user-menu-dropdown {
    display: block;
}

.user-menu-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    color: #333;
    text-decoration: none;
    border-radius: 6px;
    transition: background-color 0.2s ease;
    background-color: #fff;
    margin-bottom: 2px;
}

.user-menu-item:hover {
    background-color: #f0f0f0;
}

/* Add padding to main content to account for fixed header and footer */
body {
    padding-top: 48px;
    padding-bottom: 56px;
}

/* Desktop Layout */
@media (min-width: 769px) {
    .desktop-nav {
        display: block;
    }
    
    .footer-nav {
        display: none;
    }
    
    body {
        padding-bottom: 0;
    }
    
    .top-navbar {
        height: 56px;
        width: 100vw;
    }
    
    .top-navbar .container {
        padding: 0 20px;
        max-width: 100vw;
        width: 100vw;
    }
    
    body {
        padding-top: 56px;
    }
}

/* Mobile Layout */
@media (max-width: 768px) {
    .navbar-brand span {
        display: none;
    }
    
    .user-menu-toggle span {
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .desktop-nav {
        display: none;
    }
    
    .footer-nav {
        display: flex;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userMenuToggle = document.querySelector('.user-menu-toggle');
    const userMenu = document.querySelector('.user-menu');
    
    if (userMenuToggle) {
        userMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
            
            // Position check for mobile - ensure dropdown stays in viewport
            const dropdown = document.querySelector('.user-menu-dropdown');
            if (dropdown) {
                const rect = dropdown.getBoundingClientRect();
                if (rect.right > window.innerWidth) {
                    dropdown.style.right = '0';
                    dropdown.style.left = 'auto';
                }
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });
    }
});</script> 