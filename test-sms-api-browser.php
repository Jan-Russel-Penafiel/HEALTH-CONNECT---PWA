<?php
/**
 * Test API SMS - This tests the actual API endpoint from the browser
 */

// Start session to test properly
session_start();

// Simulate being logged in as health worker for testing
// In production, this would come from actual login
if (!isset($_SESSION['user_id'])) {
    // Check if there's an existing health worker in the database
    require_once 'includes/config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT hw.health_worker_id, u.user_id, u.first_name, u.last_name 
                         FROM health_workers hw 
                         JOIN users u ON hw.user_id = u.user_id 
                         LIMIT 1");
    $hw = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hw) {
        $_SESSION['user_id'] = $hw['user_id'];
        $_SESSION['role'] = 'health_worker';
        $_SESSION['first_name'] = $hw['first_name'];
        $_SESSION['last_name'] = $hw['last_name'];
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test SMS API</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .test-form { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        label { display: block; margin: 10px 0 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px 10px 0; }
        button:hover { background: #0056b3; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        pre { background: #333; color: #0f0; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; }
        .session-info { background: #e7f3ff; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔬 SMS API Test Interface</h1>
        
        <div class="session-info">
            <strong>Session Status:</strong><br>
            User ID: <?php echo $_SESSION['user_id'] ?? 'NOT SET'; ?><br>
            Role: <?php echo $_SESSION['role'] ?? 'NOT SET'; ?><br>
            Session ID: <?php echo session_id(); ?>
        </div>
        
        <h2>Test 1: Appointment Reminder SMS</h2>
        <div class="test-form">
            <label>Appointment ID:</label>
            <input type="number" id="appointment_id" value="1">
            <button onclick="testAppointmentReminder()">Send Appointment Reminder</button>
        </div>
        <div id="appointment-result"></div>
        
        <h2>Test 2: Immunization Reminder SMS</h2>
        <div class="test-form">
            <label>Patient ID:</label>
            <input type="number" id="patient_id" value="1">
            <label>Message:</label>
            <textarea id="imm_message">[VMC] Hello, your immunization is scheduled. Please arrive 15 minutes early. Thank you. - Respective Personnel</textarea>
            <button onclick="testImmunizationReminder()">Send Immunization Reminder</button>
        </div>
        <div id="immunization-result"></div>
        
        <h2>Test 3: Direct SMS Test (No API)</h2>
        <div class="test-form">
            <label>Phone Number:</label>
            <input type="text" id="direct_phone" value="09677726912">
            <label>Message:</label>
            <textarea id="direct_message">[VMC] Direct test SMS. Thank you. - Respective Personnel</textarea>
            <button onclick="testDirectSMS()">Send Direct SMS</button>
        </div>
        <div id="direct-result"></div>
    </div>
    
    <script>
        function showResult(elementId, data, isSuccess) {
            const el = document.getElementById(elementId);
            el.innerHTML = `<div class="result ${isSuccess ? 'success' : 'error'}">
                <strong>${isSuccess ? '✓ Success' : '✗ Error'}:</strong><br>
                <pre>${JSON.stringify(data, null, 2)}</pre>
            </div>`;
        }
        
        function testAppointmentReminder() {
            const id = document.getElementById('appointment_id').value;
            document.getElementById('appointment-result').innerHTML = '<div class="result info">Sending...</div>';
            
            fetch('/connect/api/appointments/send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ appointment_id: parseInt(id) }),
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    showResult('appointment-result', data, data.success);
                } catch (e) {
                    showResult('appointment-result', { error: 'Invalid JSON', raw: text }, false);
                }
            })
            .catch(error => {
                showResult('appointment-result', { error: error.message }, false);
            });
        }
        
        function testImmunizationReminder() {
            const patientId = document.getElementById('patient_id').value;
            const message = document.getElementById('imm_message').value;
            document.getElementById('immunization-result').innerHTML = '<div class="result info">Sending...</div>';
            
            fetch('/connect/api/immunizations/send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    patient_id: parseInt(patientId),
                    message: message
                }),
                credentials: 'same-origin'
            })
            .then(response => response.text())
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    showResult('immunization-result', data, data.success);
                } catch (e) {
                    showResult('immunization-result', { error: 'Invalid JSON', raw: text }, false);
                }
            })
            .catch(error => {
                showResult('immunization-result', { error: error.message }, false);
            });
        }
        
        function testDirectSMS() {
            const phone = document.getElementById('direct_phone').value;
            const message = document.getElementById('direct_message').value;
            document.getElementById('direct-result').innerHTML = '<div class="result info">Sending...</div>';
            
            // Use the direct test endpoint
            fetch(`/connect/direct-sms-test.php?phone=${encodeURIComponent(phone)}&msg=${encodeURIComponent(message)}&send=1`)
            .then(response => response.text())
            .then(text => {
                document.getElementById('direct-result').innerHTML = `<div class="result success"><pre>${text}</pre></div>`;
            })
            .catch(error => {
                showResult('direct-result', { error: error.message }, false);
            });
        }
    </script>
</body>
</html>
