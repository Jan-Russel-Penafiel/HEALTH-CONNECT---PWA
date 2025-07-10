// HealthConnect - Main JavaScript File

// Wait for the DOM to load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize mobile navigation
    initMobileNav();
    
    // Initialize PWA functionality
    initPWA();
    
    // Initialize any charts on dashboard
    if (document.querySelector('#appointmentsChart')) {
        initDashboardCharts();
    }
    
    // Add smooth scroll for anchor links
    initSmoothScroll();
    
    // Add form input animations
    initFormAnimations();
    
    // Add scroll animations
    initScrollAnimations();
    
    // Initialize footer navigation
    initFooterNav();
});

// Mobile navigation toggle with improved animation
function initMobileNav() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            
            // Animate the hamburger icon to X
            this.classList.toggle('active');
            if (this.classList.contains('active')) {
                this.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                this.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.nav-toggle') && !event.target.closest('.nav-menu')) {
                navMenu.classList.remove('active');
                if (navToggle.classList.contains('active')) {
                    navToggle.classList.remove('active');
                    navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }
        });
        
        // Close menu when clicking on a link (for single page navigation)
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Only close if it's a mobile view
                if (window.innerWidth <= 768) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                    navToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });
    }
}

// Footer navigation functionality
function initFooterNav() {
    const footerNavItems = document.querySelectorAll('.footer-nav-item');
    
    // Handle click on footer nav items
    footerNavItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Only handle anchor links on the same page
            if (href.includes('#') && !href.startsWith('http') && window.location.pathname === '/' + href.split('#')[0]) {
                e.preventDefault();
                
                const targetId = href.split('#')[1];
                if (!targetId) return;
                
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    // Scroll to the target element
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Update active state
                    footerNavItems.forEach(navItem => {
                        navItem.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            }
        });
    });
    
    // Update active state based on scroll position
    if (window.location.pathname === '/index.php' || window.location.pathname === '/') {
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            if (sections.length === 0) return;
            
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });
            
            footerNavItems.forEach(item => {
                item.classList.remove('active');
                const href = item.getAttribute('href');
                if (href === '#' && current === '') {
                    item.classList.add('active');
                } else if (href && href.includes('#') && href.split('#')[1] === current) {
                    item.classList.add('active');
                }
            });
        });
    }
}

// Smooth scroll for anchor links
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            
            // Skip if it's just "#" or not an anchor link
            if (targetId === '#' || !targetId.startsWith('#')) return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                
                window.scrollTo({
                    top: targetElement.offsetTop - 80, // Offset for fixed header
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Form input animations
function initFormAnimations() {
    const formControls = document.querySelectorAll('.form-control');
    
    formControls.forEach(input => {
        // Add focus effect
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        // Remove focus effect
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
        
        // Check if input has value on load
        if (input.value.trim() !== '') {
            input.parentElement.classList.add('has-value');
        }
        
        // Add/remove has-value class
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.parentElement.classList.add('has-value');
            } else {
                this.parentElement.classList.remove('has-value');
            }
        });
    });
}

// Scroll animations
function initScrollAnimations() {
    // Animate elements when they come into view
    const animateElements = document.querySelectorAll('.feature-card, .section-title, .about-content, .auth-form');
    
    // Only initialize if IntersectionObserver is supported
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        animateElements.forEach(element => {
            element.classList.add('will-animate');
            observer.observe(element);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        animateElements.forEach(element => {
            element.classList.add('animate');
        });
    }
}

// PWA functionality
function initPWA() {
    // Register service worker for PWA functionality
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/connect/service-worker.js')
                .then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                })
                .catch(error => {
                    console.log('ServiceWorker registration failed: ', error);
                });
        });
    }
    
    // Add PWA install button functionality
    let deferredPrompt;
    const pwaInstallBtn = document.querySelector('.pwa-install');
    
    // Hide install button by default
    if (pwaInstallBtn) {
        pwaInstallBtn.style.display = 'none';
    }
    
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        // Show the install button
        if (pwaInstallBtn) {
            pwaInstallBtn.style.display = 'flex';
            
            // Add animation to make it noticeable
            setTimeout(() => {
                pwaInstallBtn.classList.add('show');
            }, 1000);
        }
    });
    
    // When the install button is clicked
    if (pwaInstallBtn) {
        pwaInstallBtn.addEventListener('click', async () => {
            if (!deferredPrompt) {
                return;
            }
            // Show the install prompt
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            
            // Clear the deferredPrompt variable
            deferredPrompt = null;
            
            // Hide the install button
            pwaInstallBtn.classList.remove('show');
            setTimeout(() => {
            pwaInstallBtn.style.display = 'none';
            }, 300);
        });
    }
    
    // Listen for successful installation
    window.addEventListener('appinstalled', () => {
        // Hide the install button
        if (pwaInstallBtn) {
            pwaInstallBtn.classList.remove('show');
            setTimeout(() => {
            pwaInstallBtn.style.display = 'none';
            }, 300);
        }
        
        // Show a toast notification
        showAlert('App installed successfully!', 'success');
        
        console.log('PWA was installed');
    });
}

// Dashboard charts initialization using Chart.js
function initDashboardCharts() {
    // Make sure Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }
    
    // Appointments chart
    const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
    const appointmentsChart = new Chart(appointmentsCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Appointments',
                data: [12, 19, 15, 17, 14, 8, 5],
                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                borderColor: 'rgba(76, 175, 80, 1)',
                borderWidth: 2,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Patients chart
    const patientsCtx = document.getElementById('patientsChart').getContext('2d');
    const patientsChart = new Chart(patientsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Children', 'Adults', 'Seniors'],
            datasets: [{
                data: [35, 45, 20],
                backgroundColor: [
                    'rgba(33, 150, 243, 0.8)',
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (input.hasAttribute('required') && !input.value.trim()) {
            isValid = false;
            input.classList.add('is-invalid');
            
            // Show error message if not already present
            let errorMsg = input.nextElementSibling;
            if (!errorMsg || !errorMsg.classList.contains('error-message')) {
                errorMsg = document.createElement('small');
                errorMsg.classList.add('error-message');
                errorMsg.style.color = 'var(--error)';
                errorMsg.textContent = 'This field is required';
                input.insertAdjacentElement('afterend', errorMsg);
            }
        } else {
            input.classList.remove('is-invalid');
            const errorMsg = input.nextElementSibling;
            if (errorMsg && errorMsg.classList.contains('error-message')) {
                errorMsg.remove();
            }
        }
    });
    
    return isValid;
}

// Show alert message with toast notifications
function showAlert(message, type = 'info') {
    // Check if toast container exists, if not create it
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Create toast icon based on type
    const iconClass = type === 'success' ? 'fa-check-circle' :
                     type === 'error' ? 'fa-times-circle' :
                     type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    // Create toast content
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${iconClass}"></i>
        </div>
        <div class="toast-content">
            ${message}
        </div>
        <div class="toast-close">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Add close functionality
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', function() {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    });
    
    // Show the toast with animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }, 5000);
}

// Format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Format time
function formatTime(timeString) {
    const options = { hour: '2-digit', minute: '2-digit' };
    return new Date(`2000-01-01T${timeString}`).toLocaleTimeString(undefined, options);
}

// Appointment Scheduling
function initAppointmentScheduler() {
    const dateInput = document.getElementById('appointmentDate');
    const timeSelect = document.getElementById('appointmentTime');
    
    if (dateInput && timeSelect) {
        // Set minimum date to today
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        
        dateInput.min = `${yyyy}-${mm}-${dd}`;
        
        // Populate available times when date changes
        dateInput.addEventListener('change', function() {
            fetchAvailableTimes(this.value);
        });
    }
}

// Fetch available appointment times from server
function fetchAvailableTimes(date) {
    const timeSelect = document.getElementById('appointmentTime');
    if (!timeSelect) return;
    
    // Clear existing options
    timeSelect.innerHTML = '<option value="">Select Time</option>';
    
    // In a real app, this would be an API call to check availability
    // For now, we'll simulate with some static times
    const availableTimes = [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'
    ];
    
    // Add available times
    availableTimes.forEach(time => {
        const option = document.createElement('option');
        option.value = time;
        option.textContent = formatTime(time);
        timeSelect.appendChild(option);
    });
}

// Handle appointment submission
function submitAppointment(event) {
    event.preventDefault();
    
    if (!validateForm('appointmentForm')) {
        return;
    }
    
    // Get form data
    const formData = new FormData(document.getElementById('appointmentForm'));
    
    // In a real app, this would be an API call
    // For now, we'll just show a success message
    showAlert('Appointment scheduled successfully! You will receive an SMS confirmation.', 'success');
    
    // Reset form
    document.getElementById('appointmentForm').reset();
}

// Initialize appointment scheduler on appointment pages
if (document.getElementById('appointmentForm')) {
    initAppointmentScheduler();
    document.getElementById('appointmentForm').addEventListener('submit', submitAppointment);
} 