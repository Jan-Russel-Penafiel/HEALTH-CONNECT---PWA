<?php
/**
 * Today's Appointments Notification Banner
 * Include this file in all health_worker pages to show today's appointments notification
 */

// Only show for health workers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'health_worker') {
    return;
}

// Server-side banner timing control
$current_time = time();
$banner_show_duration = 10; // seconds

// Initialize banner timing if not set
if (!isset($_SESSION['banner_last_shown'])) {
    $_SESSION['banner_last_shown'] = $current_time;
    $_SESSION['banner_visible'] = true;
} else {
    // Calculate elapsed time since last shown
    $elapsed = $current_time - $_SESSION['banner_last_shown'];
    
    // Toggle visibility every 10 seconds
    $cycle_position = $elapsed % ($banner_show_duration * 2);
    
    if ($cycle_position < $banner_show_duration) {
        $_SESSION['banner_visible'] = true;
    } else {
        $_SESSION['banner_visible'] = false;
    }
}

// Check if banner was dismissed today
$banner_dismissed = false;
if (isset($_SESSION['banner_dismissed']) && $_SESSION['banner_dismissed'] === true) {
    if (isset($_SESSION['banner_dismissed_date']) && $_SESSION['banner_dismissed_date'] === date('Y-m-d')) {
        $banner_dismissed = true;
    } else {
        // New day, reset dismissal
        unset($_SESSION['banner_dismissed']);
        unset($_SESSION['banner_dismissed_date']);
    }
}

// Get database connection if not already available
if (!isset($pdo)) {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
}

// Get health worker ID if not available
if (!isset($health_worker_id)) {
    try {
        $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $hw = $stmt->fetch(PDO::FETCH_ASSOC);
        $health_worker_id = $hw ? $hw['health_worker_id'] : null;
    } catch (PDOException $e) {
        $health_worker_id = null;
    }
}

// Get today's appointments
$today_appointments_list = [];
$today_count = 0;
$today = date('Y-m-d');

if ($health_worker_id) {
    try {
        $query = "SELECT a.appointment_id as id, a.appointment_date, a.appointment_time, a.reason, a.status_id,
                         u.first_name, u.last_name, s.status_name as status
                  FROM appointments a 
                  JOIN patients p ON a.patient_id = p.patient_id
                  JOIN users u ON p.user_id = u.user_id
                  JOIN appointment_status s ON a.status_id = s.status_id
                  WHERE a.health_worker_id = ? 
                  AND DATE(a.appointment_date) = ?
                  AND a.status_id NOT IN (3, 4)
                  ORDER BY a.appointment_time ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$health_worker_id, $today]);
        $today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $today_count = count($today_appointments_list);
    } catch (PDOException $e) {
        error_log("Error fetching today's appointments: " . $e->getMessage());
    }
}

// Only display if there are appointments today and banner is not dismissed
if ($today_count > 0 && !$banner_dismissed):
    $banner_class = $_SESSION['banner_visible'] ? '' : 'hidden';
?>

<!-- Today's Appointments Notification Banner -->
<div id="todayAppointmentsBanner" class="today-appointments-banner <?php echo $banner_class; ?>" onclick="handleBannerClick()" style="cursor: pointer;">
    <div class="banner-content">
        <div class="banner-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="banner-text">
            <strong>You have <?php echo $today_count; ?> appointment<?php echo $today_count > 1 ? 's' : ''; ?> today!</strong>
            <span class="banner-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
// Always show modal and scripts if there are appointments today (regardless of banner dismissal)
if ($today_count > 0):
?>

<!-- Today's Appointments Modal -->
<div class="today-modal-overlay" id="todayAppointmentsModal">
    <div class="today-modal-content">
        <div class="today-modal-header">
            <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
            <span class="today-date-badge"><?php echo date('F j, Y'); ?></span>
            <button class="today-modal-close" onclick="closeTodayAppointmentsModal()">&times;</button>
        </div>
        <div class="today-modal-body">
            <div class="today-appointments-summary">
                <div class="summary-item">
                    <div class="summary-value"><?php echo $today_count; ?></div>
                    <div class="summary-label">Total</div>
                </div>
                <div class="summary-item scheduled">
                    <div class="summary-value"><?php 
                        echo count(array_filter($today_appointments_list, fn($a) => strtolower($a['status']) === 'scheduled')); 
                    ?></div>
                    <div class="summary-label">Scheduled</div>
                </div>
                <div class="summary-item confirmed">
                    <div class="summary-value"><?php 
                        echo count(array_filter($today_appointments_list, fn($a) => strtolower($a['status']) === 'confirmed')); 
                    ?></div>
                    <div class="summary-label">Confirmed</div>
                </div>
            </div>
            
            <div class="today-appointments-list">
                <?php foreach ($today_appointments_list as $appt): ?>
                <div class="today-appointment-item status-<?php echo strtolower($appt['status']); ?>" 
                     data-appointment-id="<?php echo $appt['id']; ?>"
                     onclick="goToAppointment(<?php echo $appt['id']; ?>)">
                    <div class="appointment-time-badge">
                        <?php echo date('g:i A', strtotime($appt['appointment_time'])); ?>
                    </div>
                    <div class="appointment-details">
                        <div class="patient-name">
                            <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?>
                        </div>
                        <div class="appointment-reason">
                            <?php echo htmlspecialchars($appt['reason'] ?? 'General Checkup'); ?>
                        </div>
                    </div>
                    <div class="appointment-status-badge <?php echo strtolower($appt['status']); ?>">
                        <?php echo ucfirst($appt['status']); ?>
                    </div>
                    <div class="appointment-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="today-modal-footer">
            <button class="btn btn-secondary" onclick="closeTodayAppointmentsModal()">Close</button>
            <a href="/connect/pages/health_worker/appointments.php" class="btn btn-primary">
                <i class="fas fa-calendar-alt"></i> Go to Appointments
            </a>
        </div>
    </div>
</div>

<style>
/* Today's Appointments Banner Styles */
.today-appointments-banner {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 12px 20px;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.today-appointments-banner.hidden {
    display: none;
}

.banner-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    max-width: 1200px;
    margin: 0 auto;
    flex-wrap: wrap;
}

.banner-icon {
    font-size: 1.5rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.banner-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.banner-text strong {
    font-size: 1rem;
}

.banner-date {
    font-size: 0.85rem;
    opacity: 0.9;
}

/* Today's Appointments Modal Styles */
.today-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1100;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.today-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.today-modal-content {
    background: white;
    border-radius: 16px;
    width: 95%;
    max-width: 550px;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transform: scale(0.9) translateY(20px);
    transition: transform 0.3s ease;
}

.today-modal-overlay.active .today-modal-content {
    transform: scale(1) translateY(0);
}

.today-modal-header {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 20px;
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
}

.today-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    flex-grow: 1;
}

.today-date-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
}

.today-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.today-modal-close:hover {
    background: rgba(255,255,255,0.2);
}

.today-modal-body {
    padding: 20px;
    max-height: calc(85vh - 180px);
    overflow-y: auto;
}

.today-appointments-summary {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.summary-item {
    flex: 1;
    text-align: center;
    padding: 12px;
    background: #f5f5f5;
    border-radius: 10px;
}

.summary-item.scheduled {
    background: #e3f2fd;
}

.summary-item.confirmed {
    background: #e8f5e9;
}

.summary-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
}

.summary-label {
    font-size: 0.75rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.today-appointments-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.today-appointment-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 12px;
    border-left: 4px solid #ddd;
    cursor: pointer;
    transition: all 0.2s ease;
}

.today-appointment-item:hover {
    background: #f0f0f0;
    transform: translateX(5px);
}

.today-appointment-item.status-scheduled {
    border-left-color: #2196F3;
}

.today-appointment-item.status-confirmed {
    border-left-color: #4CAF50;
}

.appointment-time-badge {
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 80px;
    text-align: center;
}

.appointment-details {
    flex-grow: 1;
}

.patient-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}

.appointment-reason {
    font-size: 0.85rem;
    color: #666;
}

.appointment-status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.appointment-status-badge.scheduled {
    background: #e3f2fd;
    color: #1976D2;
}

.appointment-status-badge.confirmed {
    background: #e8f5e9;
    color: #388E3C;
}

.appointment-arrow {
    color: #999;
    transition: transform 0.2s;
}

.today-appointment-item:hover .appointment-arrow {
    transform: translateX(5px);
    color: #4CAF50;
}

.today-modal-footer {
    padding: 15px 20px;
    background: #f5f5f5;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.today-modal-footer .btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.today-modal-footer .btn-secondary {
    background: #e0e0e0;
    color: #333;
    border: none;
    cursor: pointer;
}

.today-modal-footer .btn-primary {
    background: #4CAF50;
    color: white;
    border: none;
}

/* Adjust container padding when banner is visible */
body.has-today-banner .container {
    padding-top: 80px;
}

/* Mobile responsiveness */
@media (max-width: 576px) {
    .today-appointments-banner {
        padding: 10px 15px;
    }
    
    .banner-content {
        gap: 10px;
    }
    
    .banner-icon {
        font-size: 1.2rem;
    }
    
    .banner-text strong {
        font-size: 0.9rem;
    }
    
    .banner-date {
        display: none;
    }
    
    .today-modal-content {
        width: 98%;
        max-height: 90vh;
    }
    
    .today-appointments-summary {
        flex-wrap: wrap;
    }
    
    .summary-item {
        min-width: calc(33% - 7px);
    }
    
    .appointment-time-badge {
        min-width: 65px;
        padding: 6px 8px;
        font-size: 0.8rem;
    }
    
    .appointment-status-badge {
        display: none;
    }
}  </style>

<script>
// Today's Appointments Banner Functions

// Banner show/hide interval (20 seconds)
let bannerInterval = null;
let bannerVisible = <?php echo $_SESSION['banner_visible'] ? 'true' : 'false'; ?>;

function startBannerInterval() {
    if (bannerInterval) return; // Prevent multiple intervals
    
    bannerInterval = setInterval(function() {
        const banner = document.getElementById('todayAppointmentsBanner');
        if (!banner) {
            clearInterval(bannerInterval);
            return;
        }
        
        bannerVisible = !bannerVisible;
        
        if (bannerVisible) {
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
        }
    }, 5000); // 5 seconds
}

// Start interval when page loads
if (document.getElementById('todayAppointmentsBanner')) {
    startBannerInterval();
}

// Handle banner click - dismiss and redirect to appointments
function handleBannerClick() {
    // Stop the interval
    if (bannerInterval) {
        clearInterval(bannerInterval);
        bannerInterval = null;
    }
    // Dismiss the banner permanently in background
    fetch('/connect/api/banner/dismiss.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    }).catch(error => console.error('Error dismissing banner:', error));
    
    // Redirect to appointments.php with parameter to open modal
    window.location.href = '/connect/pages/health_worker/appointments.php?openTodayModal=1';
}

function openTodayAppointmentsModal() {
    // Dismiss banner permanently via AJAX
    fetch('/connect/api/banner/dismiss.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              // Hide banner immediately
              const banner = document.getElementById('todayAppointmentsBanner');
              if (banner) {
                  banner.classList.add('hidden');
              }
          }
      });
    
    document.getElementById('todayAppointmentsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeTodayAppointmentsModal() {
    document.getElementById('todayAppointmentsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function goToAppointment(appointmentId) {
    // Close modal
    closeTodayAppointmentsModal();
    
    // Check if we're on the appointments page
    const currentPage = window.location.pathname;
    if (currentPage.includes('appointments.php')) {
        // Already on appointments page, just highlight
        highlightAppointment(appointmentId);
    } else {
        // Navigate to appointments page with highlight parameter
        window.location.href = '/connect/pages/health_worker/appointments.php?highlight=' + appointmentId;
    }
}

function highlightAppointment(appointmentId) {
    // Find the appointment card/row
    const elements = document.querySelectorAll('[data-appointment-id="' + appointmentId + '"]');
    
    elements.forEach(element => {
        // Scroll into view
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add highlight class
        element.classList.add('highlighted');
        
        // Remove highlight after animation
        setTimeout(() => {
            element.classList.remove('highlighted');
        }, 3000);
    });
}

// Check for highlight parameter in URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        setTimeout(() => {
            highlightAppointment(highlightId);
        }, 500);
        
        // Clean up URL
        const url = new URL(window.location);
        url.searchParams.delete('highlight');
        window.history.replaceState({}, '', url);
    }
});

// Close modal on outside click
document.getElementById('todayAppointmentsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeTodayAppointmentsModal();
    }
});
</script>

<?php endif; // end if ($today_count > 0) ?>
