<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/config/database.php';

// Check if user is health worker
if ($_SESSION['role'] !== 'health_worker') {
    header('Location: /connect/pages/login.php');
    exit();
}

// Get patient ID and appointment ID from request
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$patient_id) {
    header('Location: /connect/pages/health_worker/appointments.php?error=missing_patient');
    exit();
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get health worker ID
try {
    $query = "SELECT health_worker_id FROM health_workers WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $health_worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$health_worker) {
        header('Location: /connect/pages/login.php');
        exit();
    }
    
    $health_worker_id = $health_worker['health_worker_id'];
} catch (PDOException $e) {
    error_log("Error fetching health worker ID: " . $e->getMessage());
    header('Location: /connect/pages/login.php');
    exit();
}

// Get patient details
try {
    $query = "SELECT p.*, u.first_name, u.last_name, u.date_of_birth, u.email, u.mobile_number
              FROM patients p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.patient_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: /connect/pages/health_worker/appointments.php?error=patient_not_found');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching patient: " . $e->getMessage());
    header('Location: /connect/pages/health_worker/appointments.php?error=database');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_date = $_POST['visit_date'] ?? '';
    $chief_complaint = $_POST['chief_complaint'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $prescription = $_POST['prescription'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $has_follow_up = isset($_POST['has_follow_up']) && $_POST['has_follow_up'] === 'yes';
    $follow_up_date = $has_follow_up ? ($_POST['follow_up_date'] ?? '') : '';
    $follow_up_message = $has_follow_up ? ($_POST['follow_up_message'] ?? '') : '';
    
    // Combine notes with follow-up message using JSON structure
    $notes_data = [
        'notes' => $notes,
        'follow_up_message' => $follow_up_message
    ];
    $combined_notes = json_encode($notes_data);
    
    try {
        $query = "INSERT INTO medical_records (patient_id, health_worker_id, visit_date, chief_complaint, diagnosis, treatment, prescription, notes, follow_up_date, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $patient_id,
            $health_worker_id,
            $visit_date,
            $chief_complaint,
            $diagnosis,
            $treatment,
            $prescription,
            $combined_notes,
            $follow_up_date ?: null
        ]);
        
        // Send SMS notification for follow-up if applicable
        if ($has_follow_up && !empty($follow_up_date) && !empty($patient['mobile_number'])) {
            require_once __DIR__ . '/../../includes/sms.php';
            
            error_log("Follow-up SMS: Attempting to send SMS for follow-up on {$follow_up_date} to {$patient['mobile_number']}");
            
            $formatted_date = date('M j, Y', strtotime($follow_up_date));
            $patient_name = $patient['first_name'];
            
            // Build message based on whether there's a doctor's note
            if (!empty($follow_up_message)) {
                $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Doctor's note: {$follow_up_message}. Thank you. - Respective Personnel";
            } else {
                $message = "Hello {$patient_name}, reminder: Follow-up checkup on {$formatted_date}. Please visit the Health Center. Thank you. - Respective Personnel";
            }
            
            error_log("Follow-up SMS Message: {$message}");
            
            // sendSMS function handles checking if SMS is enabled
            $sms_result = sendSMS($patient['mobile_number'], $message);
            
            if ($sms_result && isset($sms_result['success'])) {
                error_log("Follow-up SMS Result: " . json_encode($sms_result));
                if (!$sms_result['success']) {
                    error_log("Follow-up SMS Failed: " . ($sms_result['message'] ?? 'Unknown error'));
                }
            } else {
                error_log("Follow-up SMS: sendSMS returned unexpected result");
            }
        } else {
            error_log("Follow-up SMS skipped: has_follow_up={$has_follow_up}, follow_up_date=" . ($follow_up_date ?: 'empty') . ", mobile=" . ($patient['mobile_number'] ?? 'empty'));
        }
        
        // Redirect based on follow-up selection
        if ($has_follow_up) {
            // Redirect to appointments page with success message
            header('Location: /connect/pages/health_worker/appointments.php?success=medical_record_added&follow_up=1');
        } else {
            // No follow-up, redirect to done appointments
            header('Location: /connect/pages/health_worker/done_appointments.php?success=medical_record_added');
        }
        exit();
    } catch (PDOException $e) {
        error_log("Error adding medical record: " . $e->getMessage());
        $error = "Failed to add medical record. Please try again.";
    }
}

// Calculate patient's age
$age = '';
if ($patient['date_of_birth']) {
    $dob = new DateTime($patient['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medical Record - HealthConnect</title>
    <?php include __DIR__ . '/../../includes/header_links.php'; ?>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .page-container {
            padding-top: 80px;
            padding-bottom: 30px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            margin: 0 0 1rem 0;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 i {
            font-size: 1.75rem;
        }

        .patient-info {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            font-size: 0.95rem;
        }

        .patient-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 0.25rem;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .patient-info {
                flex-direction: column;
                gap: 0.75rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container page-container">
        <?php if ($appointment_id): ?>
            <a href="/connect/pages/health_worker/view_appointment.php?id=<?php echo $appointment_id; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Appointment
            </a>
        <?php else: ?>
            <a href="/connect/pages/health_worker/view_medical_history.php?patient_id=<?php echo $patient_id; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Medical History
            </a>
        <?php endif; ?>

        <div class="page-header">
            <h1>
                <i class="fas fa-file-medical"></i>
                Add Medical Record
            </h1>
            <div class="patient-info">
                <div class="patient-info-item">
                    <i class="fas fa-user"></i>
                    <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                </div>
                <?php if ($age): ?>
                <div class="patient-info-item">
                    <i class="fas fa-birthday-cake"></i>
                    <?php echo $age; ?> years old
                </div>
                <?php endif; ?>
                <div class="patient-info-item">
                    <i class="fas fa-id-card"></i>
                    Patient ID: <?php echo $patient_id; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="visit_date" class="form-label">
                        Visit Date<span class="required">*</span>
                    </label>
                    <input type="date" 
                           id="visit_date" 
                           name="visit_date" 
                           class="form-control" 
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo date('Y-m-d'); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="chief_complaint" class="form-label">
                        Chief Complaint<span class="required">*</span>
                    </label>
                    <textarea id="chief_complaint" 
                              name="chief_complaint" 
                              class="form-control"
                              placeholder="Patient's primary reason for visit or main health concern"
                              required></textarea>
                </div>

                <div class="form-group">
                    <label for="diagnosis" class="form-label">
                        Diagnosis<span class="required">*</span>
                    </label>
                    <textarea id="diagnosis" 
                              name="diagnosis" 
                              class="form-control"
                              placeholder="Enter the diagnosis or assessment"
                              required></textarea>
                </div>

                <div class="form-group">
                    <label for="treatment" class="form-label">
                        Treatment Plan
                    </label>
                    <textarea id="treatment" 
                              name="treatment" 
                              class="form-control"
                              placeholder="Enter the treatment plan, procedures performed, or recommendations"></textarea>
                </div>

                <div class="form-group">
                    <label for="prescription" class="form-label">
                        Prescription
                    </label>
                    <textarea id="prescription" 
                              name="prescription" 
                              class="form-control"
                              placeholder="List medications prescribed with dosage and instructions"></textarea>
                </div>

                <div class="form-group">
                    <label for="notes" class="form-label">
                        Additional Notes
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control"
                              placeholder="Any additional observations or notes"></textarea>
                </div>

                <!-- Follow-up Checkup Section -->
                <div class="form-group" style="margin-top: 1.5rem; padding: 1.5rem; background: #f8f9fa; border-radius: 10px; border: 2px solid #e0e0e0;">
                    <label class="form-label" style="margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fas fa-calendar-check" style="color: var(--primary-color);"></i>
                        Follow-up Checkup
                    </label>
                    
                    <div style="display: flex; gap: 2rem; margin-bottom: 1rem;">
                        <label class="follow-up-option" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.75rem 1rem; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s ease;">
                            <input type="radio" name="has_follow_up" value="yes" id="follow_up_yes" onchange="toggleFollowUpFields()">
                            <span><i class="fas fa-check-circle" style="color: #4CAF50;"></i> Has Follow-up Checkup</span>
                        </label>
                        <label class="follow-up-option" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.75rem 1rem; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s ease;">
                            <input type="radio" name="has_follow_up" value="no" id="follow_up_no" checked onchange="toggleFollowUpFields()">
                            <span><i class="fas fa-times-circle" style="color: #f44336;"></i> No Follow-up</span>
                        </label>
                    </div>
                    
                    <div id="follow_up_fields" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                        <div class="form-group">
                            <label for="follow_up_date" class="form-label">
                                Follow-up Date<span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="follow_up_date" 
                                   name="follow_up_date" 
                                   class="form-control" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <label for="follow_up_message" class="form-label">
                                Doctor's Message for Patient (Optional)
                                <small style="display: block; color: #666; font-weight: normal; margin-top: 0.25rem;">
                                    <i class="fas fa-sms"></i> This message will be included in the SMS notification sent to the patient
                                </small>
                            </label>
                            <textarea id="follow_up_message" 
                                      name="follow_up_message" 
                                      class="form-control"
                                      placeholder="E.g., Please bring your previous lab results, Continue taking prescribed medications, etc."
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Medical Record
                    </button>
                    <a href="/connect/pages/health_worker/appointments.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    
    <script>
    function toggleFollowUpFields() {
        const hasFollowUp = document.getElementById('follow_up_yes').checked;
        const followUpFields = document.getElementById('follow_up_fields');
        const followUpDateInput = document.getElementById('follow_up_date');
        
        if (hasFollowUp) {
            followUpFields.style.display = 'block';
            followUpDateInput.setAttribute('required', 'required');
        } else {
            followUpFields.style.display = 'none';
            followUpDateInput.removeAttribute('required');
            followUpDateInput.value = '';
            document.getElementById('follow_up_message').value = '';
        }
        
        // Update option styling
        document.querySelectorAll('.follow-up-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio.checked) {
                option.style.borderColor = 'var(--primary-color)';
                option.style.backgroundColor = 'rgba(74, 144, 226, 0.1)';
            } else {
                option.style.borderColor = '#e0e0e0';
                option.style.backgroundColor = 'transparent';
            }
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleFollowUpFields();
    });
    </script>
</body>
</html>
