<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../../includes/auth_check.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Check if user is a patient
if ($_SESSION['role'] !== 'patient') {
    header("Location: ../dashboard.php");
    exit;
}

// Get appointment ID
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$appointment_id) {
    die("Appointment ID is required");
}

$database = new Database();
$conn = $database->getConnection();

// Get appointment details with patient and health worker information
$query = "SELECT a.*, 
          CONCAT(p_u.first_name, ' ', p_u.last_name) as patient_name,
          p_u.mobile_number as patient_phone,
          CONCAT(h_u.first_name, ' ', h_u.last_name) as health_worker_name,
          hw.position as health_worker_position
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users p_u ON p.user_id = p_u.user_id
          JOIN health_workers hw ON a.health_worker_id = hw.health_worker_id
          JOIN users h_u ON hw.user_id = h_u.user_id
          WHERE a.appointment_id = :id 
          AND p.user_id = :user_id
          AND a.status_id = 2"; // Only confirmed appointments

$stmt = $conn->prepare($query);
$stmt->execute([
    ':id' => $appointment_id,
    ':user_id' => $_SESSION['user_id']
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Appointment not found or not confirmed");
}

// Generate QR code data
$qr_data = json_encode([
    'appointment_id' => $appointment_id,
    'patient' => $appointment['patient_name'],
    'date' => $appointment['appointment_date'],
    'time' => $appointment['appointment_time']
]);

// Create QR code image
$qr_options = new QROptions([
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel' => QRCode::ECC_L,
    'scale' => 5,
    'imageBase64' => true,
]);

$qr_code = new QRCode($qr_options);
$qr_image = $qr_code->render($qr_data);

// Create PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);

// PDF content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Appointment Slip</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 15px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .header p {
            margin: 3px 0;
            color: #666;
            font-size: 12px;
        }
        .appointment-details {
            margin-bottom: 15px;
        }
        .detail-row {
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }
        .detail-row .label {
            font-weight: bold;
            color: #2c3e50;
            width: 30%;
        }
        .detail-row .value {
            width: 70%;
        }
        .qr-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }
        .qr-code {
            text-align: center;
            width: 30%;
        }
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        .qr-code p {
            margin: 5px 0;
            font-size: 10px;
        }
        .important-notes {
            width: 65%;
        }
        .important-notes h4 {
            margin: 0 0 5px 0;
            font-size: 12px;
        }
        .important-notes ul {
            margin: 0;
            padding-left: 15px;
            font-size: 10px;
        }
        .important-notes li {
            margin-bottom: 3px;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Brgy. Poblacion Health Center</h1>
        <p>Appointment Confirmation Slip</p>
        <p>Contact: (123) 456-7890</p>
    </div>

    <div class="appointment-details">
        <div class="detail-row">
            <span class="label">Appointment ID:</span>
            <span class="value">' . $appointment_id . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Patient Name:</span>
            <span class="value">' . htmlspecialchars($appointment['patient_name']) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Contact Number:</span>
            <span class="value">' . htmlspecialchars($appointment['patient_phone']) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Date:</span>
            <span class="value">' . date('l, F j, Y', strtotime($appointment['appointment_date'])) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Time:</span>
            <span class="value">' . date('g:i A', strtotime($appointment['appointment_time'])) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Health Worker:</span>
            <span class="value">' . htmlspecialchars($appointment['health_worker_name']) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Position:</span>
            <span class="value">' . htmlspecialchars($appointment['health_worker_position']) . '</span>
        </div>
        <div class="detail-row">
            <span class="label">Reason for Visit:</span>
            <span class="value">' . htmlspecialchars($appointment['reason'] ?: 'Not specified') . '</span>
        </div>
    </div>

    <div class="qr-section">
        <div class="important-notes">
            <h4>Important Notes:</h4>
            <ul>
                <li>Please arrive 15 minutes before your scheduled appointment time</li>
                <li>Bring this slip and a valid ID</li>
                <li>If you need to cancel or reschedule, please do so at least 24 hours in advance</li>
                <li>Follow health protocols (wear mask if required)</li>
                <li>For any questions or concerns, contact the health center</li>
            </ul>
        </div>
        <div class="qr-code">
            <img src="' . $qr_image . '" alt="QR Code">
            <p>Scan for quick check-in</p>
        </div>
    </div>

    <div class="footer">
        <p>This is an automatically generated appointment slip. For verification, please contact the health center.</p>
    </div>
</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait'); // Changed to A5 size for better fit
$dompdf->render();

// Output PDF
$dompdf->stream("appointment_slip_" . $appointment_id . ".pdf", [
    "Attachment" => false
]); 