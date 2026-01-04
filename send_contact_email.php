<?php
/**
 * Contact Form Email Handler
 * Processes contact form submissions and sends email notifications
 */

// Prevent any output before JSON
ob_start();

// Disable error display (log errors instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set response header to JSON
header('Content-Type: application/json');

// Import PHPMailer classes at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Clear any output buffer
ob_clean();

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get and sanitize form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Validate form data
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Load PHPMailer
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception('PHPMailer is not installed. Please run composer install.');
    }
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Load email configuration
    if (!file_exists(__DIR__ . '/includes/config/email_config.php')) {
        throw new Exception('Email configuration file not found.');
    }
    $emailConfig = require __DIR__ . '/includes/config/email_config.php';
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = $emailConfig['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $emailConfig['smtp_username'];
    $mail->Password = $emailConfig['smtp_password'];
    $mail->SMTPSecure = $emailConfig['smtp_secure'];
    $mail->Port = $emailConfig['smtp_port'];
    
    // Recipients
    $mail->setFrom($emailConfig['smtp_username'], $emailConfig['from_name']);
    $mail->addAddress('alladinantolin@gmail.com', 'HealthConnect Admin');
    $mail->addReplyTo($email, $name);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Contact Form Submission from HealthConnect';
    
    // Create HTML email body
    $emailBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f9f9f9;
                border-radius: 8px;
            }
            .header {
                background-color: #4CAF50;
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 8px 8px 0 0;
            }
            .content {
                background-color: white;
                padding: 30px;
                border-radius: 0 0 8px 8px;
            }
            .info-row {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            .label {
                font-weight: bold;
                color: #4CAF50;
                display: inline-block;
                width: 100px;
            }
            .message-box {
                background-color: #f5f5f5;
                padding: 15px;
                border-left: 4px solid #4CAF50;
                margin-top: 20px;
                border-radius: 4px;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                color: #666;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>New Contact Form Submission</h2>
            </div>
            <div class="content">
                <div class="info-row">
                    <span class="label">From:</span>
                    <span>' . htmlspecialchars($name) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span>' . htmlspecialchars($email) . '</span>
                </div>' . 
                (!empty($phone) ? '
                <div class="info-row">
                    <span class="label">Phone:</span>
                    <span>' . htmlspecialchars($phone) . '</span>
                </div>' : '') . '
                <div class="message-box">
                    <strong>Message:</strong><br><br>
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <div class="footer">
                    <p>This email was sent from the HealthConnect contact form</p>
                    <p>Received on: ' . date('F j, Y \a\t g:i A') . '</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $mail->Body = $emailBody;
    
    // Plain text alternative
    $mail->AltBody = "New Contact Form Submission\n\n" .
                     "From: $name\n" .
                     "Email: $email\n" .
                     (!empty($phone) ? "Phone: $phone\n" : "") .
                     "\nMessage:\n$message\n\n" .
                     "Received on: " . date('F j, Y \a\t g:i A');
    
    // Send email
    $mail->send();
    
    $response['success'] = true;
    $response['message'] = 'Thank you for your message! We will get back to you soon.';
    
} catch (Exception $e) {
    $response['success'] = false;
    // Temporarily show actual error for debugging - remove in production
    $response['message'] = 'Error: ' . $e->getMessage();
    // Log the actual error for debugging
    error_log('Contact Form Error: ' . $e->getMessage());
} catch (Throwable $e) {
    // Catch any other errors (PHP 7+)
    $response['success'] = false;
    $response['message'] = 'Fatal Error: ' . $e->getMessage();
    error_log('Contact Form Fatal Error: ' . $e->getMessage());
}

// Clear output buffer and return clean JSON response
ob_end_clean();
echo json_encode($response);
exit;
