<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

if (!isset($_GET['appointment_id'])) {
    die('Appointment ID is required');
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

try {
    // Get appointment details with patient and health worker information
    $query = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.notes,
                p.patient_id,
                p.blood_type,
                CONCAT(u_patient.first_name, ' ', u_patient.last_name) as patient_name,
                u_patient.mobile_number as patient_phone,
                u_patient.email as patient_email,
                u_patient.gender as patient_gender,
                CONCAT(u_hw.first_name, ' ', u_hw.last_name) as health_worker_name,
                hw.position as health_worker_position
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u_patient ON p.user_id = u_patient.user_id
              JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
              JOIN users u_hw ON hw.user_id = u_hw.user_id
              WHERE a.appointment_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        die('Appointment not found');
    }

    // Get clinic information from settings
    $settings_query = "SELECT name, value FROM settings WHERE name IN ('clinic_name', 'clinic_address', 'clinic_phone')";
    $stmt = $pdo->query($settings_query);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Create HTML for the appointment slip
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Appointment Slip</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .clinic-name {
                font-size: 24px;
                font-weight: bold;
                margin: 0;
            }
            .clinic-details {
                font-size: 14px;
                color: #666;
                margin: 5px 0;
            }
            .slip-title {
                text-align: center;
                font-size: 20px;
                font-weight: bold;
                margin: 20px 0;
                text-transform: uppercase;
            }
            .section {
                margin: 15px 0;
            }
            .section-title {
                font-weight: bold;
                margin-bottom: 5px;
            }
            .info-grid {
                display: grid;
                grid-template-columns: auto 1fr;
                gap: 10px;
                margin: 10px 0;
            }
            .label {
                font-weight: bold;
                color: #666;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            .qr-code {
                text-align: center;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1 class="clinic-name">' . ($settings['clinic_name'] ?? 'HealthConnect Clinic') . '</h1>
            <p class="clinic-details">' . ($settings['clinic_address'] ?? '') . '</p>
            <p class="clinic-details">Tel: ' . ($settings['clinic_phone'] ?? '') . '</p>
        </div>

        <div class="slip-title">Appointment Slip</div>

        <div class="section">
            <div class="info-grid">
                <span class="label">Patient Name:</span>
                <span>' . htmlspecialchars($appointment['patient_name']) . '</span>

                <span class="label">Gender:</span>
                <span>' . htmlspecialchars($appointment['patient_gender']) . '</span>

                <span class="label">Contact:</span>
                <span>' . htmlspecialchars($appointment['patient_phone']) . '</span>

                <span class="label">Email:</span>
                <span>' . htmlspecialchars($appointment['patient_email']) . '</span>

                <span class="label">Blood Type:</span>
                <span>' . htmlspecialchars($appointment['blood_type'] ?? 'Not specified') . '</span>
            </div>
        </div>

        <div class="section">
            <div class="info-grid">
                <span class="label">Appointment Date:</span>
                <span>' . date('F d, Y', strtotime($appointment['appointment_date'])) . '</span>

                <span class="label">Time:</span>
                <span>' . date('h:i A', strtotime($appointment['appointment_time'])) . '</span>

                <span class="label">Health Worker:</span>
                <span>' . htmlspecialchars($appointment['health_worker_name']) . ' (' . htmlspecialchars($appointment['health_worker_position']) . ')</span>

                <span class="label">Reason:</span>
                <span>' . htmlspecialchars($appointment['reason']) . '</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Notes:</div>
            <p>' . htmlspecialchars($appointment['notes'] ?? 'No additional notes') . '</p>
        </div>

        <div class="qr-code">
            <!-- QR code containing appointment ID for verification -->
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode("Appointment ID: " . $appointment['appointment_id']) . '" alt="QR Code">
        </div>

        <div class="footer">
            <p>Please arrive 15 minutes before your scheduled appointment time.</p>
            <p>If you need to reschedule, please contact us at least 24 hours in advance.</p>
            <p>Appointment ID: ' . $appointment['appointment_id'] . '</p>
        </div>
    </body>
    </html>';

    // Configure PDF options
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    // Create PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generate filename
    $filename = 'Appointment_Slip_' . $appointment['patient_name'] . '_' . date('Y-m-d', strtotime($appointment['appointment_date'])) . '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, array('Attachment' => true));

} catch (PDOException $e) {
    error_log("Error generating appointment slip: " . $e->getMessage());
    die('Error generating appointment slip. Please try again later.');
}
?> 