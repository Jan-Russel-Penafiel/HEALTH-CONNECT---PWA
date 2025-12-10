# Email Notification Setup Guide

## Overview
The system now sends automatic email notifications to patients when their registration is approved by an admin.

## Features
✅ Automatic email notification on patient approval  
✅ Professional HTML email template  
✅ Plain text fallback for email clients that don't support HTML  
✅ Configurable SMTP settings  
✅ Error logging for debugging  

## Setup Instructions

### 1. Configure Email Settings

Edit the file: `includes/config/email_config.php`

Update the following settings:

```php
'smtp_username' => 'your-email@gmail.com',    // Your Gmail address
'smtp_password' => 'your-app-password',        // Your Gmail App Password
```

### 2. Get Gmail App Password (Recommended)

**For Gmail users:**

1. Go to your Google Account: https://myaccount.google.com/
2. Select **Security** from the left menu
3. Enable **2-Step Verification** (if not already enabled)
4. After enabling 2-Step Verification, go back to Security
5. Click on **App passwords** (under "How you sign in to Google")
6. Select **Mail** and **Other (Custom name)**
7. Enter "HealthConnect" as the name
8. Click **Generate**
9. Copy the 16-character password (remove spaces)
10. Paste it in `email_config.php` as `smtp_password`

### 3. Alternative Email Providers

**For other email services (Outlook, Yahoo, etc.):**

Update these settings in `email_config.php`:

```php
// For Outlook/Hotmail
'smtp_host' => 'smtp-mail.outlook.com',
'smtp_port' => 587,
'smtp_secure' => 'tls',

// For Yahoo
'smtp_host' => 'smtp.mail.yahoo.com',
'smtp_port' => 587,
'smtp_secure' => 'tls',
```

### 4. Update Application URL

For production, update the app URL:

```php
'app_url' => 'https://yourdomain.com/connect',
```

## How It Works

1. Admin approves a patient in the Patients Management page
2. System updates the database to mark patient as approved
3. System retrieves patient's email, first name, and last name
4. Sends a professional email notification with:
   - Approval confirmation
   - List of available features
   - Login button/link
   - Support contact information
5. Displays success message indicating if email was sent

## Testing

To test the email functionality:

1. Register a new patient account (or use an existing unapproved one)
2. Log in as admin
3. Go to Patients Management
4. Click "Approve" on the pending patient
5. Check the patient's email inbox (and spam folder)

## Troubleshooting

### Email not sending?

**Check error logs:**
- Look in your PHP error log for messages starting with "Email could not be sent"
- Common issues:
  - Incorrect username/password
  - App password not generated (still using account password)
  - SMTP blocked by firewall
  - 2-Step Verification not enabled

**Gmail troubleshooting:**
- Make sure you're using an **App Password**, not your regular Gmail password
- Enable "Less secure app access" if not using 2-Step Verification (not recommended)
- Check if Google blocked the sign-in attempt

**Test SMTP connection:**
```php
// Add this temporarily to test connection
$mail->SMTPDebug = 2; // Enable verbose debug output
```

### Email goes to spam?

- Add proper SPF and DKIM records to your domain
- Use a professional email address (not Gmail) for production
- Consider using a dedicated email service (SendGrid, Mailgun, etc.)

## Production Recommendations

1. **Use a dedicated email service** (SendGrid, Amazon SES, Mailgun)
2. **Set up proper DNS records** (SPF, DKIM, DMARC)
3. **Use SSL/TLS encryption** (port 465 or 587)
4. **Monitor email delivery** rates and bounces
5. **Keep credentials secure** (use environment variables)
6. **Never commit** `email_config.php` with real credentials to version control

## Security Notes

⚠️ **Important:**
- Never share your App Password
- Don't commit real credentials to Git
- Use environment variables in production
- Regularly rotate passwords
- Enable 2-Step Verification

## File Structure

```
connect/
├── api/
│   └── patients/
│       └── update_approval.php    # Handles approval and sends email
├── includes/
│   └── config/
│       └── email_config.php       # Email configuration (UPDATE THIS)
└── EMAIL_SETUP_GUIDE.md          # This file
```

## Support

If you encounter issues:
1. Check PHP error logs
2. Verify SMTP settings
3. Test with a simple script first
4. Ensure PHPMailer is installed via Composer

## Next Steps

After setup:
- [ ] Update email configuration
- [ ] Test with a real patient approval
- [ ] Customize email template if needed
- [ ] Set up monitoring for failed emails
- [ ] Plan for production email service
