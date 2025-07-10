<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->beginTransaction();

        // Prepare the update statement once
        $stmt = $conn->prepare("INSERT INTO settings (name, value) VALUES (:name, :value) 
                              ON DUPLICATE KEY UPDATE value = VALUES(value)");
        
        // Update appointment settings
        $appointmentSettings = [
            'max_daily_appointments' => $_POST['max_daily_appointments'],
            'appointment_duration' => $_POST['appointment_duration'],
            'working_hours_start' => $_POST['working_hours_start'],
            'working_hours_end' => $_POST['working_hours_end']
        ];

        foreach ($appointmentSettings as $name => $value) {
            $stmt->execute([
                ':name' => $name,
                ':value' => $value
            ]);
        }
        
        // Update notification settings
        $notificationSettings = [
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? '1' : '0',
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? '1' : '0'
        ];

        foreach ($notificationSettings as $name => $value) {
            $stmt->execute([
                ':name' => $name,
                ':value' => $value
            ]);
        }

        // Update SMS gateway settings if enabled
        if (isset($_POST['enable_sms_notifications'])) {
            $smsSettings = [
                'sms_api_key' => $_POST['sms_api_key'],
                'sms_sender_id' => $_POST['sms_sender_id']
            ];

            foreach ($smsSettings as $name => $value) {
                $stmt->execute([
                    ':name' => $name,
                    ':value' => $value
                ]);
            }
        }

        // Update email settings if enabled
        if (isset($_POST['enable_email_notifications'])) {
            $emailSettings = [
                'smtp_host' => $_POST['smtp_host'],
                'smtp_port' => $_POST['smtp_port'],
                'smtp_username' => $_POST['smtp_username'],
                'smtp_encryption' => $_POST['smtp_encryption']
            ];

            // Only update password if provided
            if (!empty($_POST['smtp_password'])) {
                $emailSettings['smtp_password'] = $_POST['smtp_password'];
            }

            foreach ($emailSettings as $name => $value) {
                $stmt->execute([
                    ':name' => $name,
                    ':value' => $value
                ]);
            }
        }

        // Commit transaction
        $conn->commit();
        $_SESSION['success'] = "Settings updated successfully.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Error updating settings: " . $e->getMessage());
        $_SESSION['error'] = "Error updating settings. Please try again.";
    }
}

// Get current settings
try {
    $stmt = $conn->query("SELECT name, value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['name']] = $row['value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching settings. Please try again.";
}

// Set default values if settings are not found
$defaultSettings = [
    'max_daily_appointments' => '20',
    'appointment_duration' => '30',
    'working_hours_start' => '09:00',
    'working_hours_end' => '17:00',
    'enable_sms_notifications' => '0',
    'enable_email_notifications' => '0',
    'sms_api_key' => '',
    'sms_sender_id' => '',
    'smtp_host' => '',
    'smtp_port' => '',
    'smtp_username' => '',
    'smtp_encryption' => 'tls'
];

// Merge default settings with database settings
$settings = array_merge($defaultSettings, $settings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>System Settings</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="settings-card">
                <h3><i class="fas fa-calendar-alt"></i> Appointment Settings</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="max_daily_appointments">Maximum Daily Appointments</label>
                        <input type="number" class="form-control" id="max_daily_appointments" name="max_daily_appointments"
                               value="<?php echo htmlspecialchars($settings['max_daily_appointments'] ?? '20'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="appointment_duration">Appointment Duration (minutes)</label>
                        <input type="number" class="form-control" id="appointment_duration" name="appointment_duration"
                               value="<?php echo htmlspecialchars($settings['appointment_duration'] ?? '30'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="working_hours_start">Working Hours Start</label>
                        <input type="time" class="form-control" id="working_hours_start" name="working_hours_start"
                               value="<?php echo htmlspecialchars($settings['working_hours_start'] ?? '09:00'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="working_hours_end">Working Hours End</label>
                        <input type="time" class="form-control" id="working_hours_end" name="working_hours_end"
                               value="<?php echo htmlspecialchars($settings['working_hours_end'] ?? '17:00'); ?>" required>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_sms_notifications" name="enable_sms_notifications"
                                   <?php echo ($settings['enable_sms_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_sms_notifications">Enable SMS Notifications</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="enable_email_notifications" name="enable_email_notifications"
                                   <?php echo ($settings['enable_email_notifications'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_email_notifications">Enable Email Notifications</label>
                        </div>
                    </div>
                </div>

                <div id="sms_settings" class="notification-settings" style="display: none;">
                    <h4>SMS Gateway Settings</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="sms_api_key">API Key</label>
                            <input type="password" class="form-control" id="sms_api_key" name="sms_api_key"
                                   value="<?php echo htmlspecialchars($settings['sms_api_key'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="sms_sender_id">Sender ID</label>
                            <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id"
                                   value="<?php echo htmlspecialchars($settings['sms_sender_id'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div id="email_settings" class="notification-settings" style="display: none;">
                    <h4>SMTP Settings</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                   placeholder="Leave blank to keep current password">
                        </div>

                        <div class="form-group">
                            <label for="smtp_encryption">Encryption</label>
                            <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="reset" class="btn btn-secondary">Reset Changes</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const smsToggle = document.getElementById('enable_sms_notifications');
        const emailToggle = document.getElementById('enable_email_notifications');
        const smsSettings = document.getElementById('sms_settings');
        const emailSettings = document.getElementById('email_settings');

        function toggleSettings() {
            smsSettings.style.display = smsToggle.checked ? 'block' : 'none';
            emailSettings.style.display = emailToggle.checked ? 'block' : 'none';
        }

        smsToggle.addEventListener('change', toggleSettings);
        emailToggle.addEventListener('change', toggleSettings);

        // Initial toggle
        toggleSettings();
    });
    </script>
</body>
</html> 