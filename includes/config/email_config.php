<?php
/**
 * Email Configuration
 * 
 * Instructions for Gmail:
 * 1. Enable 2-Step Verification in your Google Account
 * 2. Go to https://myaccount.google.com/apppasswords
 * 3. Generate an App Password for "Mail"
 * 4. Use that 16-character password below
 * 
 * For other email providers, update the SMTP settings accordingly
 */

// Email Configuration Array
$emailConfig = [
    // SMTP Server Settings
    'smtp_host' => 'smtp.gmail.com',        // Gmail SMTP server
    'smtp_port' => 587,                      // TLS port (use 465 for SSL)
    'smtp_secure' => 'tls',                  // 'tls' or 'ssl'
    
    // Email Account Credentials
    'smtp_username' => 'vmctaccollege@gmail.com',     // Your Gmail address
    'smtp_password' => 'tqqs fkkh lbuz jbeg',         // Your Gmail App Password (16 characters)
    
    // Sender Information
    'from_email' => 'noreply@healthconnect.com',   // From email address
    'from_name' => 'HealthConnect',                 // From name
    
    // Application Settings
    'app_name' => 'HealthConnect',
    'app_url' => 'http://localhost/connect',       // Update to your domain in production
    'support_email' => 'support@healthconnect.com'
];

return $emailConfig;
