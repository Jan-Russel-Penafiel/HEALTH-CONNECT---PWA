<?php
/**
 * Complete SMS Testing Script for Health Connect
 * This script tests all SMS functionality including the API endpoints
 */

// Prevent running from command line for safety
if (php_sapi_name() !== 'cli' && !isset($_GET['allow'])) {
    // Web interface mode
}

// Include required files
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/sms.php';

// Initialize database
$database = new Database();
$pdo = $database->getConnection();

// Helper function to output results
function output($title, $data, $type = 'info') {
    $colors = [
        'success' => '#27ae60',
        'error' => '#e74c3c',
        'warning' => '#f39c12',
        'info' => '#3498db'
    ];
    $color = $colors[$type] ?? $colors['info'];
    
    echo "<div style='margin: 10px 0; padding: 15px; border-left: 4px solid {$color}; background: #f8f9fa;'>";
    echo "<strong style='color: {$color};'>{$title}</strong><br>";
    if (is_array($data) || is_object($data)) {
        echo "<pre style='margin-top: 10px; background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
        print_r($data);
        echo "</pre>";
    } else {
        echo "<p style='margin-top: 5px;'>{$data}</p>";
    }
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Complete Test - Health Connect</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #ecf0f1;
        }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .section {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn:hover { opacity: 0.8; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .test-form input, .test-form textarea, .test-form select {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .test-form label { font-weight: bold; color: #2c3e50; }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-fail { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🩺 Health Connect - Complete SMS Test Suite</h1>

    <div class="section">
        <h2>📋 Test Menu</h2>
        <a href="?test=config" class="btn btn-primary">1. Check Configuration</a>
        <a href="?test=functions" class="btn btn-primary">2. Test Functions</a>
        <a href="?test=patients" class="btn btn-primary">3. View Patients</a>
        <a href="?test=appointments" class="btn btn-primary">4. View Appointments</a>
        <a href="?test=immunizations" class="btn btn-primary">5. View Immunizations</a>
        <br><br>
        <a href="?test=send_form" class="btn btn-success">📱 Send Test SMS</a>
        <a href="?" class="btn btn-warning">🔄 Reset</a>
    </div>

    <?php
    // ============================================
    // TEST 1: Configuration Check
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'config'):
    ?>
    <div class="section">
        <h2>⚙️ SMS Configuration Check</h2>
        <?php
        try {
            // Check SMS settings
            $query = "SELECT name, value FROM settings WHERE name LIKE '%sms%' OR name LIKE '%notification%'";
            $stmt = $pdo->query($query);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
            foreach ($settings as $setting) {
                $status = !empty($setting['value']) ? '<span class="status-ok">✓ Set</span>' : '<span class="status-fail">✗ Empty</span>';
                // Mask API key for security
                $value = $setting['name'] === 'sms_api_key' ? substr($setting['value'], 0, 8) . '...' : $setting['value'];
                echo "<tr><td>{$setting['name']}</td><td>{$value}</td><td>{$status}</td></tr>";
            }
            echo "</table>";
            
            // Check if enable_sms_notifications is set to 1
            $sms_enabled = false;
            foreach ($settings as $setting) {
                if ($setting['name'] === 'enable_sms_notifications' && $setting['value'] === '1') {
                    $sms_enabled = true;
                }
            }
            
            if ($sms_enabled) {
                output("SMS Status", "SMS notifications are ENABLED", "success");
            } else {
                output("SMS Status", "SMS notifications are DISABLED - enable them in admin settings", "warning");
            }
            
        } catch (PDOException $e) {
            output("Database Error", $e->getMessage(), "error");
        }
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 2: Function Availability
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'functions'):
    ?>
    <div class="section">
        <h2>🔧 SMS Function Tests</h2>
        <table>
            <tr><th>Function</th><th>Status</th><th>Description</th></tr>
            <?php
            $functions = [
                'convertToPlainMessage' => 'Converts technical terms to plain text',
                'formatSMSMessage' => 'Adds IPROG template prefix',
                'sendSMSUsingIPROG' => 'Core IPROG API caller',
                'sendSMS' => 'Main SMS function with deduplication',
                'sendAppointmentConfirmationSMS' => 'Appointment confirmation',
                'sendAppointmentReminderSMS' => 'Appointment reminder',
                'sendAppointmentCancellationSMS' => 'Appointment cancellation',
                'sendImmunizationReminderSMS' => 'Immunization reminder',
                'sendPatientNotificationSMS' => 'General patient notification'
            ];
            
            foreach ($functions as $func => $desc) {
                $exists = function_exists($func);
                $status = $exists ? '<span class="status-ok">✓ Available</span>' : '<span class="status-fail">✗ Missing</span>';
                echo "<tr><td><code>{$func}()</code></td><td>{$status}</td><td>{$desc}</td></tr>";
            }
            ?>
        </table>

        <h3>Function Output Tests</h3>
        <?php
        // Test convertToPlainMessage
        $test_input = "Your appointment_id 123 with health_worker_id 5 on appointment_date";
        $converted = convertToPlainMessage($test_input);
        output("convertToPlainMessage() Test", "Input: {$test_input}\nOutput: {$converted}", "info");
        
        // Test formatSMSMessage
        $test_msg = "Your appointment is confirmed for tomorrow";
        $formatted = formatSMSMessage($test_msg);
        output("formatSMSMessage() Test", "Input: {$test_msg}\nOutput: {$formatted}\nLength: " . strlen($formatted) . " characters", "info");
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 3: View Patients
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'patients'):
    ?>
    <div class="section">
        <h2>👥 Available Patients for Testing</h2>
        <?php
        try {
            $query = "SELECT p.patient_id, u.first_name, u.last_name, u.mobile_number, u.email 
                      FROM patients p 
                      JOIN users u ON p.user_id = u.user_id 
                      WHERE u.is_active = 1 
                      LIMIT 20";
            $stmt = $pdo->query($query);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($patients) > 0) {
                echo "<table>";
                echo "<tr><th>Patient ID</th><th>Name</th><th>Mobile Number</th><th>Email</th><th>Action</th></tr>";
                foreach ($patients as $patient) {
                    $hasPhone = !empty($patient['mobile_number']) ? '<span class="status-ok">✓</span>' : '<span class="status-fail">✗ No phone</span>';
                    echo "<tr>";
                    echo "<td>{$patient['patient_id']}</td>";
                    echo "<td>{$patient['first_name']} {$patient['last_name']}</td>";
                    echo "<td>{$patient['mobile_number']} {$hasPhone}</td>";
                    echo "<td>{$patient['email']}</td>";
                    if (!empty($patient['mobile_number'])) {
                        echo "<td><a href='?test=send_form&patient_id={$patient['patient_id']}&phone={$patient['mobile_number']}&name={$patient['first_name']}' class='btn btn-success' style='padding: 5px 10px;'>Send SMS</a></td>";
                    } else {
                        echo "<td>-</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                output("No Patients", "No patients found in the database", "warning");
            }
        } catch (PDOException $e) {
            output("Database Error", $e->getMessage(), "error");
        }
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 4: View Appointments
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'appointments'):
    ?>
    <div class="section">
        <h2>📅 Recent Appointments for Testing</h2>
        <?php
        try {
            $query = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, 
                             u.first_name, u.last_name, u.mobile_number,
                             s.status_name as status,
                             COALESCE(a.sms_notification_sent, 0) as sms_sent
                      FROM appointments a 
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN users u ON p.user_id = u.user_id
                      JOIN appointment_status s ON a.status_id = s.status_id
                      ORDER BY a.appointment_date DESC
                      LIMIT 20";
            $stmt = $pdo->query($query);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($appointments) > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Patient</th><th>Date</th><th>Time</th><th>Status</th><th>Phone</th><th>SMS Sent</th><th>Action</th></tr>";
                foreach ($appointments as $apt) {
                    $smsSent = $apt['sms_sent'] ? '<span class="status-ok">✓ Yes</span>' : '<span class="status-fail">No</span>';
                    $hasPhone = !empty($apt['mobile_number']) ? '<span class="status-ok">' . $apt['mobile_number'] . '</span>' : '<span class="status-fail">No phone</span>';
                    echo "<tr>";
                    echo "<td>{$apt['appointment_id']}</td>";
                    echo "<td>{$apt['first_name']} {$apt['last_name']}</td>";
                    echo "<td>{$apt['appointment_date']}</td>";
                    echo "<td>{$apt['appointment_time']}</td>";
                    echo "<td>{$apt['status']}</td>";
                    echo "<td>{$hasPhone}</td>";
                    echo "<td>{$smsSent}</td>";
                    if (!empty($apt['mobile_number'])) {
                        echo "<td><a href='?test=send_appointment&appointment_id={$apt['appointment_id']}' class='btn btn-success' style='padding: 5px 10px;'>Send Reminder</a></td>";
                    } else {
                        echo "<td>-</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                output("No Appointments", "No appointments found in the database", "warning");
            }
        } catch (PDOException $e) {
            output("Database Error", $e->getMessage(), "error");
        }
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 5: View Immunizations
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'immunizations'):
    ?>
    <div class="section">
        <h2>💉 Immunization Records with Upcoming Schedules</h2>
        <?php
        try {
            $query = "SELECT ir.immunization_record_id, ir.patient_id, ir.next_schedule_date,
                             u.first_name, u.last_name, u.mobile_number,
                             it.name as immunization_name
                      FROM immunization_records ir 
                      JOIN patients p ON ir.patient_id = p.patient_id
                      JOIN users u ON p.user_id = u.user_id
                      JOIN immunization_types it ON ir.immunization_type_id = it.immunization_type_id
                      WHERE ir.next_schedule_date IS NOT NULL
                      ORDER BY ir.next_schedule_date ASC
                      LIMIT 20";
            $stmt = $pdo->query($query);
            $immunizations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($immunizations) > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Patient</th><th>Immunization</th><th>Next Schedule</th><th>Phone</th><th>Action</th></tr>";
                foreach ($immunizations as $imm) {
                    $hasPhone = !empty($imm['mobile_number']) ? '<span class="status-ok">' . $imm['mobile_number'] . '</span>' : '<span class="status-fail">No phone</span>';
                    echo "<tr>";
                    echo "<td>{$imm['immunization_record_id']}</td>";
                    echo "<td>{$imm['first_name']} {$imm['last_name']}</td>";
                    echo "<td>{$imm['immunization_name']}</td>";
                    echo "<td>{$imm['next_schedule_date']}</td>";
                    echo "<td>{$hasPhone}</td>";
                    if (!empty($imm['mobile_number'])) {
                        echo "<td><a href='?test=send_immunization&patient_id={$imm['patient_id']}&vaccine={$imm['immunization_name']}&date={$imm['next_schedule_date']}' class='btn btn-success' style='padding: 5px 10px;'>Send Reminder</a></td>";
                    } else {
                        echo "<td>-</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                output("No Immunizations", "No immunization records with upcoming schedules found", "warning");
            }
        } catch (PDOException $e) {
            output("Database Error", $e->getMessage(), "error");
        }
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // Send SMS Form
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'send_form'):
    ?>
    <div class="section">
        <h2>📱 Send Test SMS</h2>
        <form method="POST" action="?test=send_sms" class="test-form">
            <label for="phone">Phone Number (Philippine format: 09xxxxxxxxx or 639xxxxxxxxx):</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>" required>
            
            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="4" required>Hello <?php echo htmlspecialchars($_GET['name'] ?? 'Patient'); ?>, this is a test message from Health Connect. Thank you!</textarea>
            
            <label for="type">Message Type:</label>
            <select id="type" name="type">
                <option value="general">General Message</option>
                <option value="appointment_reminder">Appointment Reminder</option>
                <option value="immunization_reminder">Immunization Reminder</option>
            </select>
            
            <?php if (isset($_GET['patient_id'])): ?>
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($_GET['patient_id']); ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn btn-success">📤 Send SMS</button>
            <a href="?" class="btn btn-warning">Cancel</a>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
            <strong>⚠️ Note:</strong> This will send a real SMS and use your API credits. 
            The message will be prefixed with the IPROG template requirement.
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // Process SMS Send
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'send_sms' && $_SERVER['REQUEST_METHOD'] === 'POST'):
    ?>
    <div class="section">
        <h2>📤 SMS Send Result</h2>
        <?php
        $phone = $_POST['phone'] ?? '';
        $message = $_POST['message'] ?? '';
        $type = $_POST['type'] ?? 'general';
        
        if (!empty($phone) && !empty($message)) {
            output("Sending SMS", "Phone: {$phone}\nMessage: {$message}\nType: {$type}", "info");
            
            // Send the SMS
            $result = sendSMS($phone, $message);
            
            if ($result['success']) {
                output("SMS Sent Successfully!", $result, "success");
            } else {
                output("SMS Failed", $result, "error");
            }
        } else {
            output("Invalid Input", "Phone number and message are required", "error");
        }
        ?>
        <a href="?test=send_form" class="btn btn-primary">Send Another</a>
        <a href="?" class="btn btn-warning">Back to Menu</a>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // Send Appointment Reminder
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'send_appointment' && isset($_GET['appointment_id'])):
    ?>
    <div class="section">
        <h2>📅 Appointment Reminder Result</h2>
        <?php
        $appointment_id = intval($_GET['appointment_id']);
        output("Sending Appointment Reminder", "Appointment ID: {$appointment_id}", "info");
        
        $result = sendAppointmentReminderSMS($appointment_id);
        
        if ($result['success']) {
            output("Reminder Sent Successfully!", $result, "success");
        } else {
            output("Reminder Failed", $result, "error");
        }
        ?>
        <a href="?test=appointments" class="btn btn-primary">Back to Appointments</a>
        <a href="?" class="btn btn-warning">Back to Menu</a>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // Send Immunization Reminder
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'send_immunization' && isset($_GET['patient_id'])):
    ?>
    <div class="section">
        <h2>💉 Immunization Reminder Result</h2>
        <?php
        $patient_id = intval($_GET['patient_id']);
        $vaccine = $_GET['vaccine'] ?? 'Vaccine';
        $date = $_GET['date'] ?? null;
        
        output("Sending Immunization Reminder", "Patient ID: {$patient_id}\nVaccine: {$vaccine}\nDate: {$date}", "info");
        
        $result = sendImmunizationReminderSMS($patient_id, $vaccine, $date);
        
        if ($result['success']) {
            output("Reminder Sent Successfully!", $result, "success");
        } else {
            output("Reminder Failed", $result, "error");
        }
        ?>
        <a href="?test=immunizations" class="btn btn-primary">Back to Immunizations</a>
        <a href="?" class="btn btn-warning">Back to Menu</a>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // Default View - Quick Status
    // ============================================
    if (!isset($_GET['test'])):
    ?>
    <div class="section">
        <h2>📊 Quick Status</h2>
        <?php
        try {
            // Check SMS enabled
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'enable_sms_notifications'");
            $stmt->execute();
            $sms_enabled = $stmt->fetchColumn() === '1';
            
            // Check API key
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'sms_api_key'");
            $stmt->execute();
            $api_key = $stmt->fetchColumn();
            $has_api_key = !empty($api_key);
            
            // Count patients with phone numbers
            $stmt = $pdo->query("SELECT COUNT(*) FROM patients p JOIN users u ON p.user_id = u.user_id WHERE u.mobile_number IS NOT NULL AND u.mobile_number != ''");
            $patients_with_phone = $stmt->fetchColumn();
            
            // Count pending appointments
            $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status_id IN (1, 2) AND appointment_date >= CURDATE()");
            $pending_appointments = $stmt->fetchColumn();
            
            echo "<table>";
            echo "<tr><th>Check</th><th>Status</th></tr>";
            echo "<tr><td>SMS Notifications</td><td>" . ($sms_enabled ? '<span class="status-ok">✓ Enabled</span>' : '<span class="status-fail">✗ Disabled</span>') . "</td></tr>";
            echo "<tr><td>IPROG API Key</td><td>" . ($has_api_key ? '<span class="status-ok">✓ Configured</span>' : '<span class="status-fail">✗ Missing</span>') . "</td></tr>";
            echo "<tr><td>Patients with Phone</td><td>{$patients_with_phone}</td></tr>";
            echo "<tr><td>Pending Appointments</td><td>{$pending_appointments}</td></tr>";
            echo "</table>";
            
            if ($sms_enabled && $has_api_key) {
                output("System Ready", "SMS system is configured and ready to send messages", "success");
            } else {
                output("Configuration Required", "Please configure SMS settings in Admin > Settings", "warning");
            }
            
        } catch (PDOException $e) {
            output("Database Error", $e->getMessage(), "error");
        }
        ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>📖 Usage Guide</h2>
        <ol>
            <li><strong>Check Configuration</strong> - Verify SMS settings are correct</li>
            <li><strong>Test Functions</strong> - Ensure all PHP functions are available</li>
            <li><strong>View Patients</strong> - See patients with phone numbers for testing</li>
            <li><strong>View Appointments</strong> - See appointments and send reminders</li>
            <li><strong>View Immunizations</strong> - See immunization records and send reminders</li>
            <li><strong>Send Test SMS</strong> - Send a custom test message</li>
        </ol>
        
        <h3>API Endpoints</h3>
        <ul>
            <li><code>/connect/api/appointments/send_reminder.php</code> - Send appointment reminder</li>
            <li><code>/connect/api/immunizations/send_reminder.php</code> - Send immunization reminder</li>
            <li><code>/connect/api/appointments/update_status.php</code> - Update status (sends confirmation SMS)</li>
            <li><code>/connect/api/appointments/send_cancellation.php</code> - Send cancellation SMS</li>
        </ul>
    </div>

</body>
</html>
