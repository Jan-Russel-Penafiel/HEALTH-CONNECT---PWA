<?php
// Include database connection
require_once __DIR__ . '/config/database.php';

// Get database connection if not already set
if (!isset($pdo)) {
    $database = new Database();
    $pdo = $database->getConnection();
}

// Only show for health workers
if ($_SESSION['role'] !== 'health_worker') {
    return;
}

// Server-side banner timing control
$current_time = time();
$hw_banner_show_duration = 10; // seconds

// Initialize banner timing if not set
if (!isset($_SESSION['hw_banner_last_shown'])) {
    $_SESSION['hw_banner_last_shown'] = $current_time;
    $_SESSION['hw_banner_visible'] = true;
} else {
    // Calculate elapsed time since last shown
    $elapsed = $current_time - $_SESSION['hw_banner_last_shown'];
    
    // Toggle visibility every 10 seconds
    $cycle_position = $elapsed % ($hw_banner_show_duration * 2);
    
    if ($cycle_position < $hw_banner_show_duration) {
        $_SESSION['hw_banner_visible'] = true;
    } else {
        $_SESSION['hw_banner_visible'] = false;
    }
}

// Get health worker ID if not already set
if (!isset($health_worker_id)) {
    try {
        $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($health_worker) {
            $health_worker_id = $health_worker['health_worker_id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching health worker ID: " . $e->getMessage());
    }
}

// Get today's appointments if health_worker_id exists
if (isset($health_worker_id)) {
    try {
        $today_hw_query = "SELECT a.appointment_id as id, a.appointment_time,
                                  u.first_name, u.last_name
                           FROM appointments a 
                           JOIN patients p ON a.patient_id = p.patient_id
                           JOIN users u ON p.user_id = u.user_id
                           WHERE a.health_worker_id = ? 
                           AND DATE(a.appointment_date) = CURDATE()
                           AND a.status_id != 3
                           ORDER BY a.appointment_time ASC";
        
        $stmt = $pdo->prepare($today_hw_query);
        $stmt->execute([$health_worker_id]);
        $today_hw_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $today_hw_count = count($today_hw_appointments);
    } catch (PDOException $e) {
        error_log("Error fetching today's appointments: " . $e->getMessage());
        $today_hw_appointments = [];
        $today_hw_count = 0;
    }
}

$hw_banner_class = $_SESSION['hw_banner_visible'] ? '' : 'hidden';
?>

<?php if (isset($today_hw_count) && $today_hw_count > 0): ?>
<style>
.today-hw-banner {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
    margin-right: 1rem;
    position: relative;
}

.today-hw-banner:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
}

.today-hw-banner.hidden {
    display: none;
}

.today-hw-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
}

.today-hw-header i {
    font-size: 1rem;
}

.today-hw-badge {
    background: rgba(255, 255, 255, 0.9);
    color: #4CAF50;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
    min-width: 24px;
    text-align: center;
}

.today-hw-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    min-width: 280px;
    max-width: 350px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.today-hw-banner:hover .today-hw-dropdown {
    display: block;
}

.today-hw-dropdown-header {
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    font-weight: 600;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.today-hw-list {
    padding: 0.5rem;
}

.today-hw-item {
    padding: 0.75rem;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: background 0.2s ease;
}

.today-hw-item:last-child {
    border-bottom: none;
}

.today-hw-item:hover {
    background: #f8f9fa;
}

.today-hw-time {
    background: #4CAF50;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.8rem;
    min-width: 70px;
    text-align: center;
}

.today-hw-patient {
    flex: 1;
    font-weight: 500;
    color: #333;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .today-hw-banner {
        padding: 0.4rem 0.6rem;
        margin-right: 0.5rem;
    }
    
    .today-hw-header {
        font-size: 0.8rem;
    }
    
    .today-hw-header span {
        display: none;
    }
    
    .today-hw-dropdown {
        right: -50px;
        min-width: 250px;
    }
}
</style>

<div id="todayHWBanner" class="today-hw-banner <?php echo $hw_banner_class; ?>">
    <div class="today-hw-header">
        <i class="fas fa-calendar-day"></i>
        <span>Today's</span>
        <span class="today-hw-badge"><?php echo $today_hw_count; ?></span>
    </div>
    <div class="today-hw-dropdown">
        <div class="today-hw-dropdown-header">
            <span><i class="fas fa-calendar-day"></i> Today's Appointments</span>
            <span><?php echo $today_hw_count; ?></span>
        </div>
        <div class="today-hw-list">
            <?php foreach ($today_hw_appointments as $appt): ?>
            <div class="today-hw-item" onclick="window.location.href='/connect/pages/health_worker/view_appointment.php?id=<?php echo $appt['id']; ?>'">
                <div class="today-hw-time">
                    <?php echo date('g:i A', strtotime($appt['appointment_time'])); ?>
                </div>
                <div class="today-hw-patient">
                    <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Auto-toggle banner visibility every 10 seconds
setInterval(function() {
    const hwBanner = document.getElementById('todayHWBanner');
    if (hwBanner) {
        hwBanner.classList.toggle('hidden');
    }
}, 10000); // 10 seconds
</script>
<?php endif; ?>
