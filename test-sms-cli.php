<?php
/**
 * SMS Notification Test CLI
 * Test file to verify SMS functionality in Health Connect
 * 
 * Usage: 
 *   Browser: http://localhost/connect/test-sms-cli.php
 *   With send: http://localhost/connect/test-sms-cli.php?send=true
 */

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Include SMS functions
require_once __DIR__ . '/includes/sms.php';

// Test phone number (change this to your test number)
$test_phone = '09123456789';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Test CLI - Health Connect</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #c0392b;
            background: #fadbd8;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            color: #2980b9;
            background: #d6eaf8;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            color: #d68910;
            background: #fef9e7;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
        code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Consolas', monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px 10px 0;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #3498db;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .test-form {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .test-form input, .test-form textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 14px;
        }
        .test-form label {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <h1>🩺 Health Connect - SMS Test CLI</h1>
    
    <div class="test-section">
        <h2>📋 Test Controls</h2>
        <a href="?test=functions" class="btn btn-primary">Test Functions</a>
        <a href="?test=dedup" class="btn btn-primary">Test Deduplication</a>
        <a href="?send=true" class="btn btn-success">Send Test SMS</a>
        <a href="?" class="btn btn-danger">Reset</a>
    </div>

    <?php
    // ============================================
    // TEST 1: Function Availability
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'functions'):
    ?>
    <div class="test-section">
        <h2>🔧 Function Availability Test</h2>
        <table>
            <tr>
                <th>Function</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
            <tr>
                <td><code>convertToPlainMessage()</code></td>
                <td><?php echo function_exists('convertToPlainMessage') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Converts technical terms to plain text</td>
            </tr>
            <tr>
                <td><code>formatSMSMessage()</code></td>
                <td><?php echo function_exists('formatSMSMessage') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Adds IPROG template prefix</td>
            </tr>
            <tr>
                <td><code>sendSMSUsingIPROG()</code></td>
                <td><?php echo function_exists('sendSMSUsingIPROG') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Core API caller function</td>
            </tr>
            <tr>
                <td><code>sendSMS()</code></td>
                <td><?php echo function_exists('sendSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Main SMS function with deduplication</td>
            </tr>
            <tr>
                <td><code>sendAppointmentConfirmationSMS()</code></td>
                <td><?php echo function_exists('sendAppointmentConfirmationSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Sends appointment confirmation</td>
            </tr>
            <tr>
                <td><code>sendAppointmentReminderSMS()</code></td>
                <td><?php echo function_exists('sendAppointmentReminderSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Sends appointment reminder</td>
            </tr>
            <tr>
                <td><code>sendImmunizationReminderSMS()</code></td>
                <td><?php echo function_exists('sendImmunizationReminderSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Sends immunization reminder</td>
            </tr>
            <tr>
                <td><code>sendPatientNotificationSMS()</code></td>
                <td><?php echo function_exists('sendPatientNotificationSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Sends general notification</td>
            </tr>
            <tr>
                <td><code>sendAppointmentCancellationSMS()</code></td>
                <td><?php echo function_exists('sendAppointmentCancellationSMS') ? '<span class="success">✓ Available</span>' : '<span class="error">✗ Missing</span>'; ?></td>
                <td>Sends cancellation notification</td>
            </tr>
        </table>
    </div>

    <div class="test-section">
        <h2>🔄 convertToPlainMessage() Test</h2>
        <?php
        $test_messages = [
            'appointment_id: 123, patient_id: 456' => 'Should convert to: Appointment ID: 123, Patient ID: 456',
            'Your next_dose_date is scheduled' => 'Should convert to: Your Next Dose Date is scheduled',
            'Contact: mobile_number 09123456789' => 'Should convert to: Contact: Mobile Number 09123456789',
            'hw_first_name Dr. Smith' => 'Should convert to: Doctor First Name Dr. Smith'
        ];
        
        echo '<table>';
        echo '<tr><th>Original</th><th>Converted</th><th>Expected</th></tr>';
        foreach ($test_messages as $original => $expected) {
            $converted = convertToPlainMessage($original);
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($original) . "</code></td>";
            echo "<td><code>" . htmlspecialchars($converted) . "</code></td>";
            echo "<td>" . htmlspecialchars($expected) . "</td>";
            echo "</tr>";
        }
        echo '</table>';
        ?>
    </div>

    <div class="test-section">
        <h2>📝 formatSMSMessage() Test</h2>
        <?php
        $test_msg = "Hello, your appointment is confirmed!";
        $formatted = formatSMSMessage($test_msg);
        $char_count = strlen($formatted);
        ?>
        <p><strong>Original:</strong> <code><?php echo htmlspecialchars($test_msg); ?></code></p>
        <p><strong>Formatted:</strong></p>
        <pre><?php echo htmlspecialchars($formatted); ?></pre>
        <p><strong>Character Count:</strong> <?php echo $char_count; ?> 
            <?php if ($char_count <= 160): ?>
                <span class="success">✓ Single SMS (1 credit)</span>
            <?php else: ?>
                <span class="warning">⚠ Multiple SMS (<?php echo ceil($char_count/160); ?> credits)</span>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 2: Deduplication Test
    // ============================================
    if (isset($_GET['test']) && $_GET['test'] === 'dedup'):
    ?>
    <div class="test-section">
        <h2>🔒 Deduplication Test</h2>
        <div class="info">
            <strong>How it works:</strong> The system checks if the same SMS was sent to the same phone number within the last 1 minute.
            If a duplicate is detected, the SMS is NOT sent (saves credits).
        </div>
        
        <?php
        // Check database for recent SMS logs
        try {
            require_once __DIR__ . '/includes/config/database.php';
            $database = new Database();
            $pdo = $database->getConnection();
            
            $query = "SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10";
            $stmt = $pdo->query($query);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($logs) > 0) {
                echo '<h3>Recent SMS Logs (Last 10)</h3>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Recipient</th><th>Message (first 50 chars)</th><th>Status</th><th>Sent At</th></tr>';
                foreach ($logs as $log) {
                    echo '<tr>';
                    echo '<td>' . ($log['sms_id'] ?? $log['id'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($log['recipient_number'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars(substr($log['message'] ?? '', 0, 50)) . '...</td>';
                    echo '<td>' . htmlspecialchars($log['status'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($log['sent_at'] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="warning">No SMS logs found in database.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
    <?php endif; ?>

    <?php
    // ============================================
    // TEST 3: Send Actual SMS
    // ============================================
    if (isset($_GET['send']) && $_GET['send'] === 'true'):
    ?>
    <div class="test-section">
        <h2>📱 Send Test SMS</h2>
        
        <?php if (isset($_POST['phone']) && isset($_POST['message'])): ?>
            <?php
            $phone = $_POST['phone'];
            $message = $_POST['message'];
            
            echo '<div class="info"><strong>Sending SMS to:</strong> ' . htmlspecialchars($phone) . '</div>';
            echo '<div class="info"><strong>Message:</strong> ' . htmlspecialchars($message) . '</div>';
            
            // First, test with sendSMS (which has deduplication)
            echo '<h3>Test 1: Using sendSMS() (with deduplication)</h3>';
            $result1 = sendSMS($phone, $message);
            echo '<pre>' . print_r($result1, true) . '</pre>';
            
            if (isset($result1['success']) && $result1['success']) {
                if (isset($result1['duplicate']) && $result1['duplicate']) {
                    echo '<div class="warning">⚠ DUPLICATE BLOCKED - SMS was already sent recently (1-min window)</div>';
                } else {
                    echo '<div class="success">✓ SMS SENT SUCCESSFULLY!</div>';
                }
            } else {
                echo '<div class="error">✗ Failed: ' . ($result1['message'] ?? 'Unknown error') . '</div>';
            }
            
            // Second test - direct API call (bypasses deduplication)
            if (!isset($result1['duplicate']) || !$result1['duplicate']) {
                echo '<h3>Test 2: Send Again (should be blocked as duplicate)</h3>';
                $result2 = sendSMS($phone, $message);
                echo '<pre>' . print_r($result2, true) . '</pre>';
                
                if (isset($result2['duplicate']) && $result2['duplicate']) {
                    echo '<div class="success">✓ DEDUPLICATION WORKING - Second SMS blocked!</div>';
                } else {
                    echo '<div class="warning">⚠ Deduplication may not be working properly</div>';
                }
            }
            ?>
        <?php else: ?>
            <form method="POST" class="test-form">
                <label for="phone">Phone Number:</label>
                <input type="text" id="phone" name="phone" value="<?php echo $test_phone; ?>" placeholder="09123456789" required>
                
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="3" required>Hello! This is a test SMS from Health Connect system. Appointment confirmed for tomorrow.</textarea>
                
                <button type="submit" class="btn btn-success">📤 Send Test SMS</button>
            </form>
            
            <div class="warning">
                <strong>⚠ Warning:</strong> This will send a REAL SMS and consume API credits!
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!isset($_GET['test']) && !isset($_GET['send'])): ?>
    <div class="test-section">
        <h2>📖 Quick Reference</h2>
        <div class="info">
            <strong>API Provider:</strong> IPROG SMS (https://sms.iprogtech.com)<br>
            <strong>Template Prefix:</strong> "This is an important message from the Organization."<br>
            <strong>Deduplication Window:</strong> 1 minute<br>
            <strong>Max SMS Length (1 credit):</strong> 160 characters
        </div>
        
        <h3>SMS Workflow in Health Connect:</h3>
        <table>
            <tr>
                <th>Action</th>
                <th>SMS Type</th>
                <th>Trigger</th>
            </tr>
            <tr>
                <td>Scheduled → Confirmed</td>
                <td>Confirmation SMS</td>
                <td>Click "Confirm" button</td>
            </tr>
            <tr>
                <td>Send Reminder</td>
                <td>Reminder SMS</td>
                <td>Click "Remind" button</td>
            </tr>
            <tr>
                <td>Any → Cancelled</td>
                <td>Cancellation SMS</td>
                <td>Click "Cancel" button</td>
            </tr>
            <tr>
                <td>Immunization Due</td>
                <td>Immunization Reminder</td>
                <td>From immunization page</td>
            </tr>
        </table>
    </div>
    
    <div class="test-section">
        <h2>🔗 API Endpoints</h2>
        <table>
            <tr>
                <th>Endpoint</th>
                <th>Description</th>
            </tr>
            <tr>
                <td><code>/api/appointments/update_status.php</code></td>
                <td>Update status + auto-send SMS on Confirm/Cancel</td>
            </tr>
            <tr>
                <td><code>/api/appointments/send_reminder.php</code></td>
                <td>Send appointment reminder SMS</td>
            </tr>
            <tr>
                <td><code>/api/appointments/send_cancellation.php</code></td>
                <td>Send cancellation SMS</td>
            </tr>
            <tr>
                <td><code>/api/immunizations/send_reminder.php</code></td>
                <td>Send immunization reminder SMS</td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <div class="test-section">
        <p style="text-align: center; color: #7f8c8d;">
            Health Connect SMS Test CLI v1.0 | <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
